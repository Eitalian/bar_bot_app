# bb9: Аудит и нормализация схемы БД — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.
>
> **Before starting:** Read `.agents/knowledge/codebase.md` и `.agents/specs/migration-conventions.md`.
> **After completing:** Update `.agents/knowledge/codebase.md` — добавить результаты аудита: обоснования типов, найденные аномалии, применённые исправления.

**Goal:** Каждая колонка каждой таблицы имеет обоснованный тип; FK-индексы присутствуют; CHECK-ограничения расставлены где применимо; аномалии устранены ALTER-миграциями.

**Branch:** `feature/bb9_schema-audit`

---

## Карта файлов

### Новые файлы

| Файл | Ответственность |
|---|---|
| `database/migrations/2026_06_XX_000001_schema_audit_fixes.php` | ALTER-миграции по результатам аудита (если нужны) |

### Изменяемые файлы

| Файл | Изменение |
|---|---|
| `.agents/knowledge/codebase.md` | Результаты аудита, обоснования типов |
| `.agents/specs/migration-conventions.md` | Новые правила если выявлены |

---

## Порядок исполнения

```
Группа 1 (последовательно): Task 1 — аудит (read-only исследование)
Группа 2 (последовательно): Task 2 — исправления (зависит от Task 1)
Группа 3 (финал):           Task 3 — codebase.md + PR
```

---

## Критерии handoff между задачами

См. **«Handoff-чеклист задачи»** в `CLAUDE.md`.

---

## Task 1: Аудит схемы — исследование всех таблиц

**Depends on:** None

**Files:**
- (read-only, изменений нет)

- [ ] **Step 1: Получить полную схему всех таблиц**

```bash
make db-q Q="SELECT table_name, column_name, data_type, character_maximum_length, is_nullable, column_default
             FROM information_schema.columns
             WHERE table_schema = 'public'
             ORDER BY table_name, ordinal_position"
```

- [ ] **Step 2: Проверить FK-индексы**

```bash
make db-q Q="SELECT tc.table_name, kcu.column_name
             FROM information_schema.table_constraints tc
             JOIN information_schema.key_column_usage kcu
               ON tc.constraint_name = kcu.constraint_name
             WHERE tc.constraint_type = 'FOREIGN KEY'
             ORDER BY tc.table_name"
```

Сверить каждую FK-колонку с `pg_indexes` — есть ли индекс.

- [ ] **Step 3: Проверить CHECK-ограничения**

```bash
make db-q Q="SELECT tc.table_name, tc.constraint_name, cc.check_clause
             FROM information_schema.table_constraints tc
             JOIN information_schema.check_constraints cc
               ON tc.constraint_name = cc.constraint_name
             WHERE tc.constraint_type = 'CHECK'
             ORDER BY tc.table_name"
```

- [ ] **Step 4: Анализ по таблицам**

Для каждой таблицы (`recipes`, `users`, `bar_sessions`, `orders`, `bar_inventory`, `ingredients`, `recipe_ingredients`, `recipe_tags`) зафиксировать:

- Обоснование размера PK и каждой FK-колонки
- Строковые колонки: `TEXT` vs `VARCHAR(N)` — обоснование
- Числовые колонки: `BIGINT` vs `INTEGER` vs `SMALLINT` — обоснование
- Отсутствующие индексы на FK
- Отсутствующие CHECK-ограничения

**Конкретные вопросы для проверки:**

1. `recipes.id` — `TEXT`, но фактически max 36 символов (slugs + UUID-строки). Рассмотреть `VARCHAR(64)`
2. `users.telegram_id` — какой тип? Telegram user ID может достигать 2^52, нужен `BIGINT`
3. `orders.session_id` — уже `SMALLINT` (после Phase 3.1), проверить что FK и индекс на месте
4. Все строковые колонки без явной длины — обоснование `TEXT` vs `VARCHAR`

- [ ] **Step 5: Составить список исправлений**

По результатам анализа: перечень ALTER-команд которые нужны. Если исправлений нет — зафиксировать что схема корректна.

**Handoff:** список всех таблиц проанализирован; список исправлений (или «исправлений нет») передан в Task 2.

---

## Task 2: Исправления — ALTER-миграции

**Depends on:** Task 1

**Files:**
- Create: `database/migrations/2026_06_XX_000001_schema_audit_fixes.php`

> Если Task 1 не выявил исправлений — этот Task пропускается, сразу Task 3.

- [ ] **Step 1: Написать ALTER-миграцию**

По списку из Task 1. Каждое изменение снабдить комментарием-обоснованием прямо в SQL.

- [ ] **Step 2: Прогнать миграцию**

```bash
make migration-run
```

- [ ] **Step 3: Прогнать тесты**

```bash
make tests
```

Ожидаемо: весь suite зелёный.

- [ ] **Step 4: Pint + commit**

```bash
make pint-dirty
git add database/migrations/...
git commit --author="Claude <claude@anthropic.com>" -m "feat(bb-9): schema audit — ALTER fixes"
```

**Handoff:** миграция применена; тесты pass; pint — 0.

---

## Task 3 (Финал): codebase.md + PR

**Depends on:** Task 1 (и Task 2 если выполнялся)

**Files:**
- Modify: `.agents/knowledge/codebase.md`
- Modify: `.agents/specs/migration-conventions.md` (если выявлены новые правила)

- [ ] **Step 1: Обновить codebase.md**

Добавить раздел «Схема БД — обоснования типов» с результатами аудита.

- [ ] **Step 2: Обновить migration-conventions.md**

Если аудит выявил правила которые стоит зафиксировать (например «строковые FK используют тот же тип что и PK таблицы-цели»).

- [ ] **Step 3: Финальный прогон**

```bash
make pint-dirty-dry
make tests
```

- [ ] **Step 4: Открыть PR**

```bash
gh pr create \
  --title "bb9: schema audit — column types and FK indexes normalized" \
  --body "$(cat <<'EOF'
## Summary
- Проверены типы колонок всех таблиц, обоснования зафиксированы в codebase.md
- FK-индексы проверены и при необходимости добавлены
- CHECK-ограничения проверены
- migration-conventions.md обновлён новыми правилами (если выявлены)
EOF
)"
```

**Handoff (финальный):** весь suite pass; pint — 0; codebase.md обновлён; PR открыт.

---

## Pre-PR проверка автора плана

- [x] Секция **Goal** заполнена.
- [x] Секция **Branch** содержит конкретное имя: `feature/bb9_schema-audit`.
- [x] **Карта файлов** перечисляет все файлы из Steps.
- [x] **Порядок исполнения** присутствует.
- [x] У каждой задачи есть **Depends on**.
- [x] У каждой задачи есть секция **Files**.
- [x] Финальная задача содержит «Обновить codebase.md» и «Открыть PR».
