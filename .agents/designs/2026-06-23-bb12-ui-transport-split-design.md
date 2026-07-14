# BB-12: Разделение UI-входов по транспортам — Design Spec

**Date:** 2026-06-23
**Branch:** `BB-12_architecture_restruct`

---

## 1. Цель и контекст

Сейчас один Action обслуживает оба транспорта: `__invoke(Request)` для HTTP и `fromTelegram(Nutgram)` для Telegram, в одном классе под `app/UI/Http/Actions/`. Из-за этого:

- HTTP-namespace физически тянет Telegram: `routes/telegram.php` импортирует `App\UI\Http\Actions\*`, а «Http»-Action импортирует `App\UI\Telegram\Responses\*`.
- Фича размазана по типам классов (Action / Conversation / Response / Keyboard в разных папках).
- Двойной вход в одном классе усложняет поддержку: разные сигнатуры, разные форматы ответа, разные зависимости в одном файле.

**Решение:** развязать транспорты на уровне классов и пересобрать UI-слой в **вертикальные срезы по Context → UseCase**, симметрично для HTTP и Telegram. Это разворачивает прежний инвариант «один класс на use-case для всех транспортов» (`conversation-action-architecture.md`, раздел «Как НЕ надо #2»).

**Граница задачи — только транспорты.** Это **чисто структурная** реструктуризация: режем совмещённый класс на HTTP-класс и Telegram-точку входа и раскладываем по вертикальным срезам. **Поведение Telegram-UI и HTTP-API идентично:** те же тексты, кнопки, шаги Conversation, `callback_data`, URI/методы/middleware.

**Что НЕ входит в BB-12 (вынесено в отдельный design):** пересмотр архитектуры Conversation × Action — снятие хопа Conversation → Action, моделирование двухшаговых callback-флоу как Conversation (`order:pick:{n}`), рерайт `conversation-action-architecture.md`. Это отдельная тема, меняющая контракт `callback_data` и долгоживущую спеку. См. `2026-06-23-conversation-action-rework-design.md`. **В BB-12 хоп Conversation → Telegram-Action сохраняется как есть.**

`app/Handlers/`, `app/Data/`, `app/Models/`, `app/Services/`, схема БД — **не трогаются**. Меняются только классы под `app/UI/` и строки регистрации в `routes/*.php`.

---

## 2. Архитектурное решение: вертикальные срезы Context → UseCase

```
app/UI/Http/{Context}/{UseCase}/{Action, Request, Response}
app/UI/Http/{Context}/{Action}                     # промоут простого кейса до контекста

app/UI/Telegram/{Context}/{UseCase}/{Action, Conversation, Keyboard}
app/UI/Telegram/{Context}/{Command|Action}         # промоут простого кейса
app/UI/Telegram/Shared/{Keyboard, Presenter}       # кросс-контекстный рендер
```

- **Context** — это **папка** (домен, аналог HTTP-контроллера: `Inventory`, `Search`, `Recipe`, `Orders`, `Session`, `Favorites`, `Ratings`, `Start`). **Не класс.** Отдельного класса-контроллера-агрегатора нет.
- **UseCase** — папка внутри Context (`AddInventory`, `ListFavorites`, `BrowseRecipes`), держащая **все** классы одного действия.
- Когезия — про **владение**, а не про запрет ссылок. UseCase свободно импортирует Action/Keyboard другого UseCase или Context (Conversation → чужой Action, карточка рецепта из `Shared/`). Это норма.

### Роли классов

Транспорты развязаны: у каждого свой набор классов, общего между HTTP-Action и Telegram-точкой входа нет (общий только Handler, см. §3).

#### HTTP

| Класс | Назначение |
|---|---|
| **Action** | Инвокабельный контроллер. Парсит Request → DTO → Handler → Response. Тонкий, без бизнес-логики. |
| **Request** | FormRequest для валидации входа. Если хватает route-model-binding / скоупинга — не создаётся. |
| **Response** | Преобразование выхода (`JsonResource` / `JsonResponse`). Если отдаём модель как есть — не создаётся. |

#### Telegram

**Telegram entry-point** — обобщённая роль для трёх форм: **Command** (вход по команде), **Action** (stateless-callback) и **Conversation** (multi-step UX). Задача одна на всех: распарсить/накопить ввод → собрать DTO → позвать Handler → отрендерить через Keyboard. Различаются только формой ввода.

| Класс | Назначение |
|---|---|
| **Command** | Telegram entry-point: обработчик команды (`/start`). |
| **Action** | Telegram entry-point: обработчик stateless-callback. Парсит `callback_data` → DTO → Handler → render. |
| **Conversation** | Telegram entry-point: multi-step UX (Nutgram `Conversation`). State в свойствах, навигация через `next()`. **На финальном шаге зовёт Telegram-Action через `app(...Action)->fromTelegram(...)`** — хоп сохраняется (см. §3, инвариант 4). |
| **Keyboard** | Рендер текста + `InlineKeyboardMarkup` (нынешние `Responses/*` переезжают сюда). |

### Где живут общие классы

| Переиспользование | Дом |
|---|---|
| Внутри одного Context (несколько UseCase) | Плоско на уровне Context: `Telegram/Inventory/InventoryListKeyboard.php` |
| Между разными Context | `app/UI/Telegram/Shared/` (напр. `RecipeCardKeyboard` — Search/Favorites/Browse) |
| HTTP shared (JsonResource и т.п.) | `app/UI/Http/Shared/` — **создаётся только при реальной необходимости**, заранее не заводим |

---

## 3. Инварианты (замена снятой гарантии)

Прежняя спека запрещала транспорт-split, чтобы два транспорта не разъехались в поведении. Снимая запрет, **явно переносим эту гарантию в Handler**:

1. **Handler — единственный владелец семантики use-case.** Вся бизнес-логика только в `app/Handlers/`.
2. **Оба транспорта — тонкие и ОБЯЗАНЫ ходить через один и тот же Handler.** HTTP-Action и Telegram-точка входа одного use-case зовут один и тот же Handler. Запрещено реализовывать поведение в точке входа.
3. **Zero business logic в точках входа и Keyboard.** Допустимо: парсинг payload/`callback_data`, накопление state, сборка DTO, вызов Handler, форматирование ответа, навигация шагов (`next()`).
4. **Хоп Conversation → Telegram-Action сохраняется.** Conversation на финальном шаге зовёт `app(...Action)->fromTelegram(...)`, а не Handler напрямую — как сегодня (см. `codebase.md`, «Паттерн для вызова Action из Conversation»; пример — `AddInventoryConversation::save`). Снятие этого хопа — отдельная задача, см. `2026-06-23-conversation-action-rework-design.md`.
5. **Рендер финального результата — в Keyboard, не inline.** То, чего боялась старая спека (разъехавшийся рендер двух входов), предотвращается выносом рендера в Keyboard-классы. Промежуточные шаговые промпты Conversation (`sendMessage` «введите количество», выбор единицы) могут оставаться inline.
6. **Auth:** `AuthenticateTelegramUser` middleware выполняет `Auth::setUser()` до точки входа; `Auth::id()` доступен во всех Telegram entry-point. `User::where('telegram_id'` — только в middleware.

Без пунктов 1–5 разделение делает систему **слабее** прежней: два транспорта смогут разъехаться. Эти инварианты — то, что делает split безопасным.

---

## 4. Правила структуры

### 4.1 Промоут простого кейса

UseCase-папка заводится, если **классов ≥2 ИЛИ присутствует Conversation/Request/Response**. Иначе единственный класс лежит плоско на уровне Context.

```
app/UI/Http/Inventory/RemoveInventoryAction.php            # 1 класс → плоско
app/UI/Telegram/Start/StartCommand.php                     # 1 класс → плоско
app/UI/Http/Inventory/AddInventory/AddInventoryAction.php  # +Request → папка
app/UI/Http/Inventory/AddInventory/AddInventoryRequest.php
```

Цена правила: при перерастании простого кейса в папку у класса меняется namespace → правки в `routes/*.php` и тестах. Принято осознанно ради отсутствия пустых одно-файловых папок.

### 4.2 Двухшаговые callback-флоу остаются как есть

Флоу `PlaceOrder` (шаг 1: показать пикер количества → шаг 2: оформить заказ) сейчас живёт как `PlaceOrderAction::fromTelegram` + `PlaceOrderAction::confirm` на одном Telegram-классе, с контрактом `recipe:{id}:order:{qty}`. **В BB-12 это поведение сохраняется без изменений** — при разделении транспортов HTTP `__invoke` уезжает в `app/UI/Http/Orders/`, а Telegram-методы (`fromTelegram` + `confirm`) — в `app/UI/Telegram/Orders/`, контракт `callback_data` не меняется.

> Моделирование таких флоу как Conversation и смена контракта на `order:pick:{n}` — **вне BB-12**, см. `2026-06-23-conversation-action-rework-design.md` (там это меняет поведение).

### 4.3 HTTP-маршрутизация через spatie route-attributes

`spatie/laravel-route-attributes ^1.0` уже в `composer.json`. Маршрут объявляется атрибутом на методе Action (`#[Get]`, `#[Post]`, `#[Delete]`, `#[Middleware]`, `#[Prefix]`). `routes/api.php` сводится к минимуму (или к группам префиксов/middleware, если атрибуты их не покрывают чисто).

**Acceptance:** `php artisan route:list` до и после среза даёт идентичный набор URI/методов/middleware. Молчаливое изменение URI ломает HTTP-клиентов и тесты — это блокирующая проверка каждого среза.

---

## 5. Контексты и границы

| Context | HTTP UseCase | Telegram UseCase | Заметки |
|---|---|---|---|
| `Inventory` | List, Add, Remove | List, Add (Conversation), Remove | `InventoryListKeyboard` — context-shared |
| `Search` | SearchRecipes | ByName, ByIngredient, Filter (всё Conversation с наборными фильтрами) | TG-сторона преимущественно Conversation-centric |
| `Recipe` | GetRecipe | Browse, Show | `RecipeCardKeyboard` → `Shared/` |
| `Orders` | Place, List, Accept, Cancel | PlaceOrder (two-step Action), List, Accept, Cancel | контракт `recipe:{id}:order:{qty}` сохраняется |
| `Session` | Show, Start | Show, Start | |
| `Favorites` | Toggle, List | Toggle, List (Conversation) | |
| `Ratings` | Rate | Rate, ShowPicker | |
| `Start` | — | StartCommand | промоут до Context |

**Search vs Recipe разведены:** просмотр (Browse/Show) и поиск — разные контексты. Если на практике окажется неудобно — переобъединим (решение обратимо).

---

## 6. Пилот и порядок тиража

**Пилот: `Inventory` + `Recipe` (Browse/Show).** Вместе покрывают весь паттерн:

- **Inventory** — HTTP route-attributes с вложенным `CanManage`-prefix-group, callback-Action, Conversation (с сохранённым хопом Conversation → Action), context-shared keyboard.
- **Recipe** — `Telegram/Shared/RecipeCardKeyboard` (карточка рецепта, переиспользуемая Search/Favorites/Browse). Это единственный структурный элемент, который Inventory не задействует.

Карточка рецепта эмитит `callback_data` (`recipe:{id}:order`, `:favorite`, `:rate`), чьи Action ещё не мигрированы — это ок, `callback_data` это строки, старые обработчики продолжают их ловить до миграции своих срезов.

После утверждения пилота — тираж по контекстам: `Search`, `Orders`, `Session`, `Favorites`, `Ratings`, `Start`. Каждый срез — отдельная задача плана.

**Рерайт `conversation-action-architecture.md`** в BB-12 **не делается** — он вынесен в `2026-06-23-conversation-action-rework-design.md` (после BB-12). До тех пор для срезов под `app/UI/` старая спека продолжает действовать как есть (хоп Conversation → Action сохраняется).

---

## 7. Воркед-пример: Inventory (старое → новое)

```
# HTTP
app/UI/Http/Actions/Inventory/InventoryAction.php::__invoke
  → app/UI/Http/Inventory/ListInventoryAction.php            #[Get('inventory')]
app/UI/Http/Actions/Inventory/AddInventoryAction.php::__invoke
  → app/UI/Http/Inventory/AddInventory/AddInventoryAction.php #[Post('inventory')] + AddInventoryRequest
app/UI/Http/Actions/Inventory/RemoveInventoryAction.php::__invoke
  → app/UI/Http/Inventory/RemoveInventoryAction.php           #[Delete('inventory/{id}')]

# Telegram
InventoryAction::fromTelegram        → app/UI/Telegram/Inventory/ListInventoryAction.php   # 1 класс → плоско
InventoryListResponse                → app/UI/Telegram/Inventory/InventoryListKeyboard.php  (context-shared)
AddInventoryConversation             → app/UI/Telegram/Inventory/AddInventory/AddInventoryConversation.php
AddInventoryAction::fromTelegram     → app/UI/Telegram/Inventory/AddInventory/AddInventoryAction.php
#   Conversation::save продолжает звать app(этот TG-Action)->fromTelegram(...) — хоп сохраняется (инвариант 4)
RemoveInventoryAction::fromTelegram  → app/UI/Telegram/Inventory/RemoveInventoryAction.php
```

`routes/telegram.php` и `routes/api.php` обновляют импорты и таргеты на новые классы.

---

## 8. Тесты и чистка

- **Миграция тестов — в каждом срезе.** Любой тест, ссылающийся на `App\UI\Http\Actions\*` или зовущий `fromTelegram`, ломается. Срез владеет обновлением своих тестов; handoff требует `make tests` зелёным.
- **Удалить пустые каркасные папки**, не вписавшиеся в co-location: `app/UI/Http/Requests`, `app/UI/Http/Responses`, `app/UI/Telegram/Commands`, `app/UI/Telegram/Keyboards`. Чтобы реализатор не оставил мёртвых каталогов.
- **telegram-ui.md** не меняется по содержанию экранов (тексты/кнопки те же), но при изменении путей классов в примерах — обновить затронутые.

---

## 9. Что НЕ делаем (out of scope)

- Не меняем Handlers/Data/Models/Services и схему БД.
- Не меняем тексты сообщений, наборы кнопок, шаги Conversation, контракт `callback_data` — **поведение Telegram-UI и HTTP-API полностью идентично**.
- Не убираем хоп Conversation → Action и не моделируем двухшаговые флоу как Conversation — это `2026-06-23-conversation-action-rework-design.md`.
- Не переписываем `conversation-action-architecture.md` — там же.
- Не вводим `Http/Shared/` заранее — только по факту необходимости.
- Не делаем весь рефактор одним PR — только пилот, затем срезы.
