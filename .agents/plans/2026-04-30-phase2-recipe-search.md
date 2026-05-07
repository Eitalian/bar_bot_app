# Phase 2: Recipe Search — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.
>
> **Before starting:** Read `.agents/knowledge/codebase.md`
> **After completing:** Update `.agents/knowledge/codebase.md` — Phase 2 status, new patterns, notes.

**Goal:** Активировать поиск коктейлей в Telegram (по названию, ингредиентам, фильтрам), добавить навигацию по результатам с плейсхолдерами кнопок Заказать/Форкнуть, добавить HTTP API для рецептов.

**Architecture:**
- Бизнес-логика поиска извлекается из Conversations в Handler-классы (тестируемые изолированно).
- `BrowseContext` (Cache-сервис) хранит упорядоченные списки ID рецептов по ключу = `telegram_id` — обеспечивает навигацию prev/next (один активный browse на пользователя).
- `RecipeBrowseHandler` показывает карточку рецепта с полной клавиатурой навигации.
- HTTP-эндпоинты используют те же Handlers через Action-классы.

**Tech Stack:** Laravel 12, Nutgram, Pest, spatie/laravel-data, PostgreSQL, Laravel Cache

**Branch:** `feature/bb6_recipe-search`

---

## Карта файлов

### Новые файлы

| Файл | Ответственность |
|---|---|
| `database/factories/RecipeFactory.php` | Фабрика Recipe для тестов |
| `app/Data/Search/SearchRecipesData.php` | DTO: q, glass, abv_min/max, vol_min/max, tag, page, perPage |
| `app/Data/Search/SearchByIngredientData.php` | DTO: массив ingredient_ids |
| `app/Handlers/Search/SearchRecipesHandler.php` | Поиск по названию + фильтры → LengthAwarePaginator |
| `app/Handlers/Search/SearchByIngredientHandler.php` | Рецепты со всеми заданными ингредиентами |
| `app/Handlers/Search/GetRecipeHandler.php` | Один рецепт по ID с eager-load |
| `app/Services/BrowseContext.php` | Cache-сервис: сохранить/получить список ID по ключу |
| `app/Telegram/Handlers/RecipeBrowseHandler.php` | Показ рецепта с prev/next/back/noop-кнопками |
| `app/Actions/Search/GetRecipeAction.php` | HTTP GET /api/recipes/{id} |
| `app/Actions/Search/SearchRecipesAction.php` | HTTP GET /api/recipes?q=&glass=&... |
| `tests/Unit/Handlers/Search/SearchRecipesHandlerTest.php` | Unit-тесты |
| `tests/Unit/Handlers/Search/SearchByIngredientHandlerTest.php` | Unit-тесты |
| `tests/Unit/Handlers/Search/GetRecipeHandlerTest.php` | Unit-тесты |
| `tests/Unit/Services/BrowseContextTest.php` | Unit-тесты |
| `tests/Feature/Actions/Search/SearchRecipesActionTest.php` | Feature-тесты HTTP |
| `tests/Feature/Actions/Search/GetRecipeActionTest.php` | Feature-тесты HTTP |

### Изменяемые файлы

| Файл | Изменение |
|---|---|
| `app/Models/Recipe.php` | Добавить `use HasFactory` |
| `routes/telegram.php` | Добавить маршруты поиска + browse + browse:back |
| `routes/api.php` | Добавить маршруты /api/recipes |
| `app/Telegram/Conversations/SearchByNameConversation.php` | `showResults` → SearchRecipesHandler + BrowseContext |
| `app/Telegram/Conversations/FilterConversation.php` | `showResults` → SearchRecipesHandler + BrowseContext |
| `app/Telegram/Conversations/SearchByIngredientConversation.php` | `handleIngredient` → фикс callback; `showResults` → SearchByIngredientHandler + BrowseContext |
| `app/Telegram/Handlers/RecipeHandler.php` | Внедрить GetRecipeHandler |

---

## Критерии handoff между задачами

Перед передачей управления следующему агенту выполнить проверку:

```
✅ HANDOFF CHECKLIST
1. docker compose exec app php artisan test  →  все тесты задачи PASS, никаких FAIL
2. make pint-dirty-dry  →  0 изменений (код соответствует code style)
3. git status  →  нет неотслеживаемых или незакоммиченных файлов
4. Все файлы задачи из секции "Files" существуют
5. В отчёте агента: список изменённых файлов + краткое описание что сделано
```

Если хотя бы один пункт не выполнен — агент не передаёт управление, а исправляет проблему.

---

## Task 1: Ветка + RecipeFactory + HasFactory

**Files:**
- Create: `database/factories/RecipeFactory.php`
- Modify: `app/Models/Recipe.php`

- [ ] **Step 1: Создать ветку**

```bash
git checkout -b feature/bb6_recipe-search
```

- [ ] **Step 2: Добавить HasFactory в Recipe**

В `app/Models/Recipe.php` добавить import и trait:

```php
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Recipe extends Model
{
    use HasFactory;
    // ... остальное без изменений
```

- [ ] **Step 3: Создать RecipeFactory**

Создать `database/factories/RecipeFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\Recipe;
use Illuminate\Database\Eloquent\Factories\Factory;

class RecipeFactory extends Factory
{
    protected $model = Recipe::class;

    public function definition(): array
    {
        return [
            'id'           => $this->faker->uuid(),
            'name_ru'      => $this->faker->words(2, true),
            'name_en'      => $this->faker->words(2, true),
            'description'  => $this->faker->optional()->sentence(),
            'instructions' => $this->faker->optional()->paragraph(),
            'glass'        => $this->faker->randomElement([
                'rocks', 'highball', 'cocktail', 'coupe', 'shot', 'margarita',
            ]),
            'abv'          => $this->faker->randomFloat(1, 0, 45),
            'volume'       => $this->faker->randomElement([30, 60, 100, 150, 200, 300]),
            'icon'         => null,
            'photo'        => null,
            'taste_tags'   => null,
        ];
    }

    public function nonAlcoholic(): static
    {
        return $this->state(['abv' => 0.0]);
    }
}
```

- [ ] **Step 4: Проверить, что фабрика работает**

```bash
docker compose exec app php artisan tinker --execute="dump(App\Models\Recipe::factory()->make()->toArray());"
```

Ожидаемый вывод: массив с uuid в `id`, строками в `name_ru`/`name_en`, значениями `glass`, `abv`, `volume`.

- [ ] **Step 5: Commit**

```bash
git add database/factories/RecipeFactory.php app/Models/Recipe.php
git commit --author="Claude <claude@anthropic.com>" -m "feat(bb6): RecipeFactory + HasFactory on Recipe"
```

**Handoff:** `docker compose exec app php artisan test` — все тесты pass; tinker из Step 4 выдаёт корректный массив.

---

## Task 2: SearchRecipesHandler

**Files:**
- Create: `app/Data/Search/SearchRecipesData.php`
- Create: `app/Handlers/Search/SearchRecipesHandler.php`
- Create: `tests/Unit/Handlers/Search/SearchRecipesHandlerTest.php`

- [ ] **Step 1: Написать падающий тест**

Создать `tests/Unit/Handlers/Search/SearchRecipesHandlerTest.php`:

```php
<?php

use App\Data\Search\SearchRecipesData;
use App\Handlers\Search\SearchRecipesHandler;
use App\Models\Recipe;
use App\Models\RecipeTag;

it('finds recipes by name_ru (case-insensitive)', function () {
    Recipe::factory()->create(['name_ru' => 'Маргарита', 'name_en' => 'Margarita']);
    Recipe::factory()->create(['name_ru' => 'Мохито', 'name_en' => 'Mojito']);

    $result = (new SearchRecipesHandler)->handle(new SearchRecipesData(q: 'марг'));

    expect($result->total())->toBe(1)
        ->and($result->items()[0]->name_ru)->toBe('Маргарита');
});

it('finds recipes by name_en (case-insensitive)', function () {
    Recipe::factory()->create(['name_ru' => 'Маргарита', 'name_en' => 'Margarita']);
    Recipe::factory()->create(['name_ru' => 'Мохито', 'name_en' => 'Mojito']);

    $result = (new SearchRecipesHandler)->handle(new SearchRecipesData(q: 'MOJITO'));

    expect($result->total())->toBe(1)
        ->and($result->items()[0]->name_en)->toBe('Mojito');
});

it('filters by glass type', function () {
    Recipe::factory()->create(['glass' => 'rocks']);
    Recipe::factory()->create(['glass' => 'highball']);

    $result = (new SearchRecipesHandler)->handle(new SearchRecipesData(glass: 'rocks'));

    expect($result->total())->toBe(1);
});

it('filters by abv range', function () {
    Recipe::factory()->create(['abv' => 5.0]);
    Recipe::factory()->create(['abv' => 25.0]);
    Recipe::factory()->create(['abv' => 40.0]);

    $result = (new SearchRecipesHandler)->handle(new SearchRecipesData(abvMin: 10.0, abvMax: 30.0));

    expect($result->total())->toBe(1)
        ->and((float) $result->items()[0]->abv)->toBe(25.0);
});

it('returns non-alcoholic recipes when abvMax is 0', function () {
    Recipe::factory()->create(['abv' => 0.0]);
    Recipe::factory()->create(['abv' => 0.0]);
    Recipe::factory()->create(['abv' => 5.0]);

    $result = (new SearchRecipesHandler)->handle(new SearchRecipesData(abvMin: 0.0, abvMax: 0.0));

    expect($result->total())->toBe(2);
});

it('filters by volume range', function () {
    Recipe::factory()->create(['volume' => 50]);
    Recipe::factory()->create(['volume' => 100]);
    Recipe::factory()->create(['volume' => 300]);

    $result = (new SearchRecipesHandler)->handle(new SearchRecipesData(volMin: 60, volMax: 200));

    expect($result->total())->toBe(1)
        ->and($result->items()[0]->volume)->toBe(100);
});

it('filters by tag', function () {
    $tagged = Recipe::factory()->create();
    RecipeTag::create(['recipe_id' => $tagged->id, 'tag' => 'long']);
    Recipe::factory()->create();

    $result = (new SearchRecipesHandler)->handle(new SearchRecipesData(tag: 'long'));

    expect($result->total())->toBe(1);
});

it('returns empty paginator when no match', function () {
    Recipe::factory()->count(3)->create();

    $result = (new SearchRecipesHandler)->handle(new SearchRecipesData(q: 'невозможноеназвание'));

    expect($result->isEmpty())->toBeTrue();
});

it('returns all recipes when no filters applied', function () {
    Recipe::factory()->count(5)->create();

    $result = (new SearchRecipesHandler)->handle(new SearchRecipesData);

    expect($result->total())->toBe(5);
});

it('paginates results correctly', function () {
    Recipe::factory()->count(10)->create();

    $result = (new SearchRecipesHandler)->handle(new SearchRecipesData(page: 2, perPage: 3));

    expect($result->currentPage())->toBe(2)
        ->and(count($result->items()))->toBe(3);
});
```

- [ ] **Step 2: Запустить тест — убедиться что падает**

```bash
docker compose exec app php artisan test --filter=SearchRecipesHandlerTest
```

Ожидаемый вывод: `Class "App\Data\Search\SearchRecipesData" not found`.

- [ ] **Step 3: Создать DTO**

Создать `app/Data/Search/SearchRecipesData.php`:

```php
<?php

namespace App\Data\Search;

use Spatie\LaravelData\Data;

final class SearchRecipesData extends Data
{
    public function __construct(
        public readonly ?string $q = null,
        public readonly ?string $glass = null,
        public readonly ?float $abvMin = null,
        public readonly ?float $abvMax = null,
        public readonly ?int $volMin = null,
        public readonly ?int $volMax = null,
        public readonly ?string $tag = null,
        public readonly int $page = 1,
        public readonly int $perPage = 15,
    ) {}
}
```

- [ ] **Step 4: Создать Handler**

Создать `app/Handlers/Search/SearchRecipesHandler.php`:

```php
<?php

namespace App\Handlers\Search;

use App\Data\Search\SearchRecipesData;
use App\Models\Recipe;
use Illuminate\Pagination\LengthAwarePaginator;

final class SearchRecipesHandler
{
    public function handle(SearchRecipesData $data): LengthAwarePaginator
    {
        $query = Recipe::query()->orderBy('name_ru');

        if ($data->q !== null && $data->q !== '') {
            $query->where(function ($q) use ($data): void {
                $q->where('name_ru', 'ilike', "%{$data->q}%")
                    ->orWhere('name_en', 'ilike', "%{$data->q}%");
            });
        }

        if ($data->glass !== null) {
            $query->where('glass', $data->glass);
        }

        if ($data->abvMin !== null && $data->abvMax === 0.0) {
            $query->where('abv', 0);
        } elseif ($data->abvMin !== null && $data->abvMax !== null) {
            $query->whereBetween('abv', [$data->abvMin, $data->abvMax]);
        }

        if ($data->volMin !== null && $data->volMax !== null) {
            $query->whereBetween('volume', [$data->volMin, $data->volMax]);
        }

        if ($data->tag !== null) {
            $query->whereHas('tags', fn ($q) => $q->where('tag', $data->tag));
        }

        return $query->paginate($data->perPage, ['*'], 'page', $data->page);
    }
}
```

- [ ] **Step 5: Запустить тест — убедиться что проходит**

```bash
docker compose exec app php artisan test --filter=SearchRecipesHandlerTest
```

Ожидаемый вывод: 10 passed.

- [ ] **Step 6: Commit**

```bash
git add app/Data/Search/SearchRecipesData.php app/Handlers/Search/SearchRecipesHandler.php \
        tests/Unit/Handlers/Search/SearchRecipesHandlerTest.php
git commit --author="Claude <claude@anthropic.com>" -m "feat(bb6): SearchRecipesHandler with name/glass/abv/volume/tag filters"
```

**Handoff:** 10 unit-тестов pass; `make pint-dirty-dry` — 0 изменений.

---

## Task 3: SearchByIngredientHandler

**Files:**
- Create: `app/Data/Search/SearchByIngredientData.php`
- Create: `app/Handlers/Search/SearchByIngredientHandler.php`
- Create: `tests/Unit/Handlers/Search/SearchByIngredientHandlerTest.php`

- [ ] **Step 1: Написать падающий тест**

Создать `tests/Unit/Handlers/Search/SearchByIngredientHandlerTest.php`:

```php
<?php

use App\Data\Search\SearchByIngredientData;
use App\Handlers\Search\SearchByIngredientHandler;
use App\Models\Ingredient;
use App\Models\Recipe;
use App\Models\RecipeIngredient;

it('returns recipes containing all specified ingredients', function () {
    Ingredient::factory()->create(['id' => 'vodka']);
    Ingredient::factory()->create(['id' => 'lime_juice']);

    $both = Recipe::factory()->create();
    RecipeIngredient::create(['recipe_id' => $both->id, 'ingredient_id' => 'vodka', 'sort_order' => 1]);
    RecipeIngredient::create(['recipe_id' => $both->id, 'ingredient_id' => 'lime_juice', 'sort_order' => 2]);

    $onlyVodka = Recipe::factory()->create();
    RecipeIngredient::create(['recipe_id' => $onlyVodka->id, 'ingredient_id' => 'vodka', 'sort_order' => 1]);

    $result = (new SearchByIngredientHandler)->handle(
        new SearchByIngredientData(ingredientIds: ['vodka', 'lime_juice'])
    );

    expect($result)->toHaveCount(1)
        ->and($result->first()->id)->toBe($both->id);
});

it('returns all matching recipes for single ingredient', function () {
    Ingredient::factory()->create(['id' => 'rum']);

    $r1 = Recipe::factory()->create();
    RecipeIngredient::create(['recipe_id' => $r1->id, 'ingredient_id' => 'rum', 'sort_order' => 1]);

    $r2 = Recipe::factory()->create();
    RecipeIngredient::create(['recipe_id' => $r2->id, 'ingredient_id' => 'rum', 'sort_order' => 1]);

    $result = (new SearchByIngredientHandler)->handle(
        new SearchByIngredientData(ingredientIds: ['rum'])
    );

    expect($result)->toHaveCount(2);
});

it('returns empty collection when ingredientIds is empty', function () {
    Recipe::factory()->count(3)->create();

    $result = (new SearchByIngredientHandler)->handle(
        new SearchByIngredientData(ingredientIds: [])
    );

    expect($result)->toBeEmpty();
});

it('returns empty when no recipe has all ingredients', function () {
    Ingredient::factory()->create(['id' => 'vodka']);
    Ingredient::factory()->create(['id' => 'gin']);

    $recipe = Recipe::factory()->create();
    RecipeIngredient::create(['recipe_id' => $recipe->id, 'ingredient_id' => 'vodka', 'sort_order' => 1]);

    $result = (new SearchByIngredientHandler)->handle(
        new SearchByIngredientData(ingredientIds: ['vodka', 'gin'])
    );

    expect($result)->toBeEmpty();
});

it('orders results by name_ru', function () {
    Ingredient::factory()->create(['id' => 'rum']);

    $b = Recipe::factory()->create(['name_ru' => 'Б рецепт']);
    RecipeIngredient::create(['recipe_id' => $b->id, 'ingredient_id' => 'rum', 'sort_order' => 1]);

    $a = Recipe::factory()->create(['name_ru' => 'А рецепт']);
    RecipeIngredient::create(['recipe_id' => $a->id, 'ingredient_id' => 'rum', 'sort_order' => 1]);

    $result = (new SearchByIngredientHandler)->handle(
        new SearchByIngredientData(ingredientIds: ['rum'])
    );

    expect($result->first()->id)->toBe($a->id);
});
```

- [ ] **Step 2: Запустить тест — убедиться что падает**

```bash
docker compose exec app php artisan test --filter=SearchByIngredientHandlerTest
```

- [ ] **Step 3: Создать DTO**

Создать `app/Data/Search/SearchByIngredientData.php`:

```php
<?php

namespace App\Data\Search;

use Spatie\LaravelData\Data;

final class SearchByIngredientData extends Data
{
    public function __construct(
        /** @var string[] */
        public readonly array $ingredientIds,
    ) {}
}
```

- [ ] **Step 4: Создать Handler**

Создать `app/Handlers/Search/SearchByIngredientHandler.php`:

```php
<?php

namespace App\Handlers\Search;

use App\Data\Search\SearchByIngredientData;
use App\Models\Recipe;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

final class SearchByIngredientHandler
{
    /** @return Collection<int, Recipe> */
    public function handle(SearchByIngredientData $data): Collection
    {
        if (empty($data->ingredientIds)) {
            return collect();
        }

        $count = count($data->ingredientIds);

        $recipeIds = DB::table('recipe_ingredients')
            ->whereIn('ingredient_id', $data->ingredientIds)
            ->groupBy('recipe_id')
            ->havingRaw('COUNT(DISTINCT ingredient_id) = ?', [$count])
            ->pluck('recipe_id');

        return Recipe::whereIn('id', $recipeIds)->orderBy('name_ru')->get();
    }
}
```

- [ ] **Step 5: Запустить тест — убедиться что проходит**

```bash
docker compose exec app php artisan test --filter=SearchByIngredientHandlerTest
```

Ожидаемый вывод: 5 passed.

- [ ] **Step 6: Commit**

```bash
git add app/Data/Search/SearchByIngredientData.php app/Handlers/Search/SearchByIngredientHandler.php \
        tests/Unit/Handlers/Search/SearchByIngredientHandlerTest.php
git commit --author="Claude <claude@anthropic.com>" -m "feat(bb6): SearchByIngredientHandler — recipes with all specified ingredients"
```

**Handoff:** 5 unit-тестов pass; весь suite pass; pint-dirty-dry — 0.

---

## Task 4: GetRecipeHandler

**Files:**
- Create: `app/Handlers/Search/GetRecipeHandler.php`
- Create: `tests/Unit/Handlers/Search/GetRecipeHandlerTest.php`
- Modify: `app/Telegram/Handlers/RecipeHandler.php`

- [ ] **Step 1: Написать падающий тест**

Создать `tests/Unit/Handlers/Search/GetRecipeHandlerTest.php`:

```php
<?php

use App\Handlers\Search\GetRecipeHandler;
use App\Models\Ingredient;
use App\Models\Recipe;
use App\Models\RecipeIngredient;
use App\Models\RecipeTag;

it('returns recipe with recipeIngredients eager-loaded', function () {
    $recipe = Recipe::factory()->create();
    $ing = Ingredient::factory()->create();
    RecipeIngredient::create(['recipe_id' => $recipe->id, 'ingredient_id' => $ing->id, 'sort_order' => 1]);

    $result = (new GetRecipeHandler)->handle($recipe->id);

    expect($result)->not->toBeNull()
        ->and($result->relationLoaded('recipeIngredients'))->toBeTrue()
        ->and($result->recipeIngredients)->toHaveCount(1);
});

it('returns recipe with tags eager-loaded', function () {
    $recipe = Recipe::factory()->create();
    RecipeTag::create(['recipe_id' => $recipe->id, 'tag' => 'long']);

    $result = (new GetRecipeHandler)->handle($recipe->id);

    expect($result->relationLoaded('tags'))->toBeTrue()
        ->and($result->tags)->toHaveCount(1);
});

it('returns null for non-existent id', function () {
    $result = (new GetRecipeHandler)->handle('00000000-0000-0000-0000-000000000000');

    expect($result)->toBeNull();
});
```

- [ ] **Step 2: Запустить тест — убедиться что падает**

```bash
docker compose exec app php artisan test --filter=GetRecipeHandlerTest
```

- [ ] **Step 3: Создать Handler**

Создать `app/Handlers/Search/GetRecipeHandler.php`:

```php
<?php

namespace App\Handlers\Search;

use App\Models\Recipe;

final class GetRecipeHandler
{
    public function handle(string $id): ?Recipe
    {
        return Recipe::with('recipeIngredients', 'tags')->find($id);
    }
}
```

- [ ] **Step 4: Запустить тест — убедиться что проходит**

```bash
docker compose exec app php artisan test --filter=GetRecipeHandlerTest
```

Ожидаемый вывод: 3 passed.

- [ ] **Step 5: Внедрить GetRecipeHandler в RecipeHandler**

Полностью заменить `app/Telegram/Handlers/RecipeHandler.php`:

```php
<?php

namespace App\Telegram\Handlers;

use App\Handlers\Search\GetRecipeHandler;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

class RecipeHandler
{
    public function __construct(private GetRecipeHandler $handler) {}

    public function __invoke(Nutgram $bot, string $id): void
    {
        $recipe = $this->handler->handle($id);

        if (! $recipe) {
            $bot->answerCallbackQuery(text: 'Рецепт не найден 😔');

            return;
        }

        $keyboard = InlineKeyboardMarkup::make()
            ->addRow(
                InlineKeyboardButton::make('🔙 К поиску', callback_data: 'browse:back'),
            );

        $bot->editMessageText(
            text: $recipe->toTelegramMessage(),
            parse_mode: 'Markdown',
            reply_markup: $keyboard,
        );

        $bot->answerCallbackQuery();
    }
}
```

- [ ] **Step 6: Запустить все тесты**

```bash
docker compose exec app php artisan test
```

- [ ] **Step 7: Commit**

```bash
git add app/Handlers/Search/GetRecipeHandler.php tests/Unit/Handlers/Search/GetRecipeHandlerTest.php \
        app/Telegram/Handlers/RecipeHandler.php
git commit --author="Claude <claude@anthropic.com>" -m "feat(bb6): GetRecipeHandler + inject into RecipeHandler"
```

**Handoff:** 3 unit-тестов pass; весь suite pass; pint-dirty-dry — 0.

---

## Task 5: BrowseContext + RecipeBrowseHandler

**Files:**
- Create: `app/Services/BrowseContext.php`
- Create: `app/Telegram/Handlers/RecipeBrowseHandler.php`
- Create: `tests/Unit/Services/BrowseContextTest.php`

> Принцип: при показе результатов conversation сохраняет упорядоченный список UUID рецептов в Cache под ключом = `telegram_id` пользователя (TTL 30 мин). Один активный browse на пользователя — новый поиск перезаписывает предыдущий. Кнопки в результатах используют `recipe:browse:{telegramId}:{pos}`. `RecipeBrowseHandler` извлекает рецепт по позиции и показывает карточку с навигацией.

- [ ] **Step 1: Написать падающий тест**

Создать `tests/Unit/Services/BrowseContextTest.php`:

```php
<?php

use App\Services\BrowseContext;
use Illuminate\Support\Facades\Cache;

it('stores recipe ids under telegram_id key', function () {
    $key = (new BrowseContext)->store(['uuid-1', 'uuid-2', 'uuid-3'], 123456789);

    expect($key)->toBe('123456789')
        ->and(Cache::has('browse:123456789'))->toBeTrue();
});

it('retrieves stored ids by telegram_id key', function () {
    $ctx = new BrowseContext;
    $ids = ['uuid-a', 'uuid-b'];

    $key = $ctx->store($ids, 987654321);

    expect($ctx->get($key))->toBe($ids);
});

it('returns null for non-existent key', function () {
    expect((new BrowseContext)->get('nonexist'))->toBeNull();
});

it('overwrites previous context for same user', function () {
    $ctx = new BrowseContext;
    $ctx->store(['old-uuid'], 111);
    $ctx->store(['new-uuid'], 111);

    expect($ctx->get('111'))->toBe(['new-uuid']);
});

it('returns null after cache is cleared', function () {
    $ctx = new BrowseContext;
    $key = $ctx->store(['uuid-1'], 555);
    Cache::forget("browse:{$key}");

    expect($ctx->get($key))->toBeNull();
});
```

- [ ] **Step 2: Запустить тест — убедиться что падает**

```bash
docker compose exec app php artisan test --filter=BrowseContextTest
```

Ожидаемый вывод: `Class "App\Services\BrowseContext" not found`.

- [ ] **Step 3: Создать BrowseContext**

Создать `app/Services/BrowseContext.php`:

```php
<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

final class BrowseContext
{
    private const TTL_MINUTES = 30;

    /**
     * @param  string[]  $recipeIds
     */
    public function store(array $recipeIds, int $telegramId): string
    {
        $key = (string) $telegramId;
        Cache::put("browse:{$key}", $recipeIds, now()->addMinutes(self::TTL_MINUTES));

        return $key;
    }

    /**
     * @return string[]|null
     */
    public function get(string $key): ?array
    {
        return Cache::get("browse:{$key}");
    }
}
```

- [ ] **Step 4: Запустить тест — убедиться что проходит**

```bash
docker compose exec app php artisan test --filter=BrowseContextTest
```

Ожидаемый вывод: 5 passed.

- [ ] **Step 5: Создать RecipeBrowseHandler**

Создать `app/Telegram/Handlers/RecipeBrowseHandler.php`:

```php
<?php

namespace App\Telegram\Handlers;

use App\Handlers\Search\GetRecipeHandler;
use App\Services\BrowseContext;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

class RecipeBrowseHandler
{
    public function __construct(
        private GetRecipeHandler $recipeHandler,
        private BrowseContext $browseContext,
    ) {}

    public function __invoke(Nutgram $bot, string $browseKey, string $pos): void
    {
        $position = (int) $pos;
        $ids = $this->browseContext->get($browseKey);

        if ($ids === null) {
            $bot->answerCallbackQuery(text: '🔄 Поиск устарел, начните заново');

            return;
        }

        $id = $ids[$position] ?? null;

        if ($id === null) {
            $bot->answerCallbackQuery(text: 'Рецепт не найден 😔');

            return;
        }

        $recipe = $this->recipeHandler->handle($id);

        if (! $recipe) {
            $bot->answerCallbackQuery(text: 'Рецепт не найден 😔');

            return;
        }

        $keyboard = InlineKeyboardMarkup::make();

        $nav = [];
        if ($position > 0) {
            $nav[] = InlineKeyboardButton::make(
                '◀️ Пред.',
                callback_data: "recipe:browse:{$browseKey}:" . ($position - 1),
            );
        }
        if ($position < count($ids) - 1) {
            $nav[] = InlineKeyboardButton::make(
                '▶️ След.',
                callback_data: "recipe:browse:{$browseKey}:" . ($position + 1),
            );
        }
        if (! empty($nav)) {
            $keyboard->addRow(...$nav);
        }

        $keyboard->addRow(
            InlineKeyboardButton::make('🔙 К поиску', callback_data: 'browse:back'),
        );

        // Placeholders: Phase 3 (Order) and Phase 6 (Fork)
        $keyboard->addRow(
            InlineKeyboardButton::make('🛒 Заказать', callback_data: 'noop'),
            InlineKeyboardButton::make('🍴 Форкнуть', callback_data: 'noop'),
        );

        $bot->editMessageText(
            text: $recipe->toTelegramMessage(),
            parse_mode: 'Markdown',
            reply_markup: $keyboard,
        );

        $bot->answerCallbackQuery();
    }
}
```

- [ ] **Step 6: Написать тест RecipeBrowseHandler (бизнес-логика)**

Создать `tests/Unit/Telegram/Handlers/RecipeBrowseHandlerTest.php`:

```php
<?php

use App\Handlers\Search\GetRecipeHandler;
use App\Models\Recipe;
use App\Services\BrowseContext;
use App\Telegram\Handlers\RecipeBrowseHandler;
use Mockery\MockInterface;
use SergiX44\Nutgram\Nutgram;

function makeBrowseHandler(): RecipeBrowseHandler
{
    return app(RecipeBrowseHandler::class);
}

it('sends stale message when key not found in cache', function () {
    $bot = Mockery::mock(Nutgram::class);
    $bot->shouldReceive('answerCallbackQuery')
        ->once()
        ->with(text: '🔄 Поиск устарел, начните заново');

    $handler = new RecipeBrowseHandler(
        app(GetRecipeHandler::class),
        new BrowseContext,
    );

    $handler->__invoke($bot, '999999', '0');
});

it('sends not-found when position is out of range', function () {
    Recipe::factory()->create(); // create one recipe but store empty list
    $ctx = new BrowseContext;
    $key = $ctx->store([], 123);

    $bot = Mockery::mock(Nutgram::class);
    $bot->shouldReceive('answerCallbackQuery')
        ->once()
        ->with(text: 'Рецепт не найден 😔');

    $handler = new RecipeBrowseHandler(app(GetRecipeHandler::class), $ctx);
    $handler->__invoke($bot, $key, '0');
});

it('shows prev button only when position > 0', function () {
    $recipe = Recipe::factory()->create();
    $ctx = new BrowseContext;
    $key = $ctx->store([$recipe->id, $recipe->id], 456);

    $bot = Mockery::mock(Nutgram::class);
    $bot->shouldReceive('editMessageText')->once()->withArgs(function ($args) {
        $markup = $args['reply_markup'];
        $rows = $markup->inline_keyboard;
        // row 0 = nav, row 1 = back, row 2 = placeholders
        return count($rows[0]) === 1 && str_contains($rows[0][0]->callback_data, 'recipe:browse');
    })->andReturnNull();
    $bot->shouldReceive('answerCallbackQuery')->once();

    $handler = new RecipeBrowseHandler(app(GetRecipeHandler::class), $ctx);
    $handler->__invoke($bot, $key, '1'); // position 1 → only ◀️ Пред. (no ▶️)
});
```

Запустить:

```bash
docker compose exec app php artisan test --filter=RecipeBrowseHandlerTest
```

> Эти тесты используют Mockery для изоляции Nutgram-транспорта. Если тест `shows prev button` окажется хрупким из-за структуры Nutgram-объектов — допустимо его пропустить и ограничиться первыми двумя (stale + out-of-range).

- [ ] **Step 7: Запустить все тесты**

```bash
docker compose exec app php artisan test
```

- [ ] **Step 8: Commit**

```bash
git add app/Services/BrowseContext.php app/Telegram/Handlers/RecipeBrowseHandler.php \
        tests/Unit/Services/BrowseContextTest.php \
        tests/Unit/Telegram/Handlers/RecipeBrowseHandlerTest.php
git commit --author="Claude <claude@anthropic.com>" -m "feat(bb6): BrowseContext + RecipeBrowseHandler with prev/next navigation"
```

**Handoff:** 5 unit-тестов BrowseContextTest pass; весь suite pass; pint-dirty-dry — 0.

---

## Task 6: Рефакторинг Conversations + активация Telegram-маршрутов

**Files:**
- Modify: `app/Telegram/Conversations/SearchByNameConversation.php`
- Modify: `app/Telegram/Conversations/FilterConversation.php`
- Modify: `app/Telegram/Conversations/SearchByIngredientConversation.php`
- Modify: `routes/telegram.php`

> **Важно:** Nutgram сериализует `protected` свойства между шагами. Сервисные классы (Handlers, BrowseContext) нельзя хранить как свойства — используй `app(ClassName::class)` внутри методов.

- [ ] **Step 1: Рефакторинг SearchByNameConversation**

Полностью заменить `app/Telegram/Conversations/SearchByNameConversation.php`:

```php
<?php

namespace App\Telegram\Conversations;

use App\Data\Search\SearchRecipesData;
use App\Handlers\Search\SearchRecipesHandler;
use App\Services\BrowseContext;
use SergiX44\Nutgram\Conversations\Conversation;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

class SearchByNameConversation extends Conversation
{
    protected const PER_PAGE = 5;

    protected ?string $query = null;

    protected int $page = 1;

    public function start(Nutgram $bot): void
    {
        $bot->sendMessage('🔍 Введите название коктейля (или его часть):');
        $this->next('handleQuery');
    }

    public function handleQuery(Nutgram $bot): void
    {
        $this->query = trim($bot->message()->text ?? '');

        if (empty($this->query)) {
            $bot->sendMessage('❌ Введите хотя бы один символ.');
            $this->next('handleQuery');

            return;
        }

        $this->page = 1;
        $this->showResults($bot);
        $this->end();
    }

    private function showResults(Nutgram $bot): void
    {
        $data = new SearchRecipesData(
            q: $this->query,
            page: $this->page,
            perPage: self::PER_PAGE,
        );

        $results = app(SearchRecipesHandler::class)->handle($data);

        if ($results->isEmpty()) {
            $bot->sendMessage(
                "😔 По запросу *\"{$this->query}\"* ничего не найдено.\n\nПопробуй другое название.",
                parse_mode: 'Markdown',
            );

            return;
        }

        $browseKey = app(BrowseContext::class)->store($results->pluck('id')->all(), $bot->userId());

        $text = "🔍 Результаты поиска: *\"{$this->query}\"*\n";
        $text .= "Найдено: {$results->total()} | Страница {$this->page}/{$results->lastPage()}\n\n";

        $keyboard = InlineKeyboardMarkup::make();

        foreach ($results->values() as $pos => $recipe) {
            $abv = $recipe->abv ? " {$recipe->abv}%" : '';
            $vol = $recipe->volume ? " {$recipe->volume}мл" : '';
            $text .= "• {$recipe->name_ru}{$abv}{$vol}\n";

            $keyboard->addRow(
                InlineKeyboardButton::make(
                    "🍹 {$recipe->name_ru}",
                    callback_data: "recipe:browse:{$browseKey}:{$pos}",
                ),
            );
        }

        $nav = [];
        if ($results->currentPage() > 1) {
            $nav[] = InlineKeyboardButton::make('◀️', callback_data: 'search:page:' . ($this->page - 1) . ":{$this->query}");
        }
        if ($results->hasMorePages()) {
            $nav[] = InlineKeyboardButton::make('▶️', callback_data: 'search:page:' . ($this->page + 1) . ":{$this->query}");
        }
        if (! empty($nav)) {
            $keyboard->addRow(...$nav);
        }

        $bot->sendMessage(text: $text, parse_mode: 'Markdown', reply_markup: $keyboard);
    }
}
```

- [ ] **Step 2: Рефакторинг FilterConversation**

В `app/Telegram/Conversations/FilterConversation.php` заменить только метод `showResults` и убрать `use App\Models\Recipe;` из imports:

```php
    private function showResults(Nutgram $bot): void
    {
        $data = new \App\Data\Search\SearchRecipesData(
            glass: $this->glass,
            abvMin: $this->abvMin,
            abvMax: $this->abvMax,
            volMin: $this->volMin,
            volMax: $this->volMax,
            tag: $this->tag,
            perPage: 15,
        );

        $results = app(\App\Handlers\Search\SearchRecipesHandler::class)->handle($data);

        if ($results->isEmpty()) {
            $bot->sendMessage('😔 По выбранным фильтрам ничего не найдено. Попробуйте другие параметры.');

            return;
        }

        $browseKey = app(\App\Services\BrowseContext::class)->store($results->pluck('id')->all(), $bot->userId());

        $text = "🎛 *Результаты фильтрации:*\nНайдено: {$results->total()} рецептов\n\n";
        $keyboard = InlineKeyboardMarkup::make();

        foreach ($results->values() as $pos => $recipe) {
            $abv = $recipe->abv ? " {$recipe->abv}%" : '';
            $vol = $recipe->volume ? " {$recipe->volume}мл" : '';
            $text .= "• {$recipe->name_ru}{$abv}{$vol}\n";
            $keyboard->addRow(
                InlineKeyboardButton::make(
                    "🍹 {$recipe->name_ru}",
                    callback_data: "recipe:browse:{$browseKey}:{$pos}",
                ),
            );
        }

        $bot->sendMessage(text: $text, parse_mode: 'Markdown', reply_markup: $keyboard);
    }
```

- [ ] **Step 3: Рефакторинг SearchByIngredientConversation — фикс callback + BrowseContext**

Полностью заменить `app/Telegram/Conversations/SearchByIngredientConversation.php`:

```php
<?php

namespace App\Telegram\Conversations;

use App\Data\Search\SearchByIngredientData;
use App\Handlers\Search\SearchByIngredientHandler;
use App\Models\Ingredient;
use App\Services\BrowseContext;
use SergiX44\Nutgram\Conversations\Conversation;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

class SearchByIngredientConversation extends Conversation
{
    /** @var string[] */
    protected array $selectedIngredients = [];

    public function start(Nutgram $bot): void
    {
        $bot->sendMessage(
            "🧪 *Поиск по ингредиентам*\n\n"
            . "Введите название ингредиента (на русском или английском, например: `водка`, `bourbon`, `lime juice`).\n\n"
            . "Можно добавить несколько — бот найдёт коктейли, в которых *все* они есть.\n\n"
            . 'Чтобы начать поиск, напишите /done',
            parse_mode: 'Markdown',
        );
        $this->next('handleIngredient');
    }

    public function handleIngredient(Nutgram $bot): void
    {
        // Обработка нажатия кнопки выбора ингредиента из нескольких совпадений
        $callbackData = $bot->callbackQuery()?->data ?? '';
        if (str_starts_with($callbackData, 'ing:add:')) {
            $ingId = substr($callbackData, 8);
            $this->selectedIngredients[] = $ingId;
            $list = implode(', ', $this->selectedIngredients);
            $bot->answerCallbackQuery();
            $bot->sendMessage(
                "✅ Добавлен: *{$ingId}*\n\nТекущий список: `{$list}`\n\nДобавьте ещё или напишите /done",
                parse_mode: 'Markdown',
            );
            $this->next('handleIngredient');

            return;
        }

        $text = trim($bot->message()->text ?? '');

        if ($text === '/done' || $text === 'done') {
            $this->showResults($bot);
            $this->end();

            return;
        }

        if ($text === '/clear') {
            $this->selectedIngredients = [];
            $bot->sendMessage('✅ Список очищен. Введите ингредиент:');
            $this->next('handleIngredient');

            return;
        }

        if (empty($text)) {
            $this->next('handleIngredient');

            return;
        }

        $found = Ingredient::where('id', 'ilike', "%{$text}%")
            ->orWhere('name_en', 'ilike', "%{$text}%")
            ->orWhere('name_ru', 'ilike', "%{$text}%")
            ->take(5)
            ->get();

        if ($found->isEmpty()) {
            $bot->sendMessage(
                "❌ Ингредиент *\"{$text}\"* не найден. Попробуйте другое название.",
                parse_mode: 'Markdown',
            );
            $this->next('handleIngredient');

            return;
        }

        if ($found->count() === 1) {
            $ing = $found->first();
            $this->selectedIngredients[] = $ing->id;
            $list = implode(', ', $this->selectedIngredients);
            $bot->sendMessage(
                "✅ Добавлен: *{$ing->id}*\n\nТекущий список: `{$list}`\n\nДобавьте ещё или напишите /done",
                parse_mode: 'Markdown',
            );
        } else {
            $keyboard = InlineKeyboardMarkup::make();
            foreach ($found as $ing) {
                $keyboard->addRow(
                    InlineKeyboardButton::make(
                        $ing->id . ($ing->name_ru ? " ({$ing->name_ru})" : ''),
                        callback_data: "ing:add:{$ing->id}",
                    ),
                );
            }
            $bot->sendMessage('Уточните ингредиент:', reply_markup: $keyboard);
        }

        $this->next('handleIngredient');
    }

    private function showResults(Nutgram $bot): void
    {
        if (empty($this->selectedIngredients)) {
            $bot->sendMessage('❌ Не выбрано ни одного ингредиента.');

            return;
        }

        $data = new SearchByIngredientData(ingredientIds: $this->selectedIngredients);
        $recipes = app(SearchByIngredientHandler::class)->handle($data);
        $list = implode(', ', $this->selectedIngredients);

        if ($recipes->isEmpty()) {
            $bot->sendMessage(
                "😔 Нет коктейлей со *всеми* ингредиентами: `{$list}`\n\nПопробуйте убрать один из ингредиентов.",
                parse_mode: 'Markdown',
            );

            return;
        }

        $browseKey = app(BrowseContext::class)->store($recipes->pluck('id')->all(), $bot->userId());
        $text = "🧪 Ингредиенты: `{$list}`\nНайдено коктейлей: *{$recipes->count()}*\n\n";
        $keyboard = InlineKeyboardMarkup::make();

        foreach ($recipes->take(10)->values() as $pos => $recipe) {
            $abv = $recipe->abv ? " {$recipe->abv}%" : '';
            $text .= "• {$recipe->name_ru}{$abv}\n";
            $keyboard->addRow(
                InlineKeyboardButton::make(
                    "🍹 {$recipe->name_ru}",
                    callback_data: "recipe:browse:{$browseKey}:{$pos}",
                ),
            );
        }

        if ($recipes->count() > 10) {
            $text .= "\n_...и ещё " . ($recipes->count() - 10) . ' рецептов_';
        }

        $bot->sendMessage(text: $text, parse_mode: 'Markdown', reply_markup: $keyboard);
    }
}
```

- [ ] **Step 4: Обновить routes/telegram.php**

Полностью заменить содержимое `routes/telegram.php`:

```php
<?php

/** @var Nutgram $bot */

use App\Actions\Inventory\InventoryAction;
use App\Actions\Inventory\RemoveInventoryAction;
use App\Middleware\CanManageMiddleware;
use App\Telegram\Conversations\AddInventoryConversation;
use App\Telegram\Conversations\FilterConversation;
use App\Telegram\Conversations\SearchByIngredientConversation;
use App\Telegram\Conversations\SearchByNameConversation;
use App\Telegram\Handlers\RecipeBrowseHandler;
use App\Telegram\Handlers\RecipeHandler;
use App\Telegram\Handlers\StartHandler;
use App\Telegram\Middleware\AuthenticateTelegramUser;
use Illuminate\Auth\Access\AuthorizationException;
use SergiX44\Nutgram\Nutgram;

$bot->middleware(AuthenticateTelegramUser::class);

$bot->onException(AuthorizationException::class, function (Nutgram $bot): void {
    $bot->answerCallbackQuery(text: '🚫 Нет доступа', show_alert: true);
});

$bot->onCommand('start', StartHandler::class)->description('Главное меню');
$bot->onCommand('inventory', [InventoryAction::class, 'fromTelegram'])->description('Инвентарь бара');

$bot->onCallbackQueryData('inventory:show', [InventoryAction::class, 'fromTelegram']);

$bot->group(function (Nutgram $bot): void {
    $bot->onCallbackQueryData('inventory:add', fn (Nutgram $bot) => AddInventoryConversation::begin($bot));
    $bot->onCallbackQueryData('inventory:remove:{id}', [RemoveInventoryAction::class, 'fromTelegram']);
})->middleware(CanManageMiddleware::class);

// Phase 2: Search
$bot->onCallbackQueryData('cmd:search', fn (Nutgram $bot) => SearchByNameConversation::begin($bot));
$bot->onCallbackQueryData('cmd:ingredients', fn (Nutgram $bot) => SearchByIngredientConversation::begin($bot));
$bot->onCallbackQueryData('cmd:filter', fn (Nutgram $bot) => FilterConversation::begin($bot));

// Phase 2: Recipe browsing
$bot->onCallbackQueryData('recipe:browse:{browseKey}:{pos}', RecipeBrowseHandler::class);
$bot->onCallbackQueryData('recipe:show:{id}', RecipeHandler::class);
$bot->onCallbackQueryData('browse:back', StartHandler::class);

$bot->onCallbackQueryData('noop', fn (Nutgram $bot) => $bot->answerCallbackQuery());
```

- [ ] **Step 5: Запустить все тесты**

```bash
docker compose exec app php artisan test
```

Ожидаемый вывод: все тесты pass.

- [ ] **Step 6: Linter**

```bash
make pint-dirty-dry
```

Ожидаемый вывод: 0 изменений. Если есть — исправить: `make pint-dirty`.

- [ ] **Step 7: Commit**

```bash
git add app/Telegram/Conversations/SearchByNameConversation.php \
        app/Telegram/Conversations/FilterConversation.php \
        app/Telegram/Conversations/SearchByIngredientConversation.php \
        routes/telegram.php
git commit --author="Claude <claude@anthropic.com>" -m "feat(bb6): activate search routes, refactor conversations + fix ing:add callback"
```

**Handoff:** весь suite pass; pint-dirty-dry — 0; routes/telegram.php содержит 6 новых маршрутов (cmd:search, cmd:ingredients, cmd:filter, recipe:browse, recipe:show, browse:back).

---

## Task 7: HTTP API — GET /api/recipes/{id}

**Files:**
- Create: `app/Actions/Search/GetRecipeAction.php`
- Create: `tests/Feature/Actions/Search/GetRecipeActionTest.php`
- Modify: `routes/api.php`

- [ ] **Step 1: Написать падающий тест**

Создать `tests/Feature/Actions/Search/GetRecipeActionTest.php`:

```php
<?php

use App\Models\Ingredient;
use App\Models\Recipe;
use App\Models\RecipeIngredient;
use App\Models\RecipeTag;

it('GET /api/recipes/{id} returns recipe with ingredients and tags', function () {
    $recipe = Recipe::factory()->create(['name_ru' => 'Маргарита', 'name_en' => 'Margarita']);
    $ing = Ingredient::factory()->create();
    RecipeIngredient::create(['recipe_id' => $recipe->id, 'ingredient_id' => $ing->id, 'sort_order' => 1]);
    RecipeTag::create(['recipe_id' => $recipe->id, 'tag' => 'long']);

    $this->getJson("/api/recipes/{$recipe->id}")
        ->assertOk()
        ->assertJsonFragment(['name_ru' => 'Маргарита'])
        ->assertJsonStructure(['id', 'name_ru', 'name_en', 'abv', 'volume', 'glass', 'recipe_ingredients', 'tags']);
});

it('GET /api/recipes/{id} returns 404 for unknown id', function () {
    $this->getJson('/api/recipes/00000000-0000-0000-0000-000000000000')
        ->assertNotFound();
});
```

- [ ] **Step 2: Запустить тест — убедиться что падает**

```bash
docker compose exec app php artisan test --filter=GetRecipeActionTest
```

Ожидаемый вывод: 404 (маршрут не зарегистрирован).

- [ ] **Step 3: Создать Action**

Создать `app/Actions/Search/GetRecipeAction.php`:

```php
<?php

namespace App\Actions\Search;

use App\Handlers\Search\GetRecipeHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class GetRecipeAction
{
    public function __construct(private GetRecipeHandler $handler) {}

    public function __invoke(Request $request, string $id): JsonResponse
    {
        $recipe = $this->handler->handle($id);

        if (! $recipe) {
            return response()->json(['message' => 'Рецепт не найден'], 404);
        }

        return response()->json($recipe);
    }
}
```

- [ ] **Step 4: Зарегистрировать маршрут в routes/api.php**

В конец `routes/api.php` добавить:

```php
use App\Actions\Search\GetRecipeAction;
use App\Actions\Search\SearchRecipesAction;

Route::prefix('recipes')->group(function () {
    Route::get('/{id}', GetRecipeAction::class);
});
```

Импорты добавить в начало файла.

- [ ] **Step 5: Запустить тест — убедиться что проходит**

```bash
docker compose exec app php artisan test --filter=GetRecipeActionTest
```

Ожидаемый вывод: 2 passed.

- [ ] **Step 6: Commit**

```bash
git add app/Actions/Search/GetRecipeAction.php tests/Feature/Actions/Search/GetRecipeActionTest.php \
        routes/api.php
git commit --author="Claude <claude@anthropic.com>" -m "feat(bb6): GET /api/recipes/{id} endpoint"
```

**Handoff:** 2 feature-теста pass; весь suite pass; pint-dirty-dry — 0.

---

## Task 8: HTTP API — GET /api/recipes

**Files:**
- Create: `app/Actions/Search/SearchRecipesAction.php`
- Create: `tests/Feature/Actions/Search/SearchRecipesActionTest.php`
- Modify: `routes/api.php`

- [ ] **Step 1: Написать падающий тест**

Создать `tests/Feature/Actions/Search/SearchRecipesActionTest.php`:

```php
<?php

use App\Models\Recipe;
use App\Models\RecipeTag;

it('GET /api/recipes returns all recipes without filters', function () {
    Recipe::factory()->count(3)->create();

    $this->getJson('/api/recipes')
        ->assertOk()
        ->assertJsonCount(3, 'data');
});

it('GET /api/recipes?q= filters by name', function () {
    Recipe::factory()->create(['name_ru' => 'Маргарита', 'name_en' => 'Margarita']);
    Recipe::factory()->create(['name_ru' => 'Мохито', 'name_en' => 'Mojito']);

    $this->getJson('/api/recipes?q=марг')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonFragment(['name_ru' => 'Маргарита']);
});

it('GET /api/recipes?glass= filters by glass type', function () {
    Recipe::factory()->create(['glass' => 'rocks']);
    Recipe::factory()->create(['glass' => 'highball']);
    Recipe::factory()->create(['glass' => 'highball']);

    $this->getJson('/api/recipes?glass=highball')
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

it('GET /api/recipes?abv_min=&abv_max= filters by ABV range', function () {
    Recipe::factory()->create(['abv' => 5.0]);
    Recipe::factory()->create(['abv' => 25.0]);
    Recipe::factory()->create(['abv' => 40.0]);

    $this->getJson('/api/recipes?abv_min=10&abv_max=30')
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

it('GET /api/recipes?tags= filters by tag', function () {
    $tagged = Recipe::factory()->create();
    RecipeTag::create(['recipe_id' => $tagged->id, 'tag' => 'long']);
    Recipe::factory()->create();

    $this->getJson('/api/recipes?tags=long')
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

it('GET /api/recipes returns paginated response structure', function () {
    Recipe::factory()->count(5)->create();

    $this->getJson('/api/recipes?per_page=2')
        ->assertOk()
        ->assertJsonStructure(['data', 'current_page', 'last_page', 'total', 'per_page']);
});
```

- [ ] **Step 2: Запустить тест — убедиться что падает**

```bash
docker compose exec app php artisan test --filter=SearchRecipesActionTest
```

Ожидаемый вывод: 404 (маршрут не зарегистрирован).

- [ ] **Step 3: Создать Action**

Создать `app/Actions/Search/SearchRecipesAction.php`:

```php
<?php

namespace App\Actions\Search;

use App\Data\Search\SearchRecipesData;
use App\Handlers\Search\SearchRecipesHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class SearchRecipesAction
{
    public function __construct(private SearchRecipesHandler $handler) {}

    public function __invoke(Request $request): JsonResponse
    {
        $data = new SearchRecipesData(
            q: $request->string('q')->value() ?: null,
            glass: $request->string('glass')->value() ?: null,
            abvMin: $request->filled('abv_min') ? (float) $request->input('abv_min') : null,
            abvMax: $request->filled('abv_max') ? (float) $request->input('abv_max') : null,
            tag: $request->string('tags')->value() ?: null,
            page: (int) $request->input('page', 1),
            perPage: (int) $request->input('per_page', 15),
        );

        return response()->json($this->handler->handle($data));
    }
}
```

- [ ] **Step 4: Дополнить маршруты в routes/api.php**

```php
Route::prefix('recipes')->group(function () {
    Route::get('/', SearchRecipesAction::class);
    Route::get('/{id}', GetRecipeAction::class);
});
```

- [ ] **Step 5: Запустить тест — убедиться что проходит**

```bash
docker compose exec app php artisan test --filter=SearchRecipesActionTest
```

Ожидаемый вывод: 6 passed.

- [ ] **Step 6: Запустить полный suite**

```bash
docker compose exec app php artisan test
```

Ожидаемый вывод: все тесты pass.

- [ ] **Step 7: Linter**

```bash
make pint-dirty-dry
```

- [ ] **Step 8: Commit**

```bash
git add app/Actions/Search/SearchRecipesAction.php tests/Feature/Actions/Search/SearchRecipesActionTest.php \
        routes/api.php
git commit --author="Claude <claude@anthropic.com>" -m "feat(bb6): GET /api/recipes search endpoint with filters"
```

- [ ] **Step 9: Обновить файл знаний**

В `.agents/knowledge/codebase.md`:
- Phase 2 → ✅ Готово
- Добавить `BrowseContext` в список сервисов
- Добавить новые маршруты (recipe:browse, browse:back, cmd:search, cmd:ingredients, cmd:filter)
- Добавить HTTP-маршруты /api/recipes

- [ ] **Step 10: Открыть PR**

```bash
gh pr create \
  --title "bb6: recipe search — Telegram + HTTP API" \
  --body "$(cat <<'EOF'
## Summary
- Activated recipe search conversations (by name, ingredients, filters)
- Extracted search logic into testable Handlers (SearchRecipesHandler, SearchByIngredientHandler, GetRecipeHandler)
- Added BrowseContext service for prev/next navigation between search results
- Added RecipeBrowseHandler with navigation buttons + Order/Fork placeholders
- Fixed ing:add:{id} callback handling in SearchByIngredientConversation
- Added HTTP API: GET /api/recipes (with filters) and GET /api/recipes/{id}
EOF
)"
```

**Handoff (финальный):** весь suite pass; pint-dirty-dry — 0; `.agents/knowledge/codebase.md` обновлён; PR открыт — ссылка в отчёте.

---

## Примечания

**Кнопка «Заказать»:** плейсхолдер (`noop`) до Phase 3 — когда появится `BarSession`, кнопка заменится логикой проверки `BarSession::activeExists() && recipe->allIngredientsInInventory()`.

**Кнопка «Форкнуть»:** плейсхолдер (`noop`) до Phase 6 — когда появится `ForkCocktailConversation`.

**«К поиску»:** нажатие показывает главное меню (StartHandler). В Phase 3+ можно улучшить до реального возврата к результатам.
