# Bar Bot — Design Spec

**Date:** 2026-04-22
**Project:** telegram-bar-bot

---

## 1. Контекст и цель

Telegram-бот для управления домашним баром. Вдохновлён приложением «Мой Бар» (Google Play), которое не даёт нужной функциональности: нет инвентаря, нет бар-сессий, нет заказа коктейлей гостями.

**Аудитория:** владелец бара (бармен, он же разработчик) + несколько друзей-гостей.

**Основная ценность:**
- Бармен ведёт инвентарь ингредиентов
- Гости ищут коктейли по тому, что есть в баре, и делают заказы
- Бармен ведёт бар-сессии (что было сделано вечером)

---

## 2. Технический стек

| Слой | Технология |
|---|---|
| Runtime | Laravel 13 + PHP 8.5 + Octane (RoadRunner) |
| Bot framework | Nutgram (webhook, не WebSocket) |
| База данных | PostgreSQL 18 |
| DTOs | spatie/laravel-data |
| HTTP роутинг | spatie/laravel-route-attributes |
| Тесты | Pest |
| Линтер | Laravel Pint (preset: per) |
| Туннель (local) | ngrok |

---

## 3. Архитектура

### 3.1 Поток запроса

```
Telegram → ngrok → TelegramController → Nutgram
  → routes/telegram.php (pattern matching)
    → Action (transport adapter)
      → DTO (spatie/laravel-data)
        → Handler (бизнес-логика)
          → результат → Action → ответ в Telegram

HTTP → routes/web.php или Route attributes
  → Action::__invoke / named method
    → DTO → Handler → JsonResponse
```

### 3.2 Action/Handler pattern

**Action** — транспортный адаптер, единая точка входа из разных источников. Один класс на фичу, методы per transport:

```php
class ForkCocktailAction {
    public function __construct(private ForkCocktailHandler $handler) {}

    // HTTP — вызывается как invokable controller
    public function __invoke(Request $request, string $id): JsonResponse {
        $data = ForkCocktailData::from([...]);
        return response()->json($this->handler->handle($data));
    }

    // Telegram — вызывается через [ForkCocktailAction::class, 'fromTelegram']
    public function fromTelegram(Nutgram $bot, string $id): void {
        $data = ForkCocktailData::from([...]);
        $result = $this->handler->handle($data);
        $bot->sendMessage("✅ Скопировано: {$result->name_ru}");
    }
}
```

Nutgram поддерживает `[ClassName::class, 'method']` через `$container->call()`.

**Handler** — чистая бизнес-логика, без зависимости от транспорта:

```php
class ForkCocktailHandler {
    public function handle(ForkCocktailData $data): Recipe { ... }
}
```

**Пакет lorisleiva/laravel-actions не используется** — паттерн реализован plain PHP.

### 3.3 Структура директорий

```
app/
  Actions/
    Inventory/
      AddIngredientAction.php      ← __invoke (HTTP) + fromTelegram
    Session/
      StartSessionAction.php
    Orders/
      PlaceOrderAction.php
    Search/
      SearchRecipesAction.php
    ...
  Handlers/
    Inventory/
      AddIngredientHandler.php
    Session/
      StartSessionHandler.php
    Orders/
      PlaceOrderHandler.php
    ...
  Data/                            ← DTOs (spatie/laravel-data)
    Inventory/
      AddIngredientData.php
    Orders/
      PlaceOrderData.php
    ...
  Telegram/
    Conversations/                 ← multi-step flows (Nutgram Conversation)
      SearchByNameConversation.php
      SearchByIngredientConversation.php
      FilterConversation.php
      AddInventoryConversation.php
      StartSessionConversation.php
  Models/
  Http/
    Controllers/                   ← тонкие HTTP-контроллеры (если не Action напрямую)
    Resources/                     ← API Resources
```

### 3.4 Nutgram routing

Два уровня: тип Update + pattern matching.

```php
// routes/telegram.php

// Команды
$bot->onCommand('start', StartAction::class);
$bot->onCommand('inventory', [InventoryAction::class, 'fromTelegram']);
$bot->onCommand('session', [SessionAction::class, 'fromTelegram']);

// Поиск
$bot->onCallbackQueryData('cmd:search', fn($bot) => SearchByNameConversation::begin($bot));
$bot->onCallbackQueryData('cmd:ingredients', fn($bot) => SearchByIngredientConversation::begin($bot));
$bot->onCallbackQueryData('cmd:filter', fn($bot) => FilterConversation::begin($bot));

// Рецепты
$bot->onCallbackQueryData('recipe:show:{id}', RecipeShowAction::class);
$bot->onCallbackQueryData('recipe:order:{id}', [PlaceOrderAction::class, 'fromTelegram']);
$bot->onCallbackQueryData('recipe:favorite:{id}', [FavoriteToggleAction::class, 'fromTelegram']);
$bot->onCallbackQueryData('recipe:rate:{id}', fn($bot, $id) => RateCocktailConversation::begin($bot));

// Инвентарь
$bot->onCallbackQueryData('inventory:remove:{id}', [RemoveInventoryAction::class, 'fromTelegram']);

// Бармен: управление заказами
$bot->onCallbackQueryData('order:accept:{id}', [AcceptOrderAction::class, 'fromTelegram']);
$bot->onCallbackQueryData('order:cancel:{id}', [CancelOrderAction::class, 'fromTelegram']);

// Бар-сессия
$bot->onCallbackQueryData('session:start', [StartSessionAction::class, 'fromTelegram']);
$bot->onCallbackQueryData('session:end', [EndSessionAction::class, 'fromTelegram']);

// Фото
$bot->onPhoto([UploadPhotoAction::class, 'fromTelegram']);
```

---

## 4. Модели данных

### Существующие (не меняются)

| Модель | Ключевые поля |
|---|---|
| `Recipe` | UUID PK, name_ru, name_en, abv, volume, glass, description, fork_of_id (Фаза 6) |
| `Ingredient` | id, name_ru, name_en |
| `RecipeIngredient` | recipe_id, ingredient_id, amount, unit, note, sort_order |
| `RecipeTag` | recipe_id, tag |

### Новые

| Модель | Ключевые поля |
|---|---|
| `User` | id, telegram_id (unique), first_name, username, created_at |
| `BarInventory` | id, user_id (FK→users), ingredient_id (FK→ingredients), quantity, unit |
| `BarSession` | id, started_at, ended_at (null = активная) |
| `Order` | id, session_id, user_id, recipe_id, quantity (nullable), status: `pending\|accepted\|cancelled` |
| `Favorite` | user_id, recipe_id (composite PK) |
| `Rating` | user_id, recipe_id (composite PK), score (1–5) |
| `RecipePhoto` | id, recipe_id, user_id, telegram_file_id, created_at |

### Фаза 6 (Форк)

`Recipe` получает nullable `fork_of_id` (UUID → `recipes.id`). Форк — это полноценный `Recipe`, принадлежащий конкретному `user_id`, с изменёнными ингредиентами.

---

## 5. Фазы разработки

### Фаза 1 — Инвентарь ингредиентов

Бармен управляет инвентарём бара (какие ингредиенты и в каком количестве есть).

**Telegram:**
- `/inventory` → список текущего инвентаря с кнопкой "Добавить"
- `AddInventoryConversation`: поиск ингредиента → ввод количества → сохранение
- Кнопка "Удалить" у каждого ингредиента

**HTTP API:**
- `GET /api/inventory` — список
- `POST /api/inventory` — добавить
- `DELETE /api/inventory/{id}` — удалить

### Фаза 1.1 — Роли и контроль доступа (bb5-s1)

Введение ролей пользователей и ограничение действий по роли.

**Роли:** `guest`, `bartender`, `owner`

**Telegram/HTTP:**
- Только `bartender`/`owner` могут добавлять и удалять из инвентаря
- Гости могут только просматривать инвентарь
- Миграция: добавить колонку `role` в таблицу `users`
- Логика доступа через middleware или Policy

---

### Фаза 2 — Поиск рецептов

Все пользователи могут искать коктейли.

**Telegram:**
- По названию (`SearchByNameConversation` — уже реализована)
- По ингредиентам (`SearchByIngredientConversation` — уже реализована)
- По фильтрам — ABV, объём, тип бокала, теги (`FilterConversation` — уже реализована)
- На карточке рецепта показывается кнопка "Заказать" **только если**: есть активная `BarSession` + все ингредиенты рецепта присутствуют в `BarInventory`

**HTTP API:**
- `GET /api/recipes?q=&glass=&abv_min=&abv_max=&tags=` — поиск с фильтрами
- `GET /api/recipes/{id}` — карточка рецепта

---

### Фаза 3 — Бар-сессия

Бармен открывает и закрывает сессию. Без активной сессии заказы невозможны.

**Telegram (только бармен):**
- `/session` → показывает текущую сессию или кнопку "Старт"
- Кнопка "Завершить сессию" → закрывает сессию (устанавливает `ended_at`)

**HTTP API:**
- `POST /api/sessions` — старт
- `PATCH /api/sessions/{id}/end` — завершить
- `GET /api/sessions/{id}` — детали сессии (заказы, итого)

---

### Фаза 3.1 — Заказ коктейля

Гость заказывает, бармен принимает/отклоняет.

**Флоу:**
1. Гость нажимает "Заказать" на карточке рецепта
2. Создаётся `Order(status=pending)`, бармену летит уведомление
3. Уведомление барменю: название коктейля, имя гостя, две кнопки: **Принять** / **Отказать**
4. Бармен нажимает **Принять** → бот спрашивает количество (ввод числом) → `Order(status=accepted, quantity=N)` сохраняется
5. Бармен нажимает **Отказать** → `Order(status=cancelled)`

**Конфиг:** `OWNER_TELEGRAM_ID` в `.env` — ID бармена для уведомлений.

**Ограничения:**
- Кнопка "Заказать" не показывается, если нет активной сессии
- Кнопка "Заказать" не показывается, если не все ингредиенты есть в инвентаре

**HTTP API:**
- `GET /api/sessions/{id}/orders` — список заказов сессии
- `PATCH /api/orders/{id}` — обновить статус/количество

---

### Фаза 4 — Избранное и оценки

Пользователи могут помечать рецепты.

**Telegram:**
- На карточке рецепта: кнопка "❤️ В избранное" / "💔 Убрать"
- На карточке рецепта: кнопки оценки 1–5 (после нажатия — `RateCocktailConversation` или inline выбор)
- `/favorites` → список избранного пользователя

**HTTP API:**
- `POST /api/recipes/{id}/favorite` — toggle
- `POST /api/recipes/{id}/rate` — `{score: 1-5}`
- `GET /api/favorites` — список избранного

---

### Фаза 5 — Загрузка фото

Пользователь прикрепляет фото к коктейлю.

**Telegram:**
- На карточке рецепта: кнопка "📷 Добавить фото"
- Бот ждёт фото (Conversation или `onPhoto`)
- Сохраняется `telegram_file_id` в `RecipePhoto`

**HTTP API:**
- `POST /api/recipes/{id}/photos` — загрузка
- `GET /api/recipes/{id}/photos` — список фото

---

### Фаза 6 — Форк коктейля

Пользователь копирует рецепт и изменяет под себя.

**Telegram:**
- На карточке рецепта: кнопка "🍴 Форкнуть"
- Создаётся копия `Recipe` с `fork_of_id` = оригинал, `user_id` = текущий пользователь
- Пользователь редактирует ингредиенты через `ForkCocktailConversation`

---

## 6. HTTP API

Swagger/OpenAPI через `darkaonline/l5-swagger`. Все эндпоинты тестируются через Postman/Scratch.

Базовый префикс: `/api/v1/`

Аутентификация: на первых фазах — без авторизации (личный проект), позднее — Sanctum или API-ключ.

---

## 7. Обработка ошибок

| Ситуация | Поведение |
|---|---|
| Рецепт не найден | `answerCallbackQuery(text: 'Рецепт не найден 😔')` |
| Нет активной сессии при заказе | Кнопка не показывается; если вызов напрямую — сообщение об ошибке |
| Не все ингредиенты в инвентаре | Кнопка "Заказать" скрыта |
| Conversation timeout | Nutgram завершает conversation, пользователь получает fallback-сообщение |
| Не бармен пытается управлять сессией | Проверка `$bot->userId() === config('bar.owner_telegram_id')`, ответ "Недостаточно прав" |

---

## 8. Тестирование

**Инструмент:** Pest

**Подход:**
- **Unit-тесты** для Handlers — изолированно, без транспорта, мокаем только внешние зависимости (БД через factories)
- **Feature-тесты** для HTTP Actions — реальные HTTP-запросы через Laravel test client
- **Feature-тесты** для Telegram Actions — мокаем Nutgram, проверяем что Action вызывает правильный Handler с правильным DTO

**Что не тестируем:** Conversations напрямую (слишком завязаны на состояние Nutgram), тестируем бизнес-логику через Handlers.

```
tests/
  Unit/
    Handlers/
      ForkCocktailHandlerTest.php
      PlaceOrderHandlerTest.php
      ...
  Feature/
    Actions/
      ForkCocktailActionTest.php
      SearchRecipesActionTest.php
      ...
```

---

## 9. Открытые вопросы (на будущее)

- Аутентификация HTTP API (Sanctum или API-ключ)
- Настраиваемые роли в сессии (несколько барменов)
- `taste_tags` / «вкусоматика» (зарезервировано в схеме)
- Upgrade Laravel 12 → 13 + PHP 8.5 (запланирован, не срочен)
