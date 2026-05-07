# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project

Telegram-бот для управления баром: инвентарь ингредиентов, поиск коктейлей, бар-сессии, заказы от гостей барменю. Laravel 12 + Octane (RoadRunner) + Nutgram + PostgreSQL 18, Docker Compose.

**Полный дизайн:** `.agents/specs/bar-bot-design.md`

**Знания о кодовой базе (статус фаз, паттерны с примерами, известные особенности):** `.agents/knowledge/codebase.md` — читай перед началом задачи; политика обновления — см. раздел «Агентская разработка».

**Планы разработки:** `.agents/plans/`

## Commands

All commands run inside Docker via Makefile targets. Direct artisan calls: `docker compose exec app php artisan <cmd>`.

```bash
# First-time setup
make docker-init-project

# Start/rebuild
make docker-up
make docker-rebuild

# Migrations
make migration-run
make migration-fresh        # drops and recreates all tables

# Import cocktail recipes from data/recipes_final.json
docker compose exec app php artisan bar:import

# Bot webhook
make bot-webhook-set        # set to hardcoded ngrok URL (update in Makefile)
make bot-hook-info
make bot-webhook-delete

# Linting (Pint)
make pint-dirty             # fix only changed files
make pint-dirty-dry         # check without fixing

# Tests (Pest)
make tests
make tests-feature
make tests-unit

# Cache
make cache-clear
make cache-warm

# Logs
docker compose logs -f app
```

Run a single test: `docker compose exec app php artisan test --filter=TestName`

## Architecture

**Request flow**: ngrok → `TelegramController` → Nutgram → `routes/telegram.php` → Handler or Conversation → Eloquent models

**`routes/telegram.php`** is the bot routing file (analogous to `routes/web.php` for HTTP). Register handlers and conversations here. Currently most handlers are commented out — only a basic `/start` closure is active.

**Handlers** (`app/Telegram/Handlers/`) are simple invokable classes for stateless responses (e.g., showing a recipe card on callback query). Route pattern: `$bot->onCallbackQueryData('recipe:show:{id}', RecipeHandler::class)`.

**Conversations** (`app/Telegram/Conversations/`) extend Nutgram's `Conversation` class for multi-step flows. State is stored as protected class properties that Nutgram serializes between steps. Each step calls `$this->next('methodName')` to queue the next handler; `$this->end()` terminates the conversation.

**Models**: `Recipe` (string UUID primary key, non-incrementing), `Ingredient`, `RecipeIngredient` (pivot with amount/unit/note/sort_order), `RecipeTag`. `Recipe::toTelegramMessage()` formats a recipe card for Telegram Markdown.

**Data import**: `bar:import` reads `data/recipes_final.json` (MyBar export format), skips UUID-format ingredient IDs and UUID tags, upserts recipes via `updateOrCreate`.

**Runtime**: Laravel Octane with RoadRunner (persistent PHP process). App is served on port 8080 internally, exposed as port 88 externally. The `--watch` flag is enabled in compose for local development.

**ngrok** container proxies `app:8080` and exposes the dashboard at `http://localhost:4040`. Get the public URL: `curl http://localhost:4040/api/tunnels | python3 -m json.tool` → look for `public_url`.

## Code Style

Pint with `"preset": "per"`. Excluded: `database/`, `config/`, `bootstrap/`. Trailing commas in multiline arrays/arguments/parameters.

**Конвенции миграций:** `.agents/specs/migration-conventions.md`

## Git Workflow

Работа ведётся в ветках, Claude создаёт PR — разработчик мержит вручную на GitHub.

**Ветки:**
- Задача: `feature/bb{N}_{slug}` — e.g. `feature/bb4_bar-session-flow`
- Подзадача: `feature/bb{N}-s{M}_{slug}` — e.g. `feature/bb4-s1_start-conversation`
- `slug` — kebab-case, до 4 слов

**PR:**
- Заголовок: `bb{N}: описание` или `bb{N}-s{M}: описание`
- Тело: только `## Summary` с bullet points по сделанному

**Порядок работы:**
1. Создать ветку перед началом задачи
2. Коммиты на ветке: `type(bb-N): description`
3. По завершении открыть PR через `gh pr create`
4. Мерж — вручную разработчиком

## Агентская разработка

### Параллельные задачи в планах

Каждый план в `.agents/plans/` обязан содержать раздел **"Порядок исполнения"** сразу после карты файлов.
Формат:

```
## Порядок исполнения

Группа 1 (последовательно): Task 1
Группа 2 (параллельно):     Tasks 2, 3, 4 — независимы: разные неймспейсы, нет общих файлов
Группа 3 (последовательно): Task 5 — требует артефактов из Group 2
Группа 4 (параллельно):     Tasks 6, 7 — независимы: разные HTTP-эндпоинты
Группа 5 (финал):           Task 8 — интеграция + обновление codebase.md + PR
```

Для каждой задачи указывать `Depends on: Task N` или `None`.
Для параллельных групп — однострочное обоснование независимости (разные файлы / нет зависимости по схеме БД).

### Политика Advisor

Advisor и Reviewer работают в разных направлениях:
- **Reviewer** (после каждой задачи): "соответствует ли код плану и стандартам?" — backward-looking
- **Advisor** (в ключевых точках): "правильна ли архитектура, какие риски впереди?" — forward-looking

**Вызывать Advisor:**
1. Перед стартом фазы — проверить план в целом на баги и риски
2. При отклонении задачи от плана в части архитектуры или публичного контракта
3. Перед интеграцией параллельных потоков (Reviewer видит одну задачу; Advisor видит систему)
4. Перед объявлением фазы завершённой

**Не вызывать Advisor** после каждой задачи — это роль Reviewer.

### Политика обновления codebase.md

**Обновляет только агент финальной задачи фазы**, когда в плане явно стоит шаг `- [ ] Обновить codebase.md`.

Субагенты промежуточных задач файл **не трогают** — новые паттерны и находки передают текстом в своём отчёте агенту финальной задачи.

Это устраняет риск параллельной записи и «преждевременных» обновлений.

### Handoff-чеклист задачи

Перед передачей управления каждый агент обязан выполнить:

```
1. docker compose exec app php artisan test  →  все тесты PASS, регрессий нет
2. make pint-dirty-dry  →  0 изменений
3. git status  →  нет незакоммиченных/неотслеживаемых файлов
4. Все файлы задачи из секции "Files" существуют
5. В отчёте: список изменённых файлов + краткое описание сделанного
6. git diff HEAD~{N}..HEAD --name-only  →  файлы ВНЕ секции "Files" объяснены в отчёте
```

**Правило пункта 6:** файл вне секции "Files" — не автоматический стоп, но требует явного обоснования (ссылка на Step плана, где это предусмотрено). Файл без обоснования — reviewer отклоняет.

**Следствие для авторов планов:** секция "Files" обязана перечислять **все** файлы, упомянутые в Steps — включая ancillary (`codebase.md`, дополнения к `routes/*.php` и т.д.).

---

## Planned Features

`taste_tags` column on `recipes` (JSON array) is reserved for "вкусоматика" — taste profile tagging (`sweet`, `sour`, `bitter`, `fruity`, `smoky`, `herbal`, `spicy`, `creamy`). Fill command: `bar:taste:fill` (not yet implemented).
