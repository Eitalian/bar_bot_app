# Audit: specs/ + knowledge/ vs кодовая база

**Дата аудита:** 2026-06-01
**Ветка на момент аудита:** `master` (HEAD `f18ece2`)
**Цель документа:** дать агенту чистой сессии полный контекст обнаруженных расхождений, чтобы он мог принять решения и применить правки без повторного аудита.

---

## Что и как проверялось

Сверены следующие файлы документации с фактическим состоянием кода:

- `.agents/specs/bar-bot-design.md`
- `.agents/specs/conversation-action-architecture.md`
- `.agents/specs/migration-conventions.md`
- `.agents/knowledge/codebase.md`

Источники истины для сверки:

- `composer.json` — версии Laravel/PHP/пакетов.
- `database/migrations/` — фактическая схема (16 миграций).
- `database/factories/` — фабрики моделей.
- `app/Actions/`, `app/Handlers/`, `app/Models/`, `app/Telegram/Conversations/` — реальная имплементация.
- `routes/telegram.php`, `routes/api.php` — реальные маршруты.
- `app/Providers/BusServiceProvider.php` (через ссылку в `codebase.md`) — bus-маппинг для command-actions.
- `grep` на архитектурные инварианты Conversation × Action.

---

## Контекст реальной кодовой базы (выжимка)

### Versions
- `laravel/framework: ^13.0`
- `php: ^8.5`
- Stack: Laravel 13 + PHP 8.5 + Octane (RoadRunner) + Nutgram + PostgreSQL 18.

### Фазы, по факту имплементации

| Фаза | Статус по коду | Артефакты в `.agents/` |
|---|---|---|
| 1. Инвентарь | ✅ Реализована (`app/Actions/Inventory/*`, `app/Handlers/Inventory/*`, `AddInventoryConversation`) | design + план есть |
| 1.1. Роли (guest/bartender/owner) | ✅ Реализована (`UserRole` enum, `CanManageMiddleware`, миграция `2026_04_27_000002_alter_users_add_role`) | design + план есть |
| 2. Поиск рецептов | ✅ Реализована (`SearchByName/SearchByIngredient/Filter` conversations, 4 Action'а в `app/Actions/Search/`) | план есть |
| 3. Бар-сессии | ✅ Реализована (`BarSession` модель, `SessionAction`, `StartSessionAction`, `CloseSessionJob`, миграция `2026_05_10_000001_create_bar_sessions_table`) | план есть |
| 3.1. Заказы | ⏳ **Спроектирована, не реализована.** Нет `Order` модели, `PlaceOrderAction`, маршрутов `order:*`. | design + план есть (`2026-05-26-phase3-1-orders-design.md`, `2026-05-26-phase3-1-orders.md`) |
| 4. Избранное/оценки | ⏳ Спроектирована, не реализована. | design (`2026-06-01-phase4-favorites-ratings-design.md`) |
| 5. Загрузка фото | ⏳ Не начата | нет |
| 6. Форк коктейля | ⏳ Не начата | нет |

### Существующие модели
`Bar` (POPO), `BarSession`, `Ingredient`, `Inventory`, `Recipe`, `RecipeIngredient`, `RecipeTag`, `User`.

**Отсутствуют по коду** (упомянуты в `bar-bot-design.md`): `Order`, `Favorite`, `Rating`, `RecipePhoto`.

### Существующие фабрики
`BarSessionFactory`, `IngredientFactory`, `InventoryFactory`, `RecipeFactory`, `UserFactory`.

### Существующие conversations
`AddInventoryConversation`, `FilterConversation`, `SearchByIngredientConversation`, `SearchByNameConversation`.

**Отсутствуют по коду** (упомянуты в spec'е): `StartSessionConversation`, `ForkCocktailConversation`, `RateCocktailConversation`.

### Фактические маршруты `routes/telegram.php`
```
onCommand('start')                              → StartAction::fromTelegram
onCommand('inventory')                          → InventoryAction::fromTelegram
onCommand('session')                            → SessionAction::fromTelegram

onCallbackQueryData('inventory:show')           → InventoryAction
onCallbackQueryData('cmd:session')              → SessionAction
onCallbackQueryData('cmd:search')               → SearchByNameConversation::begin
onCallbackQueryData('cmd:ingredients')          → SearchByIngredientConversation::begin
onCallbackQueryData('cmd:filter')               → FilterConversation::begin
onCallbackQueryData('recipe:browse:{key}:{pos}')→ BrowseRecipesAction
onCallbackQueryData('recipe:show:{id}')         → GetRecipeAction
onCallbackQueryData('browse:back')              → StartAction
onCallbackQueryData('noop')                     → answerCallbackQuery

Group [CanManageMiddleware]:
  onCallbackQueryData('inventory:add')           → AddInventoryConversation::begin
  onCallbackQueryData('inventory:remove:{id}')   → RemoveInventoryAction
  onCallbackQueryData('session:start')           → StartSessionAction
```

### Архитектурный инвариант Conversation × Action
`grep -rn "app(.*Handler::class)" app/Telegram/` → **0 совпадений**. Папки `app/Telegram/Handlers/` нет. Инвариант держится.

### Bus-паттерн (codebase.md §«Bus-паттерн для команд»)
Command-actions (`AddInventoryAction`, `RemoveInventoryAction`) диспатчат через `Bus::dispatch(new AddInventoryData(...))`, маппинг `Data → Handler` в `BusServiceProvider`. Query-actions (`InventoryAction`, поисковые) внедряют Handler напрямую через DI.

---

## Несоответствия — punch list

### A. `.agents/specs/bar-bot-design.md`

#### A1. §3.1 «Поток запроса» — отсутствует bus-паттерн
**Что в spec'е:** диаграмма показывает `Action → DTO → Handler` напрямую для всех случаев.
**Что в коде:** command-actions идут через `Bus::dispatch(Data)` с маппингом в `BusServiceProvider`. Query-actions — напрямую.
**Риск:** агент, читающий §3.1 как контракт, пропустит регистрацию маппинга в `BusServiceProvider` при добавлении новой command-фичи.
**Рекомендация:** добавить sub-flow «Command → Bus → Handler (через map в `BusServiceProvider`)» либо явный disclaimer «bus — имплементационная деталь, см. `codebase.md`».

#### A2. §3.3 «Структура директорий» — `StartSessionConversation.php`
**Что в spec'е:** в списке `app/Telegram/Conversations/` указан `StartSessionConversation.php`.
**Что в коде:** файла нет. Phase 3 ушла в single-click `StartSessionAction` (callback `session:start` под `CanManageMiddleware`).
**Рекомендация:** удалить `StartSessionConversation.php` из списка.

#### A3. §3.4 «Nutgram routing» — aspirational маршруты без маркировки
**Что в spec'е:** пример routing-блока перечисляет:
```
recipe:order:{id}       → PlaceOrderAction
recipe:favorite:{id}    → FavoriteToggleAction
recipe:rate:{id}        → RateCocktailConversation::begin
order:accept:{id}       → AcceptOrderAction
order:cancel:{id}       → CancelOrderAction
session:end             → EndSessionAction
onPhoto                 → UploadPhotoAction
inventory:remove:{id}   → RemoveInventoryAction (реализован)
session:start           → StartSessionAction    (реализован)
```
**Что в коде:** реализованы только `recipe:show`, `recipe:browse`, `browse:back`, `cmd:search`, `cmd:ingredients`, `cmd:filter`, `inventory:show/add/remove`, `cmd:session`, `session:start`, `noop`. Остальные перечисленные в spec'е — фазы 3.1/4/5/6, ещё не существуют.
**Риск:** агент, ищущий точку расширения, может попытаться зарегистрировать дублирующий маршрут или решить, что фича уже работает.
**Рекомендация:** разделить блок на «Реализовано (Phase 1/2/3)» и «Запланировано (Phase 3.1+)», либо пометить второй блок комментарием `// PLANNED — not in routes/telegram.php yet`.

#### A4. §6 «HTTP API» — `darkaonline/l5-swagger` не установлен
**Что в spec'е:** «Swagger/OpenAPI через `darkaonline/l5-swagger`».
**Что в коде:** в `composer.json` пакета нет (проверено: `grep -E '"laravel|"php|"nutgram|spatie' composer.json` не показал l5-swagger; никакие swagger-конфиги не упоминаются).
**Рекомендация:** либо удалить упоминание до момента реальной установки, либо переформулировать как «планируется» с указанием фазы.

#### A5. §2 Тех. стек — `spatie/laravel-route-attributes`
**Что в spec'е:** перечислен как HTTP-роутинг.
**Что в коде:** пакет в `composer.json` есть (`^1.0`), но `routes/api.php` использует обычные `Route::*` декларации, атрибутов на Action'ах нет.
**Рекомендация:** уточнить в spec'е, что пакет подключён, но фактически не используется на данный момент — либо удалить упоминание, либо описать когда планируется применять.

---

### B. `.agents/specs/conversation-action-architecture.md`

**Расхождений не обнаружено.** Инвариант (`grep` 0 совпадений, `app/Telegram/Handlers/` отсутствует) держится. Чек-лист корректно учитывает оба случая.

---

### C. `.agents/specs/migration-conventions.md`

**Расхождений не обнаружено.** 16 миграций следуют шаблону `DB::unprepared(/** @lang PostgreSQL */ "...")`, типы колонок, naming constraints (`pk_`, `fk_`, `uq_`, `chk_`, `idx_`), ENUM-типы (`user_role_type`, `order_status_type`) — всё совпадает.

---

### D. `.agents/knowledge/codebase.md`

#### D1. §«Recipe» — устаревший комментарий о фабрике
**Что в knowledge:**
```
// HasFactory: НЕТ до Phase 2 (RecipeFactory создаётся в Task 1 Phase 2)
```
**Что в коде:** Phase 2 ✅ Готово (по таблице статусов в этом же файле); `database/factories/RecipeFactory.php` существует; используется в Unit-тестах (`Recipe::factory()->create(...)`, `Recipe::factory()->nonAlcoholic()->create()` — пример в §«Фабрики» того же `codebase.md`).
**Рекомендация:** заменить на `HasFactory: ДА. См. RecipeFactory с trait nonAlcoholic().` — устранить внутреннее противоречие.

#### D2. §«Статус реализации фаз» — гранулярность статуса
**Что в knowledge:** Phase 3.1, 4 — статус «⏳ Не начато».
**Что фактически:** обе фазы имеют design в `.agents/designs/` (`2026-05-26-phase3-1-orders-design.md`, `2026-06-01-phase4-favorites-ratings-design.md`). Phase 3.1 также имеет план (`2026-05-26-phase3-1-orders.md`). Код — действительно не начат.
**Рекомендация (по желанию):** ввести статус 🟡 «Дизайн готов / план готов» и переразметить таблицу. Если не нужна гранулярность — оставить как есть, формально не ошибка.

#### D3. §«Telegram-маршруты» — корректно, но напомнить про авторитет
**Замечание:** блок «Активные маршруты» в `codebase.md` 1:1 совпадает с `routes/telegram.php`. При следующих фазах документ нужно обновлять синхронно — это уже политика проекта, но стоит явно вынести: «при изменении `routes/telegram.php` обновить этот блок в финальной задаче фазы».

---

## Сводная таблица

| ID | Файл | Тип | Серьёзность | Действие |
|----|------|-----|-------------|----------|
| A1 | bar-bot-design.md §3.1 | Архитектурное расхождение | Средняя | Решить: добавить bus-flow или disclaimer |
| A2 | bar-bot-design.md §3.3 | Устаревший факт | Низкая | Удалить `StartSessionConversation` |
| A3 | bar-bot-design.md §3.4 | Aspirational без маркировки | Средняя | Разделить «реализовано / запланировано» |
| A4 | bar-bot-design.md §6 | Несуществующий пакет | Низкая | Удалить или пометить как planned |
| A5 | bar-bot-design.md §2 | Подключён, не используется | Низкая | Уточнить статус использования |
| D1 | codebase.md §«Recipe» | Устаревший комментарий | Низкая | Заменить «НЕТ до Phase 2» → «ДА» |
| D2 | codebase.md §«Статус фаз» | Гранулярность | Опциональная | Ввести 🟡 «Дизайн готов» или оставить |
| D3 | codebase.md §«Telegram-маршруты» | Политика обновления | Опциональная | Явно зафиксировать sync с routes |

---

## Что НЕ требует правок

- `conversation-action-architecture.md` — инвариант держится.
- `migration-conventions.md` — соответствует миграциям 1:1.
- AGENTS.md / CLAUDE.md — переработаны в этой же сессии (CLAUDE.md → @AGENTS.md import; Laravel 12 → 13 уже исправлено в AGENTS.md).

---

## План работы для следующей сессии

1. Прочитать этот файл целиком — даёт полный контекст, ходить по коду повторно не нужно (если со времени аудита не было новых merge в `master`).
2. Принять решения по 8 пунктам (A1–A5, D1–D3): применять / отложить / переформулировать.
3. Применить точечные правки в `.agents/specs/bar-bot-design.md` и `.agents/knowledge/codebase.md` отдельным коммитом (одна правка ≈ один edit, чтобы diff читался).
4. При принятии решения по A3 (aspirational routing) — синхронизировать с актуальным состоянием `routes/telegram.php` (`cat routes/telegram.php` достаточно — файл короткий).
5. Перед коммитом: `make pint-dirty-dry` не нужен (правим только `.md`), достаточно `git diff --stat`.
6. Коммит-сообщение: `docs: align specs/knowledge with codebase (audit 2026-06-01)`.

---

## Verification commands (если код успел измениться)

```bash
# Версии стека
grep -E '"laravel|"php|"nutgram|spatie' composer.json

# Фактические conversations
ls app/Telegram/Conversations/

# Фактические маршруты Telegram
cat routes/telegram.php

# Фактические фабрики
ls database/factories/

# Архитектурный инвариант (должен быть 0 совпадений)
grep -rn "app(.*Handler::class)" app/Telegram/

# Список существующих моделей (отсутствие Order/Favorite/Rating/RecipePhoto = Phase 3.1+ не реализованы)
ls app/Models/
```
