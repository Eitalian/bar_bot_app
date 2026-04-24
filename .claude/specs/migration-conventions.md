# Migration Conventions

Все DDL-миграции пишутся на чистом PostgreSQL SQL через `DB::unprepared()`.

---

## Шаблон миграции

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(/** @lang PostgreSQL */ "
            CREATE TABLE example (
                id         BIGINT       GENERATED ALWAYS AS IDENTITY,
                name       VARCHAR(255) NOT NULL,
                created_at TIMESTAMPTZ  NOT NULL DEFAULT NOW(),
                updated_at TIMESTAMPTZ  NOT NULL DEFAULT NOW(),
                CONSTRAINT pk_example PRIMARY KEY (id)
            );
        ");
    }

    public function down(): void
    {
        DB::unprepared(/** @lang PostgreSQL */ "
            DROP TABLE IF EXISTS example;
        ");
    }
};
```

---

## Первичные ключи

### Auto-increment
```sql
id BIGINT GENERATED ALWAYS AS IDENTITY,
CONSTRAINT pk_{table} PRIMARY KEY (id)
```
Заменяет устаревший `SERIAL`. Для явной вставки значения: `INSERT ... OVERRIDING SYSTEM VALUE VALUES (...)`.

### UUID v7
```sql
id UUID NOT NULL DEFAULT uuid_generate_v7(),
CONSTRAINT pk_{table} PRIMARY KEY (id)
```
Использовать для сущностей, которые экспортируются/импортируются (recipes, ingredients).
Требует расширение `pg_uuidv7` (инициализируется в миграции `000000_create_extensions`).

### Составной PK (без суррогатного ключа)
```sql
CONSTRAINT pk_{table} PRIMARY KEY (col1, col2)
```

---

## Типы колонок

| Назначение | Тип PostgreSQL |
|-----------|----------------|
| Короткая строка | `VARCHAR(255)` |
| Длинный текст | `TEXT` |
| Целое | `INTEGER` |
| Большое целое / FK | `BIGINT` |
| UUID | `UUID` |
| Дробное | `NUMERIC(p, s)` |
| Маленькое целое | `SMALLINT` |
| Булево | `BOOLEAN` |
| JSON | `JSONB` |
| Дата-время | `TIMESTAMPTZ` |
| Перечисление | Нативный ENUM-тип (см. ниже) |

---

## Именование constraints

Формат: `{тип}_{таблица}_{колонка1}_{колонка2}`

| Тип | Префикс | Пример |
|-----|---------|--------|
| Primary key | `pk` | `pk_recipes` |
| Foreign key | `fk` | `fk_orders_recipe_id` |
| Unique | `uq` | `uq_bar_inventory_user_id_ingredient_id` |
| Check | `chk` | `chk_recipes_abv` |
| Index | `idx` | `idx_orders_session_id` (CREATE INDEX) |

---

## ENUM-типы

Создать тип перед таблицей, удалить после неё в `down()`:

```sql
-- up()
CREATE TYPE order_status_type AS ENUM ('pending', 'accepted', 'cancelled');
CREATE TABLE orders (
    ...
    status order_status_type NOT NULL DEFAULT 'pending',
    ...
);

-- down()
DROP TABLE IF EXISTS orders;
DROP TYPE IF EXISTS order_status_type;
```

Именование: `{domain}_type` — например `order_status_type`.

---

## CHECK-ограничения

Только для логических диапазонов и инвариантов (не для ENUM — те самовалидируются):

```sql
CONSTRAINT chk_recipes_abv CHECK (abv IS NULL OR (abv >= 0.00 AND abv <= 100.00))
CONSTRAINT chk_bar_inventory_quantity CHECK (quantity IS NULL OR quantity >= 0)
```

---

## Порядок в `down()`

```sql
-- 1. Таблицы в порядке, обратном зависимостям (зависимые — первыми)
DROP TABLE IF EXISTS dependent_table;
DROP TABLE IF EXISTS parent_table;

-- 2. ENUM-типы — после всех таблиц
DROP TYPE IF EXISTS some_enum_type;
```

---

## Временны́е метки

```sql
created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
```

`updated_at` обновляется Eloquent на уровне приложения, не через DB-триггер.
