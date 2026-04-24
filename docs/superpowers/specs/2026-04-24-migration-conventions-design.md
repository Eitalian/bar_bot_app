# Migration Conventions — Design

**Date:** 2026-04-24
**Project:** telegram-bar-bot

---

## Контекст

Проект использовал Laravel Blueprint/Schema builder для миграций. Принято решение перейти на чистый PostgreSQL SQL через `DB::unprepared()`. Причина: явный контроль над DDL, использование нативных PostgreSQL-возможностей (identity columns, ENUM types, TIMESTAMPTZ, UUID v7), лучшая читаемость для разработчика, знакомого с SQL.

---

## Решения

### Оболочка миграции

Все миграции используют `DB::unprepared()` с аннотацией `/** @lang PostgreSQL */` для IDE-подсветки:

```php
DB::unprepared(/** @lang PostgreSQL */ "
    CREATE TABLE ...
");
```

Оба метода — `up()` и `down()` — пишутся на чистом SQL.

---

### Первичные ключи

**Auto-increment:** `BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY`
- Именование constraint: `pk_{table}`
- Современный SQL-стандарт (SQL:2003), заменяет устаревший `SERIAL`
- Для явной вставки значения: `INSERT ... OVERRIDING SYSTEM VALUE VALUES (...)`

**UUID v7:** `uuid_generate_v7()` через расширение `pg_uuidv7`
- Используется когда: сущность экспортируется/импортируется (рецепты, ингредиенты)
- Инициализация расширения в первой миграции (000000)

---

### Типы колонок

| Laravel | PostgreSQL |
|---------|-----------|
| `string()` | `VARCHAR(255)` или `TEXT` |
| `text()` | `TEXT` |
| `integer()` | `INTEGER` |
| `bigInteger()` | `BIGINT` |
| `decimal(8,2)` | `NUMERIC(8,2)` |
| `boolean()` | `BOOLEAN` |
| `json()` | `JSONB` |
| `timestamp()` | `TIMESTAMPTZ` |
| `timestamps()` | `created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(), updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()` — `updated_at` обновляется Eloquent, не триггером |
| `enum()` | Нативный `CREATE TYPE ... AS ENUM (...)` |

---

### Именование constraint-ов

Формат: `{тип}_{таблица}_{колонка1}_{колонка2}`

| Тип | Префикс | Пример |
|-----|---------|--------|
| Primary key | `pk` | `pk_recipes` |
| Foreign key | `fk` | `fk_orders_recipe_id` |
| Unique | `uq` | `uq_bar_inventory_user_id_ingredient_id` |
| Check | `chk` | `chk_recipes_abv` |
| Index | `idx` | `idx_orders_session_id` |

---

### ENUM-типы

- Создаются как отдельные PostgreSQL-типы: `CREATE TYPE status_type AS ENUM (...)`
- Именование: `{domain}_type` (например, `order_status_type`)
- В `down()` удаляются после DROP TABLE: `DROP TYPE IF EXISTS {type_name}`

---

### CHECK-ограничения

Только для логических диапазонов и инвариантов, не для перечислений (их покрывает ENUM).

Примеры:
- `CONSTRAINT chk_recipes_abv CHECK (abv >= 0.00 AND abv <= 100.00)`
- `CONSTRAINT chk_bar_inventory_quantity CHECK (quantity >= 0)`

---

### Метод `down()` — порядок операций

```sql
-- 1. Сначала удалить таблицы в порядке, обратном зависимостям
DROP TABLE IF EXISTS {dependent_table};
DROP TABLE IF EXISTS {parent_table};

-- 2. Затем удалить ENUM-типы
DROP TYPE IF EXISTS {enum_type};
```

---

### Расширения PostgreSQL

`pg_uuidv7` инициализируется в самой первой миграции проекта (или отдельной `000000_create_extensions`):

```sql
CREATE EXTENSION IF NOT EXISTS pg_uuidv7;
```

---

## Артефакты

| Файл | Действие |
|------|---------|
| `.claude/specs/migration-conventions.md` | Создать — рабочий справочник по конвенциям |
| `CLAUDE.md` | Добавить ссылку на конвенции |
| `database/migrations/*.php` | Переписать 10 кастомных миграций (3 Laravel-дефолтных — users, cache, jobs — не трогаем) |
