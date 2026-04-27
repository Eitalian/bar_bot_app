# Migration Conventions — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Переписать все кастомные миграции на чистый PostgreSQL SQL и задокументировать конвенции в `.claude/specs/migration-conventions.md`.

**Architecture:** Все `up()` и `down()` методы используют `DB::unprepared(/** @lang PostgreSQL */ "...")`. Типы колонок — нативные PostgreSQL: UUID, TIMESTAMPTZ, NUMERIC, SMALLINT, JSONB, BIGINT GENERATED ALWAYS AS IDENTITY. ENUM реализуются через `CREATE TYPE`. FK columns, ссылающиеся на recipes.id и ingredients.id, меняют тип с VARCHAR на UUID.

**Tech Stack:** Laravel 12, PostgreSQL 18, расширение `pg_uuidv7`

---

> **Важно:** `recipes.id` и `ingredients.id` меняются с `VARCHAR(255)` на `UUID`. Все FK-колонки (`recipe_id`, `ingredient_id`) во всех таблицах тоже должны быть `UUID`. Запускать `make migration-fresh` нужно только после того, как переписаны ВСЕ миграции, иначе FK constraints будут несовместимы по типу.

---

## Файловая карта

| Действие | Файл |
|----------|------|
| Создать  | `.claude/specs/migration-conventions.md` |
| Изменить | `CLAUDE.md` — добавить ссылку на конвенции |
| Создать  | `database/migrations/2026_04_22_000000_create_extensions.php` |
| Переписать | `database/migrations/2026_04_22_000001_create_recipes_table.php` |
| Переписать | `database/migrations/2026_04_22_000002_create_ingredients_table.php` |
| Переписать | `database/migrations/2026_04_22_000003_create_recipe_ingredients_table.php` |
| Переписать | `database/migrations/2026_04_22_000004_create_recipe_tags_table.php` |
| Переписать | `database/migrations/2026_04_22_100001_create_bar_inventory_table.php` |
| Переписать | `database/migrations/2026_04_22_100002_create_bar_sessions_table.php` |
| Переписать | `database/migrations/2026_04_22_100003_create_orders_table.php` |
| Переписать | `database/migrations/2026_04_22_100004_create_favorites_table.php` |
| Переписать | `database/migrations/2026_04_22_100005_create_ratings_table.php` |
| Переписать | `database/migrations/2026_04_22_100006_create_recipe_photos_table.php` |

---

## Task 1: Conventions doc + CLAUDE.md

**Files:**
- Create: `.claude/specs/migration-conventions.md`
- Modify: `CLAUDE.md`

- [ ] **Step 1: Создать `.claude/specs/migration-conventions.md`**

```markdown
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
                id         BIGINT      GENERATED ALWAYS AS IDENTITY,
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
```

- [ ] **Step 2: Добавить ссылку в `CLAUDE.md`**

В секцию `## Code Style` добавить строку:

```
**Конвенции миграций:** `.claude/specs/migration-conventions.md`
```

- [ ] **Step 3: Commit**

```bash
git add .claude/specs/migration-conventions.md CLAUDE.md
git commit -m "docs(bb-3): migration conventions doc and CLAUDE.md reference"
```

---

## Task 2: Миграция расширений

**Files:**
- Create: `database/migrations/2026_04_22_000000_create_extensions.php`

- [ ] **Step 1: Создать файл**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(/** @lang PostgreSQL */ "
            CREATE EXTENSION IF NOT EXISTS pg_uuidv7;
        ");
    }

    public function down(): void
    {
        DB::unprepared(/** @lang PostgreSQL */ "
            DROP EXTENSION IF EXISTS pg_uuidv7;
        ");
    }
};
```

- [ ] **Step 2: Commit**

```bash
git add database/migrations/2026_04_22_000000_create_extensions.php
git commit -m "feat(bb-3): add pg_uuidv7 extension migration"
```

---

## Task 3: Миграции recipes + ingredients

**Files:**
- Modify: `database/migrations/2026_04_22_000001_create_recipes_table.php`
- Modify: `database/migrations/2026_04_22_000002_create_ingredients_table.php`

> `id` меняется с `VARCHAR(255)` на `UUID`. Все миграции с FK на эти таблицы переписываются в Tasks 4-6.

- [ ] **Step 1: Переписать `2026_04_22_000001_create_recipes_table.php`**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(/** @lang PostgreSQL */ "
            CREATE TABLE recipes (
                id           UUID         NOT NULL DEFAULT uuid_generate_v7(),
                name_ru      VARCHAR(255) NOT NULL,
                name_en      VARCHAR(255) NULL,
                description  TEXT         NULL,
                instructions TEXT         NULL,
                glass        VARCHAR(255) NULL,
                abv          NUMERIC(5,2) NULL,
                volume       INTEGER      NULL,
                icon         VARCHAR(255) NULL,
                photo        VARCHAR(255) NULL,
                taste_tags   JSONB        NULL,
                created_at   TIMESTAMPTZ  NOT NULL DEFAULT NOW(),
                updated_at   TIMESTAMPTZ  NOT NULL DEFAULT NOW(),
                CONSTRAINT pk_recipes PRIMARY KEY (id),
                CONSTRAINT chk_recipes_abv CHECK (abv IS NULL OR (abv >= 0.00 AND abv <= 100.00))
            );
        ");
    }

    public function down(): void
    {
        DB::unprepared(/** @lang PostgreSQL */ "
            DROP TABLE IF EXISTS recipes;
        ");
    }
};
```

- [ ] **Step 2: Переписать `2026_04_22_000002_create_ingredients_table.php`**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(/** @lang PostgreSQL */ "
            CREATE TABLE ingredients (
                id         UUID         NOT NULL DEFAULT uuid_generate_v7(),
                name_ru    VARCHAR(255) NULL,
                name_en    VARCHAR(255) NULL,
                category   VARCHAR(255) NULL,
                created_at TIMESTAMPTZ  NOT NULL DEFAULT NOW(),
                updated_at TIMESTAMPTZ  NOT NULL DEFAULT NOW(),
                CONSTRAINT pk_ingredients PRIMARY KEY (id)
            );
        ");
    }

    public function down(): void
    {
        DB::unprepared(/** @lang PostgreSQL */ "
            DROP TABLE IF EXISTS ingredients;
        ");
    }
};
```

- [ ] **Step 3: Commit**

```bash
git add database/migrations/2026_04_22_000001_create_recipes_table.php \
        database/migrations/2026_04_22_000002_create_ingredients_table.php
git commit -m "feat(bb-3): rewrite recipes and ingredients migrations to raw SQL"
```

---

## Task 4: Миграции recipe_ingredients + recipe_tags

**Files:**
- Modify: `database/migrations/2026_04_22_000003_create_recipe_ingredients_table.php`
- Modify: `database/migrations/2026_04_22_000004_create_recipe_tags_table.php`

- [ ] **Step 1: Переписать `2026_04_22_000003_create_recipe_ingredients_table.php`**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(/** @lang PostgreSQL */ "
            CREATE TABLE recipe_ingredients (
                id            BIGINT       GENERATED ALWAYS AS IDENTITY,
                recipe_id     UUID         NOT NULL,
                ingredient_id UUID         NOT NULL,
                amount        VARCHAR(255) NULL,
                unit          VARCHAR(255) NULL,
                note          VARCHAR(255) NULL,
                sort_order    INTEGER      NOT NULL DEFAULT 0,
                CONSTRAINT pk_recipe_ingredients        PRIMARY KEY (id),
                CONSTRAINT fk_recipe_ingredients_recipe_id
                    FOREIGN KEY (recipe_id)     REFERENCES recipes     (id) ON DELETE CASCADE,
                CONSTRAINT fk_recipe_ingredients_ingredient_id
                    FOREIGN KEY (ingredient_id) REFERENCES ingredients (id) ON DELETE CASCADE
            );
        ");
    }

    public function down(): void
    {
        DB::unprepared(/** @lang PostgreSQL */ "
            DROP TABLE IF EXISTS recipe_ingredients;
        ");
    }
};
```

- [ ] **Step 2: Переписать `2026_04_22_000004_create_recipe_tags_table.php`**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(/** @lang PostgreSQL */ "
            CREATE TABLE recipe_tags (
                id        BIGINT       GENERATED ALWAYS AS IDENTITY,
                recipe_id UUID         NOT NULL,
                tag       VARCHAR(255) NOT NULL,
                CONSTRAINT pk_recipe_tags PRIMARY KEY (id),
                CONSTRAINT fk_recipe_tags_recipe_id
                    FOREIGN KEY (recipe_id) REFERENCES recipes (id) ON DELETE CASCADE
            );
        ");
    }

    public function down(): void
    {
        DB::unprepared(/** @lang PostgreSQL */ "
            DROP TABLE IF EXISTS recipe_tags;
        ");
    }
};
```

- [ ] **Step 3: Commit**

```bash
git add database/migrations/2026_04_22_000003_create_recipe_ingredients_table.php \
        database/migrations/2026_04_22_000004_create_recipe_tags_table.php
git commit -m "feat(bb-3): rewrite recipe_ingredients and recipe_tags migrations to raw SQL"
```

---

## Task 5: Миграции bar_inventory + bar_sessions

**Files:**
- Modify: `database/migrations/2026_04_22_100001_create_bar_inventory_table.php`
- Modify: `database/migrations/2026_04_22_100002_create_bar_sessions_table.php`

- [ ] **Step 1: Переписать `2026_04_22_100001_create_bar_inventory_table.php`**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(/** @lang PostgreSQL */ "
            CREATE TABLE bar_inventory (
                id            BIGINT       GENERATED ALWAYS AS IDENTITY,
                user_id       BIGINT       NOT NULL,
                ingredient_id UUID         NOT NULL,
                quantity      NUMERIC(8,2) NULL,
                unit          VARCHAR(20)  NULL,
                created_at    TIMESTAMPTZ  NOT NULL DEFAULT NOW(),
                updated_at    TIMESTAMPTZ  NOT NULL DEFAULT NOW(),
                CONSTRAINT pk_bar_inventory PRIMARY KEY (id),
                CONSTRAINT fk_bar_inventory_user_id
                    FOREIGN KEY (user_id)       REFERENCES users       (id) ON DELETE CASCADE,
                CONSTRAINT fk_bar_inventory_ingredient_id
                    FOREIGN KEY (ingredient_id) REFERENCES ingredients (id) ON DELETE CASCADE,
                CONSTRAINT uq_bar_inventory_user_id_ingredient_id
                    UNIQUE (user_id, ingredient_id),
                CONSTRAINT chk_bar_inventory_quantity
                    CHECK (quantity IS NULL OR quantity >= 0)
            );
        ");
    }

    public function down(): void
    {
        DB::unprepared(/** @lang PostgreSQL */ "
            DROP TABLE IF EXISTS bar_inventory;
        ");
    }
};
```

- [ ] **Step 2: Переписать `2026_04_22_100002_create_bar_sessions_table.php`**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(/** @lang PostgreSQL */ "
            CREATE TABLE bar_sessions (
                id         BIGINT      GENERATED ALWAYS AS IDENTITY,
                started_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                ended_at   TIMESTAMPTZ NULL,
                created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                CONSTRAINT pk_bar_sessions PRIMARY KEY (id)
            );
        ");
    }

    public function down(): void
    {
        DB::unprepared(/** @lang PostgreSQL */ "
            DROP TABLE IF EXISTS bar_sessions;
        ");
    }
};
```

- [ ] **Step 3: Commit**

```bash
git add database/migrations/2026_04_22_100001_create_bar_inventory_table.php \
        database/migrations/2026_04_22_100002_create_bar_sessions_table.php
git commit -m "feat(bb-3): rewrite bar_inventory and bar_sessions migrations to raw SQL"
```

---

## Task 6: Миграция orders (с ENUM)

**Files:**
- Modify: `database/migrations/2026_04_22_100003_create_orders_table.php`

- [ ] **Step 1: Переписать `2026_04_22_100003_create_orders_table.php`**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(/** @lang PostgreSQL */ "
            CREATE TYPE order_status_type AS ENUM ('pending', 'accepted', 'cancelled');

            CREATE TABLE orders (
                id         BIGINT            GENERATED ALWAYS AS IDENTITY,
                session_id BIGINT            NOT NULL,
                user_id    BIGINT            NOT NULL,
                recipe_id  UUID              NOT NULL,
                quantity   SMALLINT          NULL,
                status     order_status_type NOT NULL DEFAULT 'pending',
                created_at TIMESTAMPTZ       NOT NULL DEFAULT NOW(),
                updated_at TIMESTAMPTZ       NOT NULL DEFAULT NOW(),
                CONSTRAINT pk_orders PRIMARY KEY (id),
                CONSTRAINT fk_orders_session_id
                    FOREIGN KEY (session_id) REFERENCES bar_sessions (id) ON DELETE CASCADE,
                CONSTRAINT fk_orders_user_id
                    FOREIGN KEY (user_id)    REFERENCES users        (id) ON DELETE CASCADE,
                CONSTRAINT fk_orders_recipe_id
                    FOREIGN KEY (recipe_id)  REFERENCES recipes      (id) ON DELETE CASCADE
            );
        ");
    }

    public function down(): void
    {
        DB::unprepared(/** @lang PostgreSQL */ "
            DROP TABLE IF EXISTS orders;
            DROP TYPE  IF EXISTS order_status_type;
        ");
    }
};
```

- [ ] **Step 2: Commit**

```bash
git add database/migrations/2026_04_22_100003_create_orders_table.php
git commit -m "feat(bb-3): rewrite orders migration to raw SQL with native ENUM type"
```

---

## Task 7: Миграции favorites + ratings + recipe_photos

**Files:**
- Modify: `database/migrations/2026_04_22_100004_create_favorites_table.php`
- Modify: `database/migrations/2026_04_22_100005_create_ratings_table.php`
- Modify: `database/migrations/2026_04_22_100006_create_recipe_photos_table.php`

- [ ] **Step 1: Переписать `2026_04_22_100004_create_favorites_table.php`**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(/** @lang PostgreSQL */ "
            CREATE TABLE favorites (
                user_id    BIGINT      NOT NULL,
                recipe_id  UUID        NOT NULL,
                created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                CONSTRAINT pk_favorites PRIMARY KEY (user_id, recipe_id),
                CONSTRAINT fk_favorites_user_id
                    FOREIGN KEY (user_id)   REFERENCES users   (id) ON DELETE CASCADE,
                CONSTRAINT fk_favorites_recipe_id
                    FOREIGN KEY (recipe_id) REFERENCES recipes (id) ON DELETE CASCADE
            );
        ");
    }

    public function down(): void
    {
        DB::unprepared(/** @lang PostgreSQL */ "
            DROP TABLE IF EXISTS favorites;
        ");
    }
};
```

- [ ] **Step 2: Переписать `2026_04_22_100005_create_ratings_table.php`**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(/** @lang PostgreSQL */ "
            CREATE TABLE ratings (
                user_id    BIGINT      NOT NULL,
                recipe_id  UUID        NOT NULL,
                score      SMALLINT    NOT NULL,
                created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                CONSTRAINT pk_ratings PRIMARY KEY (user_id, recipe_id),
                CONSTRAINT fk_ratings_user_id
                    FOREIGN KEY (user_id)   REFERENCES users   (id) ON DELETE CASCADE,
                CONSTRAINT fk_ratings_recipe_id
                    FOREIGN KEY (recipe_id) REFERENCES recipes (id) ON DELETE CASCADE
            );
        ");
    }

    public function down(): void
    {
        DB::unprepared(/** @lang PostgreSQL */ "
            DROP TABLE IF EXISTS ratings;
        ");
    }
};
```

- [ ] **Step 3: Переписать `2026_04_22_100006_create_recipe_photos_table.php`**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(/** @lang PostgreSQL */ "
            CREATE TABLE recipe_photos (
                id               BIGINT      GENERATED ALWAYS AS IDENTITY,
                recipe_id        UUID        NOT NULL,
                user_id          BIGINT      NOT NULL,
                telegram_file_id TEXT        NOT NULL,
                created_at       TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                updated_at       TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                CONSTRAINT pk_recipe_photos PRIMARY KEY (id),
                CONSTRAINT fk_recipe_photos_recipe_id
                    FOREIGN KEY (recipe_id) REFERENCES recipes (id) ON DELETE CASCADE,
                CONSTRAINT fk_recipe_photos_user_id
                    FOREIGN KEY (user_id)   REFERENCES users   (id) ON DELETE CASCADE
            );
        ");
    }

    public function down(): void
    {
        DB::unprepared(/** @lang PostgreSQL */ "
            DROP TABLE IF EXISTS recipe_photos;
        ");
    }
};
```

- [ ] **Step 4: Commit**

```bash
git add database/migrations/2026_04_22_100004_create_favorites_table.php \
        database/migrations/2026_04_22_100005_create_ratings_table.php \
        database/migrations/2026_04_22_100006_create_recipe_photos_table.php
git commit -m "feat(bb-3): rewrite favorites, ratings, recipe_photos migrations to raw SQL"
```

---

## Task 8: Верификация

**Files:** нет (только команды)

- [ ] **Step 1: Сбросить и накатить все миграции**

```bash
make migration-fresh
```

Ожидаемый вывод: все `RUNNING` → `DONE` без ошибок.

- [ ] **Step 2: Проверить статус миграций**

```bash
docker compose exec app php artisan migrate:status
```

Ожидаемый вывод: все миграции со статусом `Ran`.

- [ ] **Step 3: Проверить структуру ключевых таблиц**

```bash
docker compose exec db psql -U postgres -d bar_bot -c "\d recipes"
docker compose exec db psql -U postgres -d bar_bot -c "\d orders"
docker compose exec db psql -U postgres -d bar_bot -c "\d bar_inventory"
```

Проверить что:
- `recipes.id` имеет тип `uuid`
- `orders.status` имеет тип `order_status_type`
- `bar_inventory` имеет constraint `uq_bar_inventory_user_id_ingredient_id`

> Если имя БД отличается — посмотреть в `.env` переменную `DB_DATABASE`.

- [ ] **Step 4: Проверить что импорт рецептов всё ещё работает**

```bash
docker compose exec app php artisan bar:import
```

Ожидаемый вывод: рецепты импортированы без ошибок.

> Если ошибка типа "invalid input syntax for type uuid" — значит в JSON есть не-UUID строки в поле id. В этом случае тип `id` в recipes/ingredients нужно вернуть к `TEXT`.
