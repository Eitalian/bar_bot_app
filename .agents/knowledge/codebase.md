# Codebase Knowledge Base

> **Для агентов:** Прочитай этот файл **перед началом** любой задачи разработки.
> После завершения работы **обнови** этот файл: добавь новые паттерны, статусы реализации, известные особенности.
> Не дублируй то, что уже есть в `CLAUDE.md` — только операционные знания, примеры кода, статус фаз.

---

## Статус реализации фаз

| Фаза | Описание | Статус |
|---|---|---|
| 1 | Инвентарь ингредиентов (Telegram + HTTP API) | ✅ Готово |
| 1.1 | Роли и контроль доступа (guest/bartender/owner) | ✅ Готово |
| 2 | Поиск рецептов (Telegram + HTTP API) | 🔄 В разработке |
| 3 | Бар-сессии | ⏳ Не начато |
| 3.1 | Заказы коктейлей | ⏳ Не начато |
| 4 | Избранное и оценки | ⏳ Не начато |
| 5 | Загрузка фото | ⏳ Не начато |
| 6 | Форк коктейля | ⏳ Не начато |

---

## Архитектурный паттерн: Action / Handler / DTO

**Правило:** каждая фича = один Action (транспортный адаптер) + один Handler (бизнес-логика) + один DTO.

### Handler (чистая логика, без транспорта)

```php
// app/Handlers/Inventory/ListInventoryHandler.php
final class ListInventoryHandler
{
    /** @return Collection<int, Inventory> */
    public function handle(): Collection
    {
        return Inventory::with('ingredient')->orderBy('id')->get();
    }
}
```

### DTO (spatie/laravel-data)

```php
// app/Data/Inventory/AddInventoryData.php
final class AddInventoryData extends Data
{
    public function __construct(
        public readonly string $ingredientId,
        public readonly ?float $quantity,
        public readonly ?string $unit,
    ) {}
}
```

### Action (HTTP + Telegram в одном классе)

```php
// app/Actions/Inventory/InventoryAction.php
final class InventoryAction
{
    public function __construct(private ListInventoryHandler $handler) {}

    // HTTP: Route::get('/inventory', InventoryAction::class)
    public function __invoke(Request $request): JsonResponse
    {
        return response()->json($this->handler->handle()->load('ingredient'));
    }

    // Telegram: $bot->onCommand('inventory', [InventoryAction::class, 'fromTelegram'])
    public function fromTelegram(Nutgram $bot): void
    {
        $items = $this->handler->handle();
        $response = new InventoryListResponse($items);
        $bot->sendMessage(text: $response->text(), parse_mode: 'Markdown', reply_markup: $response->keyboard());
    }
}
```

### Bus-паттерн для команд (Add/Remove)

Actions для мутирующих операций диспатчат через Laravel Bus:

```php
// app/Actions/Inventory/AddInventoryAction.php
$item = Bus::dispatch(new AddInventoryData(...));
```

Маппинг Data → Handler регистрируется в `app/Providers/BusServiceProvider.php`:

```php
$this->app->make(Dispatcher::class)->map([
    AddInventoryData::class => AddInventoryHandler::class,
    RemoveInventoryData::class => RemoveInventoryHandler::class,
]);
```

**Query-handlers** (поиск, список) НЕ используют Bus — внедряются напрямую через DI.

---

## Nutgram: особенности Conversations

Conversations сериализуются между шагами. `protected` свойства хранят состояние между шагами (сохраняются в Redis/кэше). **Классы сервисов (handlers) нельзя хранить в `protected` свойствах** — они не сериализуемы.

**Паттерн для вызова handler из Conversation:**

```php
// Правильно: разрешать через app() внутри метода
private function showResults(Nutgram $bot): void
{
    $result = app(SearchRecipesHandler::class)->handle($data);
}

// Неправильно: хранить handler как protected свойство
protected SearchRecipesHandler $handler; // сломается после десериализации
```

**Структура шагов:**

```php
class MyConversation extends Conversation
{
    protected ?string $state = null; // ✅ scalar — сериализуется

    public function start(Nutgram $bot): void
    {
        $bot->sendMessage('Введите что-нибудь:');
        $this->next('handleInput'); // очередь следующего шага
    }

    public function handleInput(Nutgram $bot): void
    {
        $this->state = $bot->message()->text;
        // ...
        $this->end(); // завершить conversation
    }
}
```

**Запуск conversation из callback:**

```php
// routes/telegram.php
$bot->onCallbackQueryData('cmd:search', fn(Nutgram $bot) => SearchByNameConversation::begin($bot));
```

---

## Telegram-маршруты (routes/telegram.php)

### Активные маршруты (после Phase 2)

```
Global middleware: AuthenticateTelegramUser (создаёт User из telegram_id, логинит через Auth::login)

onCommand('start')       → StartHandler
onCommand('inventory')   → InventoryAction::fromTelegram
onCallbackQueryData('inventory:show')   → InventoryAction::fromTelegram

Group [CanManageMiddleware]:
  onCallbackQueryData('inventory:add')        → AddInventoryConversation::begin
  onCallbackQueryData('inventory:remove:{id}') → RemoveInventoryAction::fromTelegram

onCallbackQueryData('noop')         → answerCallbackQuery (no-op)

// Phase 2 (активируются в Task 5):
onCallbackQueryData('cmd:search')      → SearchByNameConversation::begin
onCallbackQueryData('cmd:ingredients') → SearchByIngredientConversation::begin
onCallbackQueryData('cmd:filter')      → FilterConversation::begin
onCallbackQueryData('recipe:show:{id}') → RecipeHandler
```

### Кнопки главного меню (StartHandler)

```
🔍 Поиск по названию  → callback_data: 'cmd:search'
🧪 По ингредиентам    → callback_data: 'cmd:ingredients'
🎛 Фильтры            → callback_data: 'cmd:filter'
📦 Инвентарь          → callback_data: 'inventory:show'
```

---

## HTTP API-маршруты (routes/api.php)

```
Middleware 'auth.telegram': читает ?telegram_id= из query params, логинит пользователя.

GET    /api/inventory              → InventoryAction
POST   /api/inventory              → AddInventoryAction     [+CanManageMiddleware]
DELETE /api/inventory/{id}         → RemoveInventoryAction  [+CanManageMiddleware]

// Phase 2 (добавляются в Task 6-7):
GET    /api/recipes                → SearchRecipesAction    (без auth)
GET    /api/recipes/{id}           → GetRecipeAction        (без auth)
```

**Как работает `auth.telegram` middleware**: ищет `User` по `telegram_id` из query-параметра. Если не найден → 404. Используется в тестах как `?telegram_id={$user->telegram_id}`.

---

## Модели и связи

### Recipe

```php
// String UUID PK (не incrementing)
// Fillable: id, name_ru, name_en, description, instructions, glass, abv, volume, icon, photo, taste_tags
// Casts: taste_tags→array, abv→float, volume→integer
// HasFactory: НЕТ до Phase 2 (RecipeFactory создаётся в Task 1 Phase 2)

$recipe->recipeIngredients() // HasMany RecipeIngredient, orderBy('sort_order')
$recipe->ingredients()       // BelongsToMany Ingredient (через recipe_ingredients)
$recipe->tags()              // HasMany RecipeTag
$recipe->toTelegramMessage() // форматирует карточку рецепта для Telegram Markdown
```

### Inventory (таблица: bar_inventory)

```php
// Fillable: ingredient_id, quantity (float|null), unit (string|null)
// НЕТ user_id — инвентарь общий для всего бара (не per-user)
$inventory->ingredient() // BelongsTo Ingredient
```

### User

```php
// Fillable: telegram_id, first_name, username, role
// Casts: telegram_id→integer, role→UserRole enum
// Implements Authenticatable (без паролей и токенов)
$user->inventory() // HasMany Inventory
```

### UserRole enum

```php
// app/Enums/UserRole.php
UserRole::Guest     // canManage() → false (default для новых пользователей)
UserRole::Bartender // canManage() → true
UserRole::Owner     // canManage() → true
```

### RecipeIngredient

```php
// $timestamps = false
// Fillable: recipe_id, ingredient_id, amount, unit, note, sort_order
```

---

## Авторизация

**Gate `can-manage`**: `Gate::define('can-manage', fn(User $user) => $user->role->canManage())`

**CanManageMiddleware**: работает для HTTP (через `handle(Request)`) и для Telegram (через `__invoke(Nutgram)`). При 403 в Telegram → `answerCallbackQuery(text: '🚫 Нет доступа')` через `onException(AuthorizationException::class, ...)`.

---

## Паттерны тестирования

### Unit-тест Handler (с БД через factory)

```php
// tests/Unit/Handlers/Inventory/ListInventoryHandlerTest.php
it('returns all inventory items', function () {
    Inventory::factory()->count(3)->create();

    $result = (new ListInventoryHandler)->handle();

    expect($result)->toHaveCount(3);
});

it('eager loads ingredient relation', function () {
    $ingredient = Ingredient::factory()->create();
    Inventory::factory()->create(['ingredient_id' => $ingredient->id]);

    $result = (new ListInventoryHandler)->handle();

    expect($result->first()->relationLoaded('ingredient'))->toBeTrue();
});
```

### Feature-тест Action (HTTP)

```php
// tests/Feature/Actions/Inventory/InventoryActionTest.php
it('GET /api/inventory returns all items', function () {
    Inventory::factory()->count(3)->create();
    $user = User::factory()->create();

    $this->getJson("/api/inventory?telegram_id={$user->telegram_id}")
        ->assertOk()
        ->assertJsonCount(3);
});

it('POST /api/inventory returns 403 for guest', function () {
    $user = User::factory()->create(); // default role = Guest
    $ingredient = Ingredient::factory()->create();

    $this->postJson('/api/inventory', [
        'telegram_id'   => $user->telegram_id,
        'ingredient_id' => $ingredient->id,
    ])->assertForbidden();
});
```

### Фабрики

```php
User::factory()->create()           // role = Guest
User::factory()->bartender()->create() // role = Bartender
User::factory()->owner()->create()     // role = Owner

Ingredient::factory()->create(['id' => 'vodka']) // id — строка (slug)
Inventory::factory()->create(['ingredient_id' => $ing->id])

// Recipe::factory() — добавляется в Phase 2, Task 1
Recipe::factory()->create(['name_ru' => 'Маргарита', 'glass' => 'margarita'])
Recipe::factory()->nonAlcoholic()->create() // abv = 0.0
```

---

## Известные особенности и ограничения

1. **`browse:back` callback** (в RecipeHandler) — кнопка «🔙 К поиску» отправляет `callback_data: 'browse:back'`. Маршрут будет зарегистрирован в Task 6 Phase 2. До тех пор Nutgram игнорирует необработанные callback queries (не крашится).

2. **`ing:add:{id}` callbacks** (SearchByIngredientConversation) — когда найдено несколько ингредиентов, conversation показывает кнопки с `ing:add:{id}`. Шаг `handleIngredient` читает `$bot->message()->text`, а не callback_data — при нажатии кнопки `text` будет `null`, поиск выполнится по пустой строке. Известный баг, не мешает основному флоу.

3. **Инвентарь без `user_id`** — `bar_inventory` не привязан к пользователю. Инвентарь общий для всего бара. `InventoryAction::fromTelegram` и `ListInventoryHandler` возвращают всё без фильтрации.

4. **`recipes` таблица** — уже заполнена 203 рецептами через `bar:import`. При `migration:fresh` данные теряются — нужен повторный `bar:import`.

5. **`taste_tags`** — зарезервированная колонка (JSON array). Команда `bar:taste:fill` не реализована.

6. **`Recipe` без `user_id`** — встроенные рецепты не принадлежат пользователям. `user_id` появится в Phase 6 (форк).
