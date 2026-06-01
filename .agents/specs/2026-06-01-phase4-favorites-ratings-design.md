# Phase 4: Избранное и оценки — Design Spec

**Date:** 2026-06-01

---

## 1. Цель

Пользователи могут помечать рецепты как избранные и выставлять оценку (1–5). Избранное и оценки — независимые сущности. Средняя оценка отображается на карточке рецепта и в списке поиска.

---

## 2. Схема данных

### Таблица `favorites`

```sql
CREATE TABLE favorites (
    id         INTEGER     GENERATED ALWAYS AS IDENTITY,
    user_id    BIGINT      NOT NULL,
    recipe_id  TEXT        NOT NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    CONSTRAINT pk_favorites            PRIMARY KEY (id),
    CONSTRAINT fk_favorites_user_id    FOREIGN KEY (user_id)   REFERENCES users(id)   ON DELETE CASCADE,
    CONSTRAINT fk_favorites_recipe_id  FOREIGN KEY (recipe_id) REFERENCES recipes(id) ON DELETE CASCADE,
    CONSTRAINT uq_favorites_user_recipe UNIQUE (user_id, recipe_id)
);
```

Обоснование `id INTEGER`: суррогатный ключ для Eloquent-совместимости. Кардинальность — произведение `users × recipes` (~10³–10⁴ строк), INTEGER (2.1 млрд) покрывает любой реалистичный сценарий.

### Таблица `ratings`

```sql
CREATE TABLE ratings (
    id         INTEGER     GENERATED ALWAYS AS IDENTITY,
    user_id    BIGINT      NOT NULL,
    recipe_id  TEXT        NOT NULL,
    score      SMALLINT    NOT NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    CONSTRAINT pk_ratings              PRIMARY KEY (id),
    CONSTRAINT fk_ratings_user_id      FOREIGN KEY (user_id)   REFERENCES users(id)   ON DELETE CASCADE,
    CONSTRAINT fk_ratings_recipe_id    FOREIGN KEY (recipe_id) REFERENCES recipes(id) ON DELETE CASCADE,
    CONSTRAINT uq_ratings_user_recipe  UNIQUE (user_id, recipe_id),
    CONSTRAINT chk_ratings_score       CHECK (score BETWEEN 1 AND 5)
);
```

Обоснование `id INTEGER`: аналогично `favorites`.

### Модели

```php
// app/Models/Favorite.php
// $incrementing = true (есть суррогатный id)
// $timestamps = false (только created_at — заполняется DEFAULT NOW())
// Fillable: user_id, recipe_id
$favorite->user()   // BelongsTo User
$favorite->recipe() // BelongsTo Recipe

// app/Models/Rating.php
// $incrementing = true
// $timestamps = true (created_at + updated_at)
// Fillable: user_id, recipe_id, score
// Casts: score → integer
$rating->user()   // BelongsTo User
$rating->recipe() // BelongsTo Recipe
```

Upsert оценки: `Rating::updateOrCreate(['user_id' => ..., 'recipe_id' => ...], ['score' => ...])`.

---

## 3. Карточка рецепта — изменения

### Строка рейтинга в тексте карточки

Добавляется в шапку `Recipe::toTelegramMessage()` (или в `GetRecipeAction` при формировании текста):

```
// Никто не оценил — строку не показываем

// Есть оценки, текущий пользователь не оценивал:
⭐ 4.2 (3 оценки)

// Есть оценки, текущий пользователь оценил:
⭐ 4.2 (3 оценки) · ваша: 3⭐
```

`AVG(score)` и `COUNT(*)` вычисляются в `GetRecipeAction` одним запросом. Личная оценка — отдельный SELECT по `(user_id, recipe_id)`.

### Новые ряды клавиатуры

Добавляются ниже существующих кнопок («🔙 К поиску», «🛒 Заказать» и т.д.):

```
// Ряд: избранное
[❤️ Убрать из избранного]  или  [🤍 В избранное]

// Ряд: оценка — пользователь ещё не оценил
[⭐1] [⭐2] [⭐3] [⭐4] [⭐5]

// Ряд: оценка — пользователь уже оценил
[Переоценить (3⭐)]
```

### Callbacks

| Callback | Action | Описание |
|---|---|---|
| `recipe:{id}:favorite` | `FavoriteToggleAction` | Toggle избранного |
| `recipe:{id}:rate:{score}` | `RateAction` | Сохранить оценку (score = 1–5) |
| `recipe:{id}:rate:new` | `ShowRatingPickerAction` | Показать кнопки ⭐1–5 (для переоценки) |

После любого действия — полный `editMessageText` с обновлённой карточкой и клавиатурой.

### Загрузка контекста в `GetRecipeAction`

```php
// В fromTelegram и __invoke:
$userId    = ...; // из $bot->userId() или из request
$isFavorite = Favorite::where('user_id', $userId)->where('recipe_id', $id)->exists();
$userRating = Rating::where('user_id', $userId)->where('recipe_id', $id)->value('score');
['avg' => $avg, 'count' => $count] = Rating::where('recipe_id', $id)
    ->selectRaw('ROUND(AVG(score), 1) as avg, COUNT(*) as count')
    ->first()
    ?->toArray() ?? ['avg' => null, 'count' => 0];
```

---

## 4. Actions

### `FavoriteToggleAction`

- Telegram: `fromTelegram(Nutgram $bot, string $id)` — toggle, полный `editMessageText` карточки
- HTTP: `__invoke(Request $request, string $id): JsonResponse` — toggle, возвращает `{favorited: bool}`

Handler: `FavoriteToggleHandler` — `updateOrCreate` / `delete`, возвращает `bool $isFavorite`.

### `RateAction`

- Telegram: `fromTelegram(Nutgram $bot, string $id, int $score)` — upsert оценки, полный `editMessageText`
- HTTP: `__invoke(Request $request, string $id): JsonResponse` — body `{score: 1-5}`, возвращает `{score, avg, count}`

Handler: `RateRecipeHandler` — `Rating::updateOrCreate(...)`, возвращает `Rating`.

### `ShowRatingPickerAction`

- Telegram only: `fromTelegram(Nutgram $bot, string $id)` — `editMessageReplyMarkup` с рядом ⭐1–5
- Нет HTTP-транспорта (чисто UI-операция без мутации состояния)

### `ListFavoritesAction`

- HTTP: `__invoke(Request $request): JsonResponse` — список избранного текущего пользователя
- Telegram: `fromTelegram(Nutgram $bot, int $page): void` — вызывается из `ListFavoritesConversation`

Handler: `ListFavoritesHandler` — запрос избранного с JOIN на ratings для сортировки.

---

## 5. Список избранного — `ListFavoritesConversation`

Запускается через `/favorites`.

### Запрос данных

```sql
SELECT recipes.*, ratings.score as user_score
FROM favorites
JOIN recipes ON recipes.id = favorites.recipe_id
LEFT JOIN ratings ON ratings.recipe_id = favorites.recipe_id
    AND ratings.user_id = favorites.user_id
WHERE favorites.user_id = ?
ORDER BY ratings.score DESC NULLS LAST, recipes.name_ru ASC
```

### Пустой список

```
У тебя пока нет избранных рецептов 🤍
```

Без клавиатуры навигации.

### Непустой список — текст сообщения

Моноширинный блок (` ``` `), 40 символов в строке:

```
 1. Маргарита             ⭐4.2 23% 120мл
 2. Негрони                    24%  75мл
 3. Лонг Айлендский ча… ⭐3.8 18% 500мл
...
10. Дайкири               ⭐4.0 20% 100мл

Введи номер для просмотра:
```

Формат строки (37 символов): `{num:2}. {name:20} {rate:4} {abv:3} {vol:5}`

- `name` — обрезается до 19 символов + `…` если длиннее
- `rate` — `⭐N.N` (средняя оценка рецепта) или 4 пробела
- `abv` — `N%` right-aligned или 3 пробела
- `vol` — `NNNмл` или 5 пробелов

### Клавиатура

```
[<<] [🏠 Главная] [>>]
```

- `<<` / `>>` на границах (первая/последняя страница) — callback `noop`
- Callbacks активной пагинации: `favorites:prev`, `favorites:next`
- `🏠 Главная` — `browse:back`

### Состояние Conversation

```php
protected int $page = 0;
protected array $recipeIds = []; // все UUID избранного (не только текущая страница)
```

### Шаг `handleInput`

Принимает текст или callback:
- Callback `favorites:prev` / `favorites:next` → меняет `$this->page`, `editMessageText`, остаётся на `handleInput`
- Callback `browse:back` → `$this->end()`
- Текст — валидное число N (1–10 на текущей странице) → `app(GetRecipeAction::class)->fromTelegram($bot, $recipeId)`, `$this->end()`
- Текст невалидный → повторное сообщение с подсказкой, остаётся на `handleInput`

---

## 6. Список поиска — обновление `SearchResultsResponse`

Текст каждой строки расширяется до формата:

```
{fav:1} {name:20} {rate:4} {abv:3} {vol:5}
```

- `fav` — `❤` если рецепт в избранном у текущего пользователя, иначе пробел
- `rate` — средняя оценка или пробелы
- Весь текст — в моноширинном блоке (` ``` `)

Кнопки (inline keyboard) — без изменений, полное название рецепта.

### Изменение конструктора `SearchResultsResponse`

Добавляется параметр `Set<string> $favoritedIds` (набор recipe_id избранного текущего пользователя). Все вызывающие Actions (`SearchRecipesAction`, `SearchByIngredientAction`) делают один JOIN-запрос перед рендером:

```php
$favoritedIds = Favorite::where('user_id', $userId)
    ->pluck('recipe_id')
    ->flip(); // Collection → keyed by recipe_id для O(1) lookup
```

---

## 7. HTTP API

Все эндпоинты под middleware `auth.telegram`:

```
POST  /api/recipes/{id}/favorite  → FavoriteToggleAction   200: {favorited: bool}
POST  /api/recipes/{id}/rate      → RateAction             body: {score: 1-5}; 200: {score, avg, count}
GET   /api/favorites              → ListFavoritesAction    200: [{recipe...}]
```

---

## 8. Callback-конвенция (новая)

**Новые callbacks в Phase 4** используют схему `recipe:{id}:action`:

```
recipe:{id}:favorite
recipe:{id}:rate:{score}
recipe:{id}:rate:new
```

**Переименование существующих callbacks** — отдельная финальная задача плана. Список мест:

| Старый callback | Новый callback | Файл |
|---|---|---|
| `recipe:show:{id}` | `recipe:{id}:show` | `routes/telegram.php`, `SearchResultsResponse`, `BrowseRecipesAction` |
| `recipe:browse:{key}:{pos}` | Оставить без изменений — не recipe-specific | — |
| `recipe:order:{id}` | `recipe:{id}:order` | `routes/telegram.php`, `GetRecipeAction`, `BrowseRecipesAction` |

> **Примечание:** `recipe:browse:{key}:{pos}` — это навигация по кэшу поиска, не действие над рецептом. Переименование не требуется.

---

## 9. Тестирование

### Unit-тесты Handlers

- `FavoriteToggleHandlerTest` — toggle добавляет/удаляет; повторный вызов инвертирует
- `RateRecipeHandlerTest` — создаёт оценку; повторный вызов обновляет; score вне 1–5 → ValidationException
- `ListFavoritesHandlerTest` — возвращает отсортированный список; пустой список

### Feature-тесты Actions (HTTP)

- `FavoriteToggleActionTest` — POST toggle; 401 без auth
- `RateActionTest` — POST rate; 422 при невалидном score
- `ListFavoritesActionTest` — GET список; пустой список

### Feature-тест E2E

`PhaseFourFlowTest`:
1. Пользователь добавляет рецепт в избранное → `/api/favorites` возвращает его
2. Пользователь выставляет оценку → средняя оценка обновляется
3. Повторная оценка → upsert, средняя пересчитывается
4. Удаляет из избранного → `/api/favorites` пустой; оценка остаётся в `ratings`

---

## 10. Известные ограничения

- `ShowRatingPickerAction` не имеет HTTP-транспорта — это UI-only операция
- `SearchResultsResponse` требует JOIN на `favorites`; если пользователь не аутентифицирован (HTTP без `telegram_id`) — передаётся пустой set
- Conversation `ListFavoritesConversation` использует смешанный ввод (текст + callback) в одном шаге — новый паттерн для проекта, явно задокументирован
