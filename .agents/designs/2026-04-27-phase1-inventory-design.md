# Phase 1: Inventory Management — Design & Summary

**Date:** 2026-04-27
**Branch:** `feature/bb5_inventory`
**Status:** Implemented, pending Docker verification

---

## Контекст

Первая рабочая фаза бота. Бармен управляет инвентарём бара — какие ингредиенты и в каком количестве есть. Инвентарь разблокирует фазу 2 (кнопка "Заказать" зависит от наличия ингредиентов) и фазу 3 (бар-сессии).

---

## Ключевые решения

**User model** — стандартная Laravel users миграция (email/password) заменена на Telegram-ориентированную: `telegram_id BIGINT UNIQUE`, `first_name`, `username`. Без timestamps `updated_at`.

**Inventory model** — модель называется `Inventory` (не `BarInventory`), таблица `bar_inventory` (существующая миграция). `$table = 'bar_inventory'` в модели.

**Action/Handler pattern** — введён только для нового кода инвентаря. Существующие Search/Filter conversations не трогались.

**HTTP API** — без авторизации в Phase 1. `telegram_id` передаётся как query/body параметр.

**Тесты** — переключены с SQLite в-память на PostgreSQL `bar_bot_test`. `RefreshDatabase` включён для Unit и Feature суитов в `Pest.php`.

---

## Архитектура

```
Telegram /inventory            → InventoryAction::fromTelegram    → ListInventoryHandler
callback inventory:show        → InventoryAction::fromTelegram
callback inventory:add         → AddInventoryConversation::begin
callback inventory:remove:{id} → RemoveInventoryAction::fromTelegram → RemoveInventoryHandler

GET    /api/inventory          → InventoryAction::__invoke         → ListInventoryHandler
POST   /api/inventory          → AddInventoryAction::__invoke      → AddInventoryHandler
DELETE /api/inventory/{id}     → RemoveInventoryAction::__invoke   → RemoveInventoryHandler
```

---

## Файловая карта

### Переписаны
- `database/migrations/0001_01_01_000000_create_users_table.php` — raw PostgreSQL, без email/password
- `app/Models/Ingredient.php` — добавлен `HasFactory`
- `app/Telegram/Handlers/StartHandler.php` — кнопка "❓ Помощь" заменена на "📦 Инвентарь" (callback: `inventory:show`)
- `routes/telegram.php` — зарегистрированы inventory-хендлеры
- `tests/Pest.php` — `RefreshDatabase` для всех суитов, pgsql test DB
- `phpunit.xml` — `DB_CONNECTION=pgsql`, `DB_DATABASE=bar_bot_test`

### Созданы
```
app/
  Models/
    User.php                    telegram_id, first_name, username; hasMany(Inventory)
    Inventory.php               $table='bar_inventory'; belongsTo(User, Ingredient)
  Services/
    TelegramUserResolver.php    User::firstOrCreate по telegram_id
  Data/Inventory/
    AddInventoryData.php        user_id, ingredient_id, ?quantity, ?unit
    RemoveInventoryData.php     user_id, inventory_id
  Handlers/Inventory/
    ListInventoryHandler.php    Inventory::where(user_id)->with('ingredient')
    AddInventoryHandler.php     Inventory::updateOrCreate
    RemoveInventoryHandler.php  Inventory::where(id, user_id)->delete
  Actions/Inventory/
    InventoryAction.php         buildMessage() — статический метод для переиспользования
    AddInventoryAction.php      fromTelegram вызывается из Conversation
    RemoveInventoryAction.php   fromTelegram редактирует сообщение со списком
  Telegram/Conversations/
    AddInventoryConversation.php  4 шага: поиск → выбор → количество → сохранение

database/factories/
  UserFactory.php
  IngredientFactory.php
  InventoryFactory.php

routes/
  api.php                       GET/POST/DELETE /api/inventory

tests/
  Unit/Handlers/Inventory/
    ListInventoryHandlerTest.php   3 теста
    AddInventoryHandlerTest.php    3 теста
    RemoveInventoryHandlerTest.php 2 теста
  Feature/Actions/Inventory/
    InventoryActionTest.php        5 тестов
```

---

## Telegram UX

### Список (`/inventory` или callback `inventory:show`)
```
📦 Инвентарь бара — 3 позиции:
[Водка — 500 мл] [❌]
[Лимон — 3 шт]  [❌]
[Лёд]            [❌]
[➕ Добавить ингредиент]
```

### AddInventoryConversation
```
1. Бот: "Введите название ингредиента:"
2. Пользователь пишет → ILIKE поиск → до 8 кнопок
3. Пользователь выбирает → Бот: "Введите количество... или [Пропустить]"
4. Парсинг "500 мл" → quantity=500, unit="мл" → сохранение → показ списка
```

Парсинг количества: regex `^(\d+(?:[.,]\d+)?)\s*(.*)$` — число + остаток как unit.

---

## Верификация (при запущенном Docker)

```bash
# Создать тестовую БД (один раз)
docker compose exec postgres psql -U root -c "CREATE DATABASE bar_bot_test;"

# Пересоздать основную БД
make migration-fresh

# Тесты (8 unit + 5 feature = 13 тестов)
make tests

# Ручная проверка Telegram:
# /start → кнопка "📦 Инвентарь"
# /inventory → пустой список с "➕ Добавить"
# Добавить → диалог поиска → появляется в списке
# ❌ → исчезает из списка
```

---

## Что НЕ входит в Phase 1

- Search/Filter conversations (реализованы, но закомментированы до Phase 2)
- Кнопка "Заказать" на рецепте (Phase 2 — зависит от инвентаря)
- Авторизация HTTP API (Phase 6 по спеку)
- Роли (бармен vs гость) — в Phase 1 инвентарём управляет любой пользователь
