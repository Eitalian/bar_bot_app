# Phase 4: Избранное и оценки — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.
>
> **Before starting:** Read `.agents/knowledge/codebase.md`
> **After completing:** Update `.agents/knowledge/codebase.md` — статус Phase 4 → ✅, новые модели/хэндлеры/экшны, новые маршруты, паттерн смешанного ввода в Conversation.

**Goal:** Пользователи могут добавлять рецепты в избранное, выставлять оценку 1–5, видеть среднюю оценку на карточке и листать свой список избранного через `/favorites`.

**Architecture:**
- Таблицы `favorites` и `ratings` уже существуют (scaffold апреля); Task 1 — ALTER-миграция для приведения схемы к дизайну (суррогатный `id`, снятие составного PK, UNIQUE-ограничение)
- Handlers — чистая логика без транспорта; Actions — HTTP + Telegram в одном классе (паттерн проекта)
- `GetRecipeAction` расширяется: загружает контекст (isFavorite, userRating, avg/count) одним запросом, добавляет ряды кнопок
- `SearchResultsResponse` принимает `favoritedIds` — коллекцию recipe_id избранного текущего пользователя
- Callback-конвенция приведена к `recipe:{id}:action` (rename существующих `recipe:show:{id}` и `recipe:order:{id}`)

**Branch:** `feature/BB-11_favorites-ratings`

---

## Карта файлов

### Новые файлы

| Файл | Ответственность |
|---|---|
| `database/migrations/2026_06_02_000003_alter_favorites_ratings_schema.php` | ALTER: surrogate id, UNIQUE, drop updated_at from favorites |
| `app/Models/Favorite.php` | Eloquent-модель избранного |
| `app/Models/Rating.php` | Eloquent-модель оценок |
| `database/factories/FavoriteFactory.php` | Фабрика для тестов |
| `database/factories/RatingFactory.php` | Фабрика для тестов |
| `app/Handlers/Favorites/FavoriteToggleHandler.php` | Toggle избранного, возвращает bool |
| `app/Handlers/Ratings/RateRecipeHandler.php` | Upsert оценки, возвращает Rating |
| `app/Handlers/Favorites/ListFavoritesHandler.php` | Список избранного с сортировкой |
| `app/Actions/Favorites/FavoriteToggleAction.php` | HTTP + Telegram toggle |
| `app/Actions/Ratings/RateAction.php` | HTTP + Telegram upsert оценки |
| `app/Actions/Ratings/ShowRatingPickerAction.php` | Telegram-only: показать ряд ⭐1–5 |
| `app/Actions/Favorites/ListFavoritesAction.php` | HTTP + Telegram список избранного |
| `app/Telegram/Conversations/ListFavoritesConversation.php` | Conversation для просмотра избранного |
| `tests/Unit/Handlers/Favorites/FavoriteToggleHandlerTest.php` | Unit тест toggle |
| `tests/Unit/Handlers/Ratings/RateRecipeHandlerTest.php` | Unit тест upsert оценки |
| `tests/Unit/Handlers/Favorites/ListFavoritesHandlerTest.php` | Unit тест списка |
| `tests/Feature/Actions/Favorites/FavoriteToggleActionTest.php` | Feature тест HTTP toggle |
| `tests/Feature/Actions/Ratings/RateActionTest.php` | Feature тест HTTP rate |
| `tests/Feature/Actions/Favorites/ListFavoritesActionTest.php` | Feature тест HTTP список |
| `tests/Feature/PhaseFourFlowTest.php` | E2E: toggle→rate→upsert→unfavorite |

### Изменяемые файлы

| Файл | Изменение |
|---|---|
| `app/Actions/GetRecipeAction.php` | Загрузка favorites/ratings контекста, новые ряды кнопок, rename callback recipe:show→recipe:{id}:show |
| `app/Actions/BrowseRecipesAction.php` | Rename callback recipe:show→recipe:{id}:show, recipe:order→recipe:{id}:order |
| `app/Telegram/Responses/SearchResultsResponse.php` | Добавить favoritedIds + fav-столбец в текст, rename recipe:show→recipe:{id}:show |
| `app/Actions/Search/SearchRecipesAction.php` | Передать favoritedIds в SearchResultsResponse |
| `app/Actions/Search/SearchByIngredientAction.php` | Передать favoritedIds в SearchResultsResponse |
| `routes/telegram.php` | Новые маршруты + rename recipe:show / recipe:order |
| `routes/api.php` | Три новых HTTP-маршрута |
| `.agents/knowledge/codebase.md` | Статус Phase 4, новые паттерны |
| `.agents/knowledge/telegram-ui.md` | Экраны карточки рецепта, списка избранного |

---

## Порядок исполнения

```
Группа 1 (последовательно): Task 1 — ALTER-миграция (основа схемы)
Группа 2 (последовательно): Task 2 — Модели (Favorite, Rating, фабрики)
Группа 3 (параллельно):     Tasks 3, 4, 5 — Handlers независимы: разные неймспейсы Favorites/Ratings, нет общих файлов
Группа 4 (параллельно):     Tasks 6, 7, 8 — Actions независимы: каждый зависит только от своего Handler
Группа 5 (параллельно):     Tasks 9, 10 — GetRecipeAction и (SearchResultsResponse+Routes) независимы: разные файлы
Группа 6 (последовательно): Task 11 — E2E тесты (требуют полного роутинга из Task 10)
Группа 7 (финал):           Task 12 — codebase.md + telegram-ui.md + PR
```

---

## Критерии handoff между задачами

См. **«Handoff-чеклист задачи»** в `AGENTS.md`. Пункт 6: файл вне «Files» — требует обоснования в отчёте.

---

## Task 1: ALTER-миграция — привести favorites/ratings к дизайну

**Depends on:** None

**Files:**
- Create: `database/migrations/2026_06_02_000003_alter_favorites_ratings_schema.php`

- [ ] **Step 1: Проверить текущее состояние схемы**

  ```bash
  make db-q Q="SELECT conname, contype FROM pg_constraint WHERE conrelid IN ('favorites'::regclass,'ratings'::regclass) ORDER BY conrelid, contype"
  ```

  Ожидаем: `pk_favorites` (p), `pk_ratings` (p), `fk_favorites_user_id` (f), `fk_favorites_recipe_id` (f), `fk_ratings_user_id` (f), `fk_ratings_recipe_id` (f), `chk_ratings_score` (c). Индексы `idx_favorites_recipe_id` и `idx_ratings_recipe_id` уже есть (BB-9) — не пересоздавать.

- [ ] **Step 2: Создать ALTER-миграцию**

  Файл: `database/migrations/2026_06_02_000003_alter_favorites_ratings_schema.php`

  Операции для `favorites`:
  - `DROP CONSTRAINT pk_favorites` (составной PK)
  - `ADD COLUMN id INTEGER GENERATED ALWAYS AS IDENTITY`
  - `ADD CONSTRAINT pk_favorites PRIMARY KEY (id)`
  - `ADD CONSTRAINT uq_favorites_user_recipe UNIQUE (user_id, recipe_id)`
  - `DROP COLUMN updated_at` (дизайн: $timestamps=false, только created_at)

  Операции для `ratings`:
  - `DROP CONSTRAINT pk_ratings` (составной PK)
  - `ADD COLUMN id INTEGER GENERATED ALWAYS AS IDENTITY`
  - `ADD CONSTRAINT pk_ratings PRIMARY KEY (id)`
  - `ADD CONSTRAINT uq_ratings_user_recipe UNIQUE (user_id, recipe_id)`
  - (chk_ratings_score и idx_ratings_recipe_id уже добавлены BB-9 — не трогать)

  `down()`: обратные ALTER + DROP pk_new + ADD pk_old.

- [ ] **Step 3: Применить миграцию**

  ```bash
  make migration-run
  ```

  Проверить:
  ```bash
  make db-q Q="SELECT column_name, data_type FROM information_schema.columns WHERE table_name IN ('favorites','ratings') ORDER BY table_name, ordinal_position"
  ```
  Ожидаем: id, user_id, recipe_id, created_at в favorites (без updated_at); id, user_id, recipe_id, score, created_at, updated_at в ratings.

- [ ] **Step 4: Commit**

  ```bash
  git checkout -b feature/BB-11_favorites-ratings
  git add database/migrations/2026_06_02_000003_alter_favorites_ratings_schema.php
  git commit --author="Claude <claude@anthropic.com>" -m "feat(BB-11): alter favorites/ratings — surrogate id, UNIQUE, drop favorites.updated_at"
  ```

**Handoff:** `make migration-run` — OK, колонки соответствуют дизайну.

---

## Task 2: Модели Favorite и Rating + фабрики

**Depends on:** Task 1

**Files:**
- Create: `app/Models/Favorite.php`
- Create: `app/Models/Rating.php`
- Create: `database/factories/FavoriteFactory.php`
- Create: `database/factories/RatingFactory.php`

- [ ] **Step 1: Создать `app/Models/Favorite.php`**

  ```php
  // $incrementing = true (суррогатный id)
  // $timestamps = false (only created_at, managed by DB DEFAULT)
  // CREATED_AT = 'created_at'; UPDATED_AT = null
  // $fillable = ['user_id', 'recipe_id']
  // Relations: user() BelongsTo User, recipe() BelongsTo Recipe
  ```

- [ ] **Step 2: Создать `app/Models/Rating.php`**

  ```php
  // $incrementing = true
  // $timestamps = true (created_at + updated_at)
  // $fillable = ['user_id', 'recipe_id', 'score']
  // $casts = ['score' => 'integer']
  // Relations: user() BelongsTo User, recipe() BelongsTo Recipe
  ```

- [ ] **Step 3: Создать фабрики**

  `FavoriteFactory`: генерирует user_id из User::factory(), recipe_id из Recipe::factory().
  `RatingFactory`: генерирует user_id, recipe_id, score (fake()->numberBetween(1, 5)).

- [ ] **Step 4: Commit**

  ```bash
  git add app/Models/Favorite.php app/Models/Rating.php database/factories/FavoriteFactory.php database/factories/RatingFactory.php
  git commit --author="Claude <claude@anthropic.com>" -m "feat(BB-11): add Favorite and Rating models with factories"
  ```

**Handoff:** `make pint-dirty-dry` — 0 изменений.

---

## Task 3: FavoriteToggleHandler + unit тест

**Depends on:** Task 2

**Files:**
- Create: `app/Handlers/Favorites/FavoriteToggleHandler.php`
- Create: `tests/Unit/Handlers/Favorites/FavoriteToggleHandlerTest.php`

- [ ] **Step 1: Создать `FavoriteToggleHandler`**

  Метод `handle(int $userId, string $recipeId): bool`
  - Если `Favorite` существует → удалить, вернуть `false`
  - Если не существует → создать, вернуть `true`

- [ ] **Step 2: Unit тест**

  - `it('adds to favorites when not favorited')` → возвращает true, запись в БД
  - `it('removes from favorites when already favorited')` → возвращает false, запись удалена
  - `it('toggling twice returns to original state')` — дважды вызвать, убедиться что записи нет

- [ ] **Step 3: Запустить тесты**

  ```bash
  docker compose exec app php artisan test --filter=FavoriteToggleHandlerTest
  ```

- [ ] **Step 4: Commit**

  ```bash
  git add app/Handlers/Favorites/FavoriteToggleHandler.php tests/Unit/Handlers/Favorites/FavoriteToggleHandlerTest.php
  git commit --author="Claude <claude@anthropic.com>" -m "feat(BB-11): FavoriteToggleHandler — toggle favorite, unit tests"
  ```

**Handoff:** 3 unit-теста PASS; pint-dirty-dry — 0.

---

## Task 4: RateRecipeHandler + unit тест

**Depends on:** Task 2

**Files:**
- Create: `app/Handlers/Ratings/RateRecipeHandler.php`
- Create: `tests/Unit/Handlers/Ratings/RateRecipeHandlerTest.php`

- [ ] **Step 1: Создать `RateRecipeHandler`**

  Метод `handle(int $userId, string $recipeId, int $score): Rating`
  - Валидация: `$score` должен быть от 1 до 5 включительно; если нет → `\InvalidArgumentException`
  - `Rating::updateOrCreate(['user_id' => $userId, 'recipe_id' => $recipeId], ['score' => $score])`
  - Вернуть свежий объект `Rating`

- [ ] **Step 2: Unit тест**

  - `it('creates a new rating')` — запись создана, score верный
  - `it('updates existing rating on second call')` — второй вызов с другим score перезаписывает
  - `it('throws on invalid score 0')` — InvalidArgumentException
  - `it('throws on invalid score 6')` — InvalidArgumentException

- [ ] **Step 3: Запустить тесты**

  ```bash
  docker compose exec app php artisan test --filter=RateRecipeHandlerTest
  ```

- [ ] **Step 4: Commit**

  ```bash
  git add app/Handlers/Ratings/RateRecipeHandler.php tests/Unit/Handlers/Ratings/RateRecipeHandlerTest.php
  git commit --author="Claude <claude@anthropic.com>" -m "feat(BB-11): RateRecipeHandler — upsert rating 1-5, unit tests"
  ```

**Handoff:** 4 unit-теста PASS; pint-dirty-dry — 0.

---

## Task 5: ListFavoritesHandler + unit тест

**Depends on:** Task 2

**Files:**
- Create: `app/Handlers/Favorites/ListFavoritesHandler.php`
- Create: `tests/Unit/Handlers/Favorites/ListFavoritesHandlerTest.php`

- [ ] **Step 1: Создать `ListFavoritesHandler`**

  Метод `handle(int $userId): Collection` — возвращает коллекцию Recipe с дополнительным атрибутом `user_score`.

  Запрос (дизайн, секция 5):
  ```sql
  SELECT recipes.*, ratings.score as user_score
  FROM favorites
  JOIN recipes ON recipes.id = favorites.recipe_id
  LEFT JOIN ratings ON ratings.recipe_id = favorites.recipe_id
      AND ratings.user_id = favorites.user_id
  WHERE favorites.user_id = ?
  ORDER BY ratings.score DESC NULLS LAST, recipes.name_ru ASC
  ```

  Использовать `Recipe::query()` с joins, или raw Eloquent. Важно: `user_score` должен быть доступен как атрибут на каждом элементе коллекции.

- [ ] **Step 2: Unit тест**

  - `it('returns empty collection for user with no favorites')`
  - `it('returns favorites ordered by score desc then name asc')` — создать favorites с разными оценками, проверить порядок
  - `it('returns user_score attribute on each recipe')` — oценённые рецепты имеют user_score, неоценённые — null

- [ ] **Step 3: Запустить тесты**

  ```bash
  docker compose exec app php artisan test --filter=ListFavoritesHandlerTest
  ```

- [ ] **Step 4: Commit**

  ```bash
  git add app/Handlers/Favorites/ListFavoritesHandler.php tests/Unit/Handlers/Favorites/ListFavoritesHandlerTest.php
  git commit --author="Claude <claude@anthropic.com>" -m "feat(BB-11): ListFavoritesHandler — sorted favorites list, unit tests"
  ```

**Handoff:** 3 unit-теста PASS; pint-dirty-dry — 0.

---

## Task 6: FavoriteToggleAction + HTTP feature тест

**Depends on:** Task 3

**Files:**
- Create: `app/Actions/Favorites/FavoriteToggleAction.php`
- Create: `tests/Feature/Actions/Favorites/FavoriteToggleActionTest.php`

- [ ] **Step 1: Создать `FavoriteToggleAction`**

  - `__invoke(Request $request, string $id): JsonResponse`
    - `$userId` из `Auth::id()`
    - Вызвать `FavoriteToggleHandler::handle($userId, $id)`
    - Вернуть `['favorited' => $result]` HTTP 200

  - `fromTelegram(Nutgram $bot, string $id): void`
    - `$userId` из `$bot->userId()` через `User::where('telegram_id', ...)->value('id')`
    - Вызвать `FavoriteToggleHandler::handle($userId, $id)`
    - Вызвать `app(GetRecipeAction::class)->fromTelegram($bot, $id)` для перерисовки карточки (паттерн проекта)

- [ ] **Step 2: HTTP Feature тест**

  - `it('POST /api/recipes/{id}/favorite returns favorited true when adding')` — 200, `{favorited: true}`
  - `it('POST /api/recipes/{id}/favorite returns favorited false when removing')` — повторный запрос, `{favorited: false}`
  - `it('POST /api/recipes/{id}/favorite returns 401 without auth')` — запрос без `telegram_id`

- [ ] **Step 3: Запустить тесты**

  ```bash
  docker compose exec app php artisan test --filter=FavoriteToggleActionTest
  ```

- [ ] **Step 4: Commit**

  ```bash
  git add app/Actions/Favorites/FavoriteToggleAction.php tests/Feature/Actions/Favorites/FavoriteToggleActionTest.php
  git commit --author="Claude <claude@anthropic.com>" -m "feat(BB-11): FavoriteToggleAction — HTTP + Telegram toggle"
  ```

**Handoff:** 3 feature-теста PASS; pint-dirty-dry — 0.

---

## Task 7: RateAction + ShowRatingPickerAction + HTTP feature тест

**Depends on:** Task 4

**Files:**
- Create: `app/Actions/Ratings/RateAction.php`
- Create: `app/Actions/Ratings/ShowRatingPickerAction.php`
- Create: `tests/Feature/Actions/Ratings/RateActionTest.php`

- [ ] **Step 1: Создать `RateAction`**

  - `__invoke(Request $request, string $id): JsonResponse`
    - Валидировать `score` (integer, 1–5) из body
    - `$userId` из `Auth::id()`
    - Вызвать `RateRecipeHandler::handle($userId, $id, $score)`
    - Вычислить `avg` и `count` через `Rating::where('recipe_id', $id)->selectRaw('ROUND(AVG(score),1) as avg, COUNT(*) as count')->first()`
    - Вернуть `['score' => $rating->score, 'avg' => $avg, 'count' => $count]`

  - `fromTelegram(Nutgram $bot, string $id, int $score): void`
    - Вызвать `RateRecipeHandler::handle($userId, $id, $score)`
    - Перерисовать карточку: `app(GetRecipeAction::class)->fromTelegram($bot, $id)`

- [ ] **Step 2: Создать `ShowRatingPickerAction`**

  Telegram-only: `fromTelegram(Nutgram $bot, string $id): void`
  - `answerCallbackQuery()`
  - `editMessageReplyMarkup(reply_markup: ...)` — клавиатура с одним рядом: [⭐1][⭐2][⭐3][⭐4][⭐5]
    callbacks: `recipe:{id}:rate:1` … `recipe:{id}:rate:5`

- [ ] **Step 3: HTTP Feature тест**

  - `it('POST /api/recipes/{id}/rate creates rating and returns stats')` — 200, `{score, avg, count}`
  - `it('POST /api/recipes/{id}/rate upserts on second call')` — второй POST с другим score, avg пересчитался
  - `it('POST /api/recipes/{id}/rate returns 422 for score 0')` — невалидный score
  - `it('POST /api/recipes/{id}/rate returns 422 for score 6')` — невалидный score

- [ ] **Step 4: Запустить тесты**

  ```bash
  docker compose exec app php artisan test --filter=RateActionTest
  ```

- [ ] **Step 5: Commit**

  ```bash
  git add app/Actions/Ratings/RateAction.php app/Actions/Ratings/ShowRatingPickerAction.php tests/Feature/Actions/Ratings/RateActionTest.php
  git commit --author="Claude <claude@anthropic.com>" -m "feat(BB-11): RateAction + ShowRatingPickerAction — HTTP + Telegram rating"
  ```

**Handoff:** 4 feature-теста PASS; pint-dirty-dry — 0.

---

## Task 8: ListFavoritesAction + ListFavoritesConversation + HTTP feature тест

**Depends on:** Task 5

**Files:**
- Create: `app/Actions/Favorites/ListFavoritesAction.php`
- Create: `app/Telegram/Conversations/ListFavoritesConversation.php`
- Create: `tests/Feature/Actions/Favorites/ListFavoritesActionTest.php`

- [ ] **Step 1: Создать `ListFavoritesAction`**

  - `__invoke(Request $request): JsonResponse`
    - `$userId` из `Auth::id()`
    - Вызвать `ListFavoritesHandler::handle($userId)`, вернуть коллекцию рецептов JSON

  - `fromTelegram(Nutgram $bot, int $page = 0): void`
    - Используется только из `ListFavoritesConversation`; получает страницу, рендерит текст + клавиатуру

- [ ] **Step 2: Создать `ListFavoritesConversation`**

  Состояние (protected):
  ```php
  protected int $page = 0;
  protected array $recipeIds = []; // все UUID избранного
  ```

  Шаги:
  - `start(Nutgram $bot)`: загрузить все ID из `ListFavoritesHandler`, сохранить в `$this->recipeIds`
    - Если пусто → `sendMessage('У тебя пока нет избранных рецептов 🤍')`, `$this->end()`
    - Иначе → рендерить страницу (10 рецептов) + клавиатура `[<<][🏠 Главная][>>]`, `$this->next('handleInput')`

  - `handleInput(Nutgram $bot)`:
    - callback `favorites:prev` → `$this->page--`, editMessageText, остаться на `handleInput`
    - callback `favorites:next` → `$this->page++`, editMessageText, остаться на `handleInput`
    - callback `browse:back` → `$this->end()`
    - текст — число N в диапазоне страницы → `app(GetRecipeAction::class)->fromTelegram($bot, $recipeId)`, `$this->end()`
    - невалидный ввод → повторить подсказку, остаться на `handleInput`

  Формат строки (моноширинный блок ``` ``` ```):
  ```
  {num:2}. {name:20} {rate:4} {abv:3} {vol:5}
  ```
  - name: обрезать до 19 символов + `…` если длиннее
  - rate: `⭐N.N` (средняя оценка рецепта из `Rating::...`) или 4 пробела
  - abv: `N%` или 3 пробела
  - vol: `NNNмл` или 5 пробелов

  Клавиатура пагинации: `<<` disabled (`noop`) на первой странице, `>>` disabled на последней.

- [ ] **Step 3: HTTP Feature тест**

  - `it('GET /api/favorites returns empty array for new user')` — 200, `[]`
  - `it('GET /api/favorites returns favorited recipes')` — после добавления в избранное — рецепт в списке

- [ ] **Step 4: Запустить тесты**

  ```bash
  docker compose exec app php artisan test --filter=ListFavoritesActionTest
  ```

- [ ] **Step 5: Commit**

  ```bash
  git add app/Actions/Favorites/ListFavoritesAction.php app/Telegram/Conversations/ListFavoritesConversation.php tests/Feature/Actions/Favorites/ListFavoritesActionTest.php
  git commit --author="Claude <claude@anthropic.com>" -m "feat(BB-11): ListFavoritesAction + ListFavoritesConversation — favorites browser"
  ```

**Handoff:** 2 feature-теста PASS; pint-dirty-dry — 0.

---

## Task 9: GetRecipeAction — контекст избранного/оценок + новые кнопки

**Depends on:** Tasks 6, 7

**Files:**
- Modify: `app/Actions/GetRecipeAction.php`

- [ ] **Step 1: Загрузка контекста в `fromTelegram`**

  После получения рецепта:
  ```php
  $userId    = User::where('telegram_id', $bot->userId())->value('id');
  $isFavorite = Favorite::where('user_id', $userId)->where('recipe_id', $id)->exists();
  $userRating = Rating::where('user_id', $userId)->where('recipe_id', $id)->value('score');
  $stats      = Rating::where('recipe_id', $id)
      ->selectRaw('ROUND(AVG(score), 1) as avg, COUNT(*) as count')
      ->first();
  $avg   = $stats?->avg;
  $count = $stats?->count ?? 0;
  ```

- [ ] **Step 2: Строка рейтинга в тексте карточки**

  Добавить в начало текста карточки (перед или после названия рецепта):
  - $count === 0 → строку не добавлять
  - $count > 0, $userRating === null → `⭐ {avg} ({count} оценки/оценок)`
  - $count > 0, $userRating !== null → `⭐ {avg} ({count} оценки/оценок) · ваша: {userRating}⭐`

  Склонение «оценок/оценки» — простое, через вспомогательный метод или inline.

- [ ] **Step 3: Новые ряды кнопок**

  После существующих кнопок (`🔙 К поиску`, `🛒 Заказать`) добавить:

  Ряд избранного:
  - `$isFavorite` → `[❤️ Убрать из избранного]` callback: `recipe:{id}:favorite`
  - иначе → `[🤍 В избранное]` callback: `recipe:{id}:favorite`

  Ряд оценки:
  - `$userRating === null` → `[⭐1][⭐2][⭐3][⭐4][⭐5]` callbacks: `recipe:{id}:rate:1`…`recipe:{id}:rate:5`
  - `$userRating !== null` → `[Переоценить ({userRating}⭐)]` callback: `recipe:{id}:rate:new`

- [ ] **Step 4: Аналогично обновить `__invoke` (HTTP)**

  В HTTP-версии `$userId` берётся через `Auth::id()`. Добавить те же поля к JSON-ответу:
  `is_favorite`, `user_rating`, `avg_rating`, `ratings_count`.

- [ ] **Step 5: Rename callback recipe:show:{id} → recipe:{id}:show**

  В `GetRecipeAction` callback `browse:back` кнопки «🔙 К поиску» — не меняется.
  Если внутри файла есть формирование callback для показа этой же карточки — исправить.

- [ ] **Step 6: Запустить полный тест-сьют**

  ```bash
  make tests
  ```

- [ ] **Step 7: Commit**

  ```bash
  git add app/Actions/GetRecipeAction.php
  git commit --author="Claude <claude@anthropic.com>" -m "feat(BB-11): GetRecipeAction — favorites/ratings context, new keyboard rows"
  ```

**Handoff:** `make tests` — все PASS, регрессий нет; pint-dirty-dry — 0.

---

## Task 10: SearchResultsResponse + caller Actions + callback rename + route wiring

**Depends on:** Tasks 6, 7, 8

**Files:**
- Modify: `app/Telegram/Responses/SearchResultsResponse.php`
- Modify: `app/Actions/Search/SearchRecipesAction.php`
- Modify: `app/Actions/Search/SearchByIngredientAction.php`
- Modify: `app/Actions/BrowseRecipesAction.php`
- Modify: `routes/telegram.php`
- Modify: `routes/api.php`

- [ ] **Step 1: Обновить `SearchResultsResponse`**

  Добавить параметр `Collection $favoritedIds` (collection keyed by recipe_id) в конструктор со значением по умолчанию пустой коллекции.

  Формат строки (моноширинный блок):
  ```
  {fav:1} {name:20} {rate:4} {abv:3} {vol:5}
  ```
  - `fav`: `❤` если `$favoritedIds->has($recipe->id)`, иначе пробел
  - `rate`: средняя оценка рецепта (нужен joinable запрос или отдельный SELECT avg per recipe_id из переданной коллекции)

  **Про avg оценок в списке:** передавать avg ratings внутри коллекции рецептов (жадная загрузка через withAvg), а не делать N+1 запросов. Конкретный подход — на усмотрение исполнителя.

  Rename callback: `recipe:show:{id}` → `recipe:{id}:show` во всех inline-кнопках этого класса.

- [ ] **Step 2: Обновить `SearchRecipesAction`**

  В `fromTelegram`: перед вызовом `SearchResultsResponse` получить `$favoritedIds`:
  ```php
  $userId = User::where('telegram_id', $bot->userId())->value('id');
  $favoritedIds = $userId
      ? Favorite::where('user_id', $userId)->pluck('recipe_id')->flip()
      : collect();
  ```
  Передать в `SearchResultsResponse(..., $favoritedIds)`.

  В `__invoke` (HTTP): без изменений (HTTP-ответ не включает favorites в списке).

- [ ] **Step 3: Аналогично обновить `SearchByIngredientAction`**

- [ ] **Step 4: Rename в `BrowseRecipesAction`**

  - `recipe:show:{id}` → `recipe:{id}:show` в формировании кнопок навигации
  - `recipe:order:{id}` → `recipe:{id}:order` в формировании кнопки заказа

- [ ] **Step 5: Обновить `routes/telegram.php`**

  Rename старых маршрутов:
  ```php
  // Было:
  $bot->onCallbackQueryData('recipe:show:{id}', ...);
  $bot->onCallbackQueryData('recipe:order:{id}', ...);
  // Стало:
  $bot->onCallbackQueryData('recipe:{id}:show', ...);
  $bot->onCallbackQueryData('recipe:{id}:order', ...);
  ```

  Добавить новые маршруты:
  ```php
  $bot->onCallbackQueryData('recipe:{id}:favorite', [FavoriteToggleAction::class, 'fromTelegram']);
  $bot->onCallbackQueryData('recipe:{id}:rate:{score}', [RateAction::class, 'fromTelegram']);
  $bot->onCallbackQueryData('recipe:{id}:rate:new', [ShowRatingPickerAction::class, 'fromTelegram']);
  $bot->onCommand('favorites', [ListFavoritesAction::class, 'fromTelegram']); // или Conversation::begin
  $bot->onCallbackQueryData('favorites:prev', ...); // обработчик внутри Conversation
  $bot->onCallbackQueryData('favorites:next', ...);
  ```

  > **Примечание:** колбэки `favorites:prev` и `favorites:next` должны направляться в `ListFavoritesConversation`; конкретный механизм — через статический метод Conversation или отдельный обработчик — на усмотрение исполнителя, в соответствии с паттерном проекта.

- [ ] **Step 6: Обновить `routes/api.php`**

  ```php
  // Под middleware auth.telegram:
  Route::post('/recipes/{id}/favorite', FavoriteToggleAction::class);
  Route::post('/recipes/{id}/rate',     RateAction::class);
  Route::get('/favorites',              ListFavoritesAction::class);
  ```

- [ ] **Step 7: Запустить полный тест-сьют**

  ```bash
  make tests
  ```

- [ ] **Step 8: Commit**

  ```bash
  git add app/Telegram/Responses/SearchResultsResponse.php \
          app/Actions/Search/SearchRecipesAction.php \
          app/Actions/Search/SearchByIngredientAction.php \
          app/Actions/BrowseRecipesAction.php \
          routes/telegram.php \
          routes/api.php
  git commit --author="Claude <claude@anthropic.com>" -m "feat(BB-11): SearchResultsResponse favorites column, callback rename, new routes"
  ```

**Handoff:** `make tests` — все PASS; pint-dirty-dry — 0; новые роуты видны в `php artisan route:list`.

---

## Task 11: E2E тест PhaseFourFlowTest

**Depends on:** Tasks 9, 10

**Files:**
- Create: `tests/Feature/PhaseFourFlowTest.php`

- [ ] **Step 1: Создать `PhaseFourFlowTest`**

  Сценарий (HTTP API, telegram_id auth):
  1. `GET /api/favorites` → пустой массив
  2. `POST /api/recipes/{id}/favorite` → `{favorited: true}`
  3. `GET /api/favorites` → массив с одним рецептом
  4. `POST /api/recipes/{id}/rate` body `{score:4}` → `{score:4, avg:4.0, count:1}`
  5. `POST /api/recipes/{id}/rate` body `{score:2}` → `{score:2, avg:2.0, count:1}` (upsert)
  6. `POST /api/recipes/{id}/favorite` (второй раз) → `{favorited: false}`
  7. `GET /api/favorites` → пустой массив (запись удалена)
  8. `make db-q` проверить: оценка в `ratings` осталась — `SELECT count(*) FROM ratings` → 1

- [ ] **Step 2: Запустить**

  ```bash
  docker compose exec app php artisan test --filter=PhaseFourFlowTest
  make tests
  ```

- [ ] **Step 3: Commit**

  ```bash
  git add tests/Feature/PhaseFourFlowTest.php
  git commit --author="Claude <claude@anthropic.com>" -m "test(BB-11): e2e PhaseFourFlowTest — full favorites+ratings lifecycle"
  ```

**Handoff:** `make tests` — все PASS, включая E2E; pint-dirty-dry — 0.

---

## Task 12: Финал — codebase.md + telegram-ui.md + PR

**Depends on:** Task 11

**Files:**
- Modify: `.agents/knowledge/codebase.md`
- Modify: `.agents/knowledge/telegram-ui.md`

- [ ] **Step 1: Обновить `codebase.md`**

  - Статус Phase 4 → ✅ Готово
  - Добавить в «Статус реализации фаз»
  - Добавить модели `Favorite`, `Rating` в раздел «Модели и связи»
  - Добавить новые маршруты (telegram + HTTP) в соответствующие разделы
  - Зафиксировать callback-конвенцию `recipe:{id}:action` и rename старых callbacks
  - Добавить паттерн смешанного ввода в `ListFavoritesConversation` в раздел «Nutgram: особенности Conversations»

- [ ] **Step 2: Обновить `telegram-ui.md`**

  Добавить/обновить экраны:
  - **Карточка рецепта** — строка рейтинга, ряд избранного, ряд оценки
  - **Список избранного** — формат строки, клавиатура пагинации, сообщение пустого списка

- [ ] **Step 3: Запустить финальный тест-сьют и lint**

  ```bash
  make tests
  make pint-dirty-dry
  ```

- [ ] **Step 4: Commit docs**

  ```bash
  git add .agents/knowledge/codebase.md .agents/knowledge/telegram-ui.md
  git commit --author="Claude <claude@anthropic.com>" -m "docs(BB-11): update codebase.md + telegram-ui.md — Phase 4 complete"
  ```

- [ ] **Step 5: Открыть PR**

  ```bash
  gh pr create \
    --title "BB-11: favorites and ratings" \
    --body "$(cat <<'EOF'
  ## Summary
  - Добавлены модели Favorite и Rating с суррогатными id (ALTER-миграция)
  - FavoriteToggleAction, RateAction, ShowRatingPickerAction, ListFavoritesAction
  - ListFavoritesConversation — просмотр избранного с пагинацией и смешанным вводом
  - GetRecipeAction расширен: контекст favorites/ratings, ряды кнопок ❤️ и ⭐
  - SearchResultsResponse расширен: fav-столбец, avg оценок
  - Callback-конвенция приведена к recipe:{id}:action (rename show + order)
  - Unit-тесты handlers, feature-тесты HTTP, E2E PhaseFourFlowTest
  EOF
  )"
  ```

**Handoff (финальный):** `make tests` — все PASS; pint-dirty-dry — 0; codebase.md + telegram-ui.md обновлены; PR открыт — ссылка в отчёте.

---

## Pre-PR проверка автора плана

- [x] **Goal** заполнен.
- [x] **Branch** указан: `feature/BB-11_favorites-ratings`.
- [x] **Карта файлов** перечисляет все файлы из Steps (включая ancillary: codebase.md, telegram-ui.md, routes/*.php, фабрики).
- [x] **Порядок исполнения** присутствует, параллельные группы обоснованы.
- [x] У каждой задачи есть **Depends on** и **Files**.
- [x] План — каркас: нет тел методов, зафиксированы только контракты (callback-паттерны, имена файлов, что вход/выход у handlers).
- [x] Финальная задача содержит Step «Обновить codebase.md», «Обновить telegram-ui.md» и «Открыть PR».
