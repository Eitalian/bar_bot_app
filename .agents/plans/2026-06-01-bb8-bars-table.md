# BB-8: таблица `bars` + БД как источник правды — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: superpowers:executing-plans (исполняется в текущей сессии с TDD).
>
> **Before starting:** Read `.agents/knowledge/codebase.md`
> **After completing:** Update `.agents/knowledge/codebase.md` — добавить модель `Bar` как DB-backed (источник правды — таблица `bars`), новую таблицу + FK-проводку `bar_sessions`/`bar_inventory`, отметить перенос настроек бара из `config/bar.php` в БД.

**Goal:** В системе появляется таблица `bars` с одним сидированным баром; `Bar::default()` читает настройки бара из БД, а не из `config/bar.php`; `bar_sessions` и `bar_inventory` связаны с баром внешними ключами.

**Architecture:**
- Сущность бара переезжает из `config('bar.*')` в таблицу `bars`. `config/bar.php` сохраняет только `search.per_page` (это не про сущность бара).
- `Bar` остаётся **readonly value-object (POPO)** — `Bar::default()` гидрируется из единственной строки `bars` через query builder, маппит колонки в те же поля. Паттерн value-object и `new Bar(...)` в `BarScheduleTest` сохраняются (минимальный риск, без конвертации в Eloquent).
- Владелец бара: `bars.owner_id → users` `ON DELETE RESTRICT` (владельца нельзя удалить). Сидируется плейсхолдер-владелец (`telegram_id = 0`, role `owner`) внутри миграции, чтобы закрыть `NOT NULL`.
- Применяются новые конвенции: FK-индексы (мягкое правило — пропуск на крошечных таблицах с обоснованием в комментарии), CASCADE для composition / RESTRICT для ссылок на актора.

**Branch:** `feature/BB-8_bars-table`

---

## Карта файлов (REQUIRED)

### Новые файлы

| Файл | Ответственность |
|---|---|
| `database/migrations/2026_06_01_000001_create_bars_table.php` | CREATE `bars` + сид владельца (`users`, tg_id 0) + сид бара #1 |
| `database/migrations/2026_06_01_000002_alter_bar_sessions_add_bar_fk.php` | FK `bar_sessions.bar_id → bars(id)` CASCADE |
| `database/migrations/2026_06_01_000003_alter_bar_inventory_add_bar.php` | ADD `bar_inventory.bar_id` + FK CASCADE + UNIQUE `(bar_id, ingredient_id)` |
| `tests/Unit/Models/BarTest.php` *(перезапись)* | `Bar::default()` читает из БД, не из config |

### Изменяемые файлы

| Файл | Изменение |
|---|---|
| `app/Models/Bar.php` | `Bar::default()` гидрируется из строки `bars` вместо `config('bar.*')` |
| `config/bar.php` | Удалить `id/name/working_hours/open_cutoff_minutes`; оставить `search` |
| `.agents/knowledge/codebase.md` | Обновить раздел `Bar` + схему (финальная задача) |

### DDL-контракты (cross-boundary)

```
bars (
    id                  SMALLINT  GENERATED ALWAYS AS IDENTITY  PK
    owner_id            BIGINT    NOT NULL  FK→users(id) ON DELETE RESTRICT
    name                VARCHAR(255) NOT NULL
    work_start          TIME      NOT NULL
    work_end            TIME      NOT NULL          -- 06:00<12:00, без CHECK
    open_cutoff_minutes SMALLINT  NOT NULL DEFAULT 30
    created_at/updated_at TIMESTAMPTZ DEFAULT NOW()
)
bar_sessions:  + CONSTRAINT fk_bar_sessions_bar_id FK(bar_id)→bars(id) ON DELETE CASCADE
bar_inventory: + bar_id SMALLINT NOT NULL DEFAULT 1
               drop uq_bar_inventory_ingredient_id
               + fk_bar_inventory_bar_id FK(bar_id)→bars(id) ON DELETE CASCADE
               + uq_bar_inventory_bar_id_ingredient_id UNIQUE(bar_id, ingredient_id)
```
FK-индексы: `bars.owner_id` и `bar_sessions.bar_id` — пропуск (крошечные таблицы), обоснование в комментарии миграции. `bar_inventory.bar_id` — покрыт ведущей колонкой нового UNIQUE, отдельный индекс не нужен.

---

## Порядок исполнения (REQUIRED)

```
Группа 1 (последовательно): Task 1 — создаёт bars (от неё зависят FK)
Группа 2 (параллельно):     Tasks 2, 3 — независимы: разные таблицы (bar_sessions / bar_inventory), общий лишь предок bars из Task 1
Группа 3 (последовательно): Task 4 — рантайм-резолв Bar, требует таблицы bars из Task 1
Группа 4 (финал):           Task 5 — codebase.md + PR
```

При сольном исполнении группы 2 идут последовательно — порядок не критичен.

---

## Task 1: миграция `bars` + сид

**Depends on:** None

**Files:**
- Create: `database/migrations/2026_06_01_000001_create_bars_table.php`

- [ ] **Step 1:** CREATE TABLE `bars` по DDL-контракту выше (PK SMALLINT IDENTITY, FK `owner_id`→users RESTRICT, без CHECK на часы).
- [ ] **Step 2:** Сид: `INSERT users(telegram_id=0, first_name='Owner', role='owner')`, затем `INSERT bars(...)` со значениями из текущего `config/bar.php` (`'Полторушка'`, `12:00`, `06:00`, `30`), `owner_id` = id засиженного пользователя.
- [ ] **Step 3:** `down()`: DROP TABLE bars; удалить засиженного владельца (`telegram_id = 0`).
- [ ] **Step 4:** `make migration-fresh` → проходит без ошибок; `make db-q Q="SELECT * FROM bars"` → 1 строка.
- [ ] **Step 5: Commit** — `feat(bb8): add bars table with seeded owner and bar`

**Handoff:** миграции применяются с нуля; `bars` содержит 1 строку с валидным owner_id.

---

## Task 2: FK `bar_sessions.bar_id → bars`

**Depends on:** Task 1

**Files:**
- Create: `database/migrations/2026_06_01_000002_alter_bar_sessions_add_bar_fk.php`

- [ ] **Step 1:** ALTER ADD CONSTRAINT `fk_bar_sessions_bar_id` FK→bars CASCADE; комментарий про пропуск FK-индекса (крошечная таблица).
- [ ] **Step 2:** `down()`: DROP CONSTRAINT.
- [ ] **Step 3:** `make migration-fresh` → ок.
- [ ] **Step 4: Commit** — `feat(bb8): link bar_sessions to bars`

**Handoff:** FK существует; существующие тесты сессий зелёные.

---

## Task 3: привязка `bar_inventory` к бару

**Depends on:** Task 1

**Files:**
- Create: `database/migrations/2026_06_01_000003_alter_bar_inventory_add_bar.php`

- [ ] **Step 1:** ALTER: ADD `bar_id SMALLINT NOT NULL DEFAULT 1`; DROP `uq_bar_inventory_ingredient_id`; ADD FK→bars CASCADE; ADD UNIQUE `(bar_id, ingredient_id)`.
- [ ] **Step 2:** `down()`: обратные операции (вернуть UNIQUE(ingredient_id), убрать FK и колонку).
- [ ] **Step 3:** `make migration-fresh` → ок. Данных нет — миграция безопасна.
- [ ] **Step 4: Commit** — `feat(bb8): scope bar_inventory to bar`

**Handoff:** `bar_inventory` имеет `bar_id` + FK; `InventoryFactory`/`Inventory` тесты зелёные (модель не требует изменений — `bar_id` имеет DEFAULT).

---

## Task 4: `Bar::default()` из БД + config trim

**Depends on:** Task 1

**Files:**
- Modify: `app/Models/Bar.php`
- Modify: `config/bar.php`
- Create/Overwrite: `tests/Unit/Models/BarTest.php`

- [ ] **Step 1 (TDD):** Переписать `BarTest`: засидить строку `bars` (или опираться на сид миграции), `Bar::default()` возвращает POPO с полями из БД. Тест красный.
- [ ] **Step 2:** `Bar::default()` читает единственную строку `bars` через query builder, маппит `work_start/work_end/open_cutoff_minutes` в `workStart/workEnd/openCutoffMinutes`. POPO без изменений сигнатуры конструктора.
- [ ] **Step 3:** Удалить из `config/bar.php` ключи `id/name/working_hours/open_cutoff_minutes`; оставить `search`. Проверить grep `config('bar.` — остаётся только `bar.search.per_page` (FilterConversation, SearchRecipesAction).
- [ ] **Step 4:** `make tests` → весь suite зелёный (включая `BarScheduleTest`, использующий `new Bar(...)` — не затронут). `make pint-dirty-dry` → 0.
- [ ] **Step 5: Commit** — `feat(bb8): read bar settings from DB instead of config`

**Handoff:** `Bar::default()` источник — БД; config-настройки сущности удалены; suite зелёный.

---

## Task 5: Финал — codebase.md + PR

**Depends on:** Tasks 1–4

**Files:**
- Modify: `.agents/knowledge/codebase.md`

- [ ] **Step 1: Обновить codebase.md** — раздел `Bar`: теперь DB-backed value-object, источник правды — таблица `bars`; добавить `bars` в схему; отметить FK-проводку `bar_sessions`/`bar_inventory`; убрать упоминание `config('bar.*')` как источника настроек бара.
- [ ] **Step 2: Открыть PR** — `gh pr create --title "BB-8: bars table + DB-backed bar settings"` с `## Summary`.

**Handoff (финальный):** весь suite pass; pint-dirty-dry — 0; codebase.md обновлён; PR открыт.

---

## Связанные находки (вне BB-8)

Аудит схемы (FK-политика CASCADE→RESTRICT, FK-индексы) — см. `.agents/plans/2026-06-01-schema-fk-audit.md`. Доработка `migration-conventions.md` (#1 FK-индексы, #2 FK-секция, #3 VARCHAR-политика, #4 ссылка на неизменяемость, #5 полировка) — на паузе, возобновить после BB-8.
