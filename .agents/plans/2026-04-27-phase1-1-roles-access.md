# Phase 1.1 — Роли и контроль доступа

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Добавить роли `guest`/`bartender`/`owner` в модель User и ограничить добавление/удаление инвентаря только для `bartender`/`owner`.

**Architecture:** PostgreSQL ENUM `user_role_type` в таблице `users`. PHP `UserRole` enum с методом `canManage()`. Gate `can-manage` в `AuthorizationServiceProvider`. Единый `CanManageMiddleware` с методами `handle()` (HTTP) и `__invoke()` (Nutgram) защищает POST/DELETE инвентаря через route-группы в обоих каналах.

**Tech Stack:** Laravel 12, Nutgram, PostgreSQL 18, Pest

---

## Карта файлов

| Действие | Файл |
|----------|------|
| Создать | `app/Enums/UserRole.php` |
| Создать | `database/migrations/2026_04_27_000002_alter_users_add_role.php` |
| Изменить | `app/Models/User.php` |
| Изменить | `database/factories/UserFactory.php` |
| Создать | `app/Providers/AuthorizationServiceProvider.php` |
| Изменить | `bootstrap/providers.php` |
| Создать | `app/Middleware/CanManageMiddleware.php` |
| Изменить | `routes/api.php` |
| Изменить | `routes/telegram.php` |
| Создать | `tests/Unit/Enums/UserRoleTest.php` |
| Изменить | `tests/Feature/Actions/Inventory/InventoryActionTest.php` |

---

## Task 1: UserRole Enum

**Files:**
- Create: `tests/Unit/Enums/UserRoleTest.php`
- Create: `app/Enums/UserRole.php`

- [ ] **Step 1: Написать failing тесты**

```php
// tests/Unit/Enums/UserRoleTest.php
<?php

use App\Enums\UserRole;

it('bartender can manage', fn () => expect(UserRole::Bartender->canManage())->toBeTrue());
it('owner can manage', fn () => expect(UserRole::Owner->canManage())->toBeTrue());
it('guest cannot manage', fn () => expect(UserRole::Guest->canManage())->toBeFalse());
```

- [ ] **Step 2: Запустить тесты, убедиться что падают**

```bash
docker compose exec app php artisan test --filter=UserRoleTest
```

Ожидается: `FAIL` — класс `UserRole` не существует.

- [ ] **Step 3: Создать enum**

```php
// app/Enums/UserRole.php
<?php

namespace App\Enums;

enum UserRole: string
{
    case Guest     = 'guest';
    case Bartender = 'bartender';
    case Owner     = 'owner';

    public function canManage(): bool
    {
        return match($this) {
            self::Bartender, self::Owner => true,
            self::Guest                  => false,
        };
    }
}
```

- [ ] **Step 4: Запустить тесты, убедиться что проходят**

```bash
docker compose exec app php artisan test --filter=UserRoleTest
```

Ожидается: `PASS` — 3 теста.

- [ ] **Step 5: Коммит**

```bash
git add app/Enums/UserRole.php tests/Unit/Enums/UserRoleTest.php
git commit --author="Claude <claude@anthropic.com>" -m "feat(bb5-s1): add UserRole enum with canManage()"
```

---

## Task 2: Миграция и обновление User модели

**Files:**
- Create: `database/migrations/2026_04_27_000002_alter_users_add_role.php`
- Modify: `app/Models/User.php`
- Modify: `database/factories/UserFactory.php`

- [ ] **Step 1: Создать миграцию**

```php
// database/migrations/2026_04_27_000002_alter_users_add_role.php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(/** @lang PostgreSQL */ "
            CREATE TYPE user_role_type AS ENUM ('guest', 'bartender', 'owner');
            ALTER TABLE users ADD COLUMN role user_role_type NOT NULL DEFAULT 'guest';
        ");
    }

    public function down(): void
    {
        DB::unprepared(/** @lang PostgreSQL */ "
            ALTER TABLE users DROP COLUMN role;
            DROP TYPE IF EXISTS user_role_type;
        ");
    }
};
```

- [ ] **Step 2: Применить миграцию**

```bash
make migration-run
```

Ожидается: `Migrating: 2026_04_27_000002_alter_users_add_role` → `Migrated`.

- [ ] **Step 3: Обновить модель User**

Заменить содержимое `app/Models/User.php`:

```php
<?php

namespace App\Models;

use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $telegram_id
 * @property string $first_name
 * @property string|null $username
 * @property UserRole $role
 * @property \Illuminate\Support\Carbon $created_at
 */
class User extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'telegram_id',
        'first_name',
        'username',
        'role',
    ];

    protected $casts = [
        'telegram_id' => 'integer',
        'created_at'  => 'datetime',
        'role'        => UserRole::class,
    ];

    /** @return HasMany<Inventory, $this> */
    public function inventory(): HasMany
    {
        return $this->hasMany(Inventory::class);
    }
}
```

- [ ] **Step 4: Обновить UserFactory**

Заменить содержимое `database/factories/UserFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class UserFactory extends Factory
{
    protected $model = User::class;

    public function definition(): array
    {
        return [
            'telegram_id' => $this->faker->unique()->numberBetween(100000, 999999999),
            'first_name'  => $this->faker->firstName(),
            'username'    => $this->faker->optional()->userName(),
            'role'        => UserRole::Guest,
        ];
    }

    public function bartender(): static
    {
        return $this->state(['role' => UserRole::Bartender]);
    }

    public function owner(): static
    {
        return $this->state(['role' => UserRole::Owner]);
    }
}
```

- [ ] **Step 5: Запустить все тесты, убедиться что проходят**

```bash
make tests
```

Ожидается: все тесты зелёные (включая 3 новых `UserRoleTest`).

- [ ] **Step 6: Коммит**

```bash
git add database/migrations/2026_04_27_000002_alter_users_add_role.php \
        app/Models/User.php \
        database/factories/UserFactory.php
git commit --author="Claude <claude@anthropic.com>" -m "feat(bb5-s1): add role column to users, update model and factory"
```

---

## Task 3: AuthorizationServiceProvider + CanManageMiddleware

**Files:**
- Create: `app/Providers/AuthorizationServiceProvider.php`
- Modify: `bootstrap/providers.php`
- Create: `app/Middleware/CanManageMiddleware.php`

- [ ] **Step 1: Создать AuthorizationServiceProvider**

```php
// app/Providers/AuthorizationServiceProvider.php
<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AuthorizationServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Gate::define('can-manage', fn (User $user) => $user->role->canManage());
    }
}
```

- [ ] **Step 2: Зарегистрировать в bootstrap/providers.php**

```php
// bootstrap/providers.php
<?php

use App\Providers\AppServiceProvider;
use App\Providers\AuthorizationServiceProvider;
use App\Providers\BusServiceProvider;

return [
    AppServiceProvider::class,
    AuthorizationServiceProvider::class,
    BusServiceProvider::class,
];
```

- [ ] **Step 3: Создать CanManageMiddleware**

```php
// app/Middleware/CanManageMiddleware.php
<?php

namespace App\Middleware;

use Closure;
use Illuminate\Support\Facades\Gate;
use SergiX44\Nutgram\Nutgram;

final class CanManageMiddleware
{
    public function handle($request, Closure $next): mixed
    {
        Gate::authorize('can-manage');

        return $next($request);
    }

    public function __invoke(Nutgram $bot, callable $next): void
    {
        Gate::authorize('can-manage');

        $next($bot);
    }
}
```

- [ ] **Step 4: Запустить все тесты, убедиться что проходят**

```bash
make tests
```

Ожидается: все тесты зелёные.

- [ ] **Step 5: Коммит**

```bash
git add app/Providers/AuthorizationServiceProvider.php \
        bootstrap/providers.php \
        app/Middleware/CanManageMiddleware.php
git commit --author="Claude <claude@anthropic.com>" -m "feat(bb5-s1): add AuthorizationServiceProvider and CanManageMiddleware"
```

---

## Task 4: Обновить роуты

**Files:**
- Modify: `routes/api.php`
- Modify: `routes/telegram.php`

- [ ] **Step 1: Обновить routes/api.php**

```php
// routes/api.php
<?php

use App\Actions\Inventory\AddInventoryAction;
use App\Actions\Inventory\InventoryAction;
use App\Actions\Inventory\RemoveInventoryAction;
use App\Middleware\CanManageMiddleware;
use Illuminate\Support\Facades\Route;

Route::middleware('auth.telegram')->prefix('inventory')->group(function () {
    Route::get('/', InventoryAction::class);

    Route::middleware(CanManageMiddleware::class)->group(function () {
        Route::post('/', AddInventoryAction::class);
        Route::delete('/{id}', [RemoveInventoryAction::class, '__invoke']);
    });
});
```

- [ ] **Step 2: Обновить routes/telegram.php**

```php
// routes/telegram.php
<?php

/** @var Nutgram $bot */

use App\Actions\Inventory\InventoryAction;
use App\Actions\Inventory\RemoveInventoryAction;
use App\Middleware\CanManageMiddleware;
use App\Telegram\Conversations\AddInventoryConversation;
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

$bot->onCallbackQueryData('noop', fn (Nutgram $bot) => $bot->answerCallbackQuery());

// Не активированы до Phase 2:
// $bot->onCommand('search', SearchByNameConversation::class);
// $bot->onCommand('ingredients', SearchByIngredientConversation::class);
// $bot->onCommand('filter', FilterConversation::class);
// $bot->onCallbackQueryData('recipe:show:{id}', \App\Telegram\Handlers\RecipeHandler::class);
```

- [ ] **Step 3: Запустить все тесты**

```bash
make tests
```

Ожидается: feature-тесты для POST/DELETE упадут с `403` — существующие тесты используют `guest`-пользователей. Это ожидаемо; фикс — в следующем таске.

- [ ] **Step 4: Коммит**

```bash
git add routes/api.php routes/telegram.php
git commit --author="Claude <claude@anthropic.com>" -m "feat(bb5-s1): apply CanManageMiddleware to inventory write routes"
```

---

## Task 5: Обновить Feature тесты

**Files:**
- Modify: `tests/Feature/Actions/Inventory/InventoryActionTest.php`

- [ ] **Step 1: Обновить InventoryActionTest.php**

```php
// tests/Feature/Actions/Inventory/InventoryActionTest.php
<?php

use App\Models\Ingredient;
use App\Models\Inventory;
use App\Models\User;

it('GET /api/inventory returns all items', function () {
    Inventory::factory()->count(3)->create();
    $user = User::factory()->create();

    $this->getJson("/api/inventory?telegram_id={$user->telegram_id}")
        ->assertOk()
        ->assertJsonCount(3);
});

it('GET /api/inventory returns 404 for unknown telegram_id', function () {
    $this->getJson('/api/inventory?telegram_id=999999999')->assertNotFound();
});

it('POST /api/inventory creates an inventory item', function () {
    $user = User::factory()->bartender()->create();
    $ingredient = Ingredient::factory()->create();

    $this->postJson('/api/inventory', [
        'telegram_id'   => $user->telegram_id,
        'ingredient_id' => $ingredient->id,
        'quantity'      => 500,
        'unit'          => 'мл',
    ])->assertCreated();

    $this->assertDatabaseHas('bar_inventory', ['ingredient_id' => $ingredient->id, 'quantity' => 500]);
});

it('POST /api/inventory returns 403 for guest', function () {
    $user = User::factory()->create();
    $ingredient = Ingredient::factory()->create();

    $this->postJson('/api/inventory', [
        'telegram_id'   => $user->telegram_id,
        'ingredient_id' => $ingredient->id,
    ])->assertForbidden();
});

it('DELETE /api/inventory/{id} removes the item', function () {
    $user = User::factory()->bartender()->create();
    $item = Inventory::factory()->create();

    $this->deleteJson("/api/inventory/{$item->id}?telegram_id={$user->telegram_id}")
        ->assertNoContent();

    $this->assertDatabaseMissing('bar_inventory', ['id' => $item->id]);
});

it('DELETE /api/inventory/{id} returns 403 for guest', function () {
    $user = User::factory()->create();
    $item = Inventory::factory()->create();

    $this->deleteJson("/api/inventory/{$item->id}?telegram_id={$user->telegram_id}")
        ->assertForbidden();
});

it('DELETE /api/inventory/{id} returns 404 for missing item', function () {
    $user = User::factory()->bartender()->create();

    $this->deleteJson("/api/inventory/999?telegram_id={$user->telegram_id}")
        ->assertNotFound();
});
```

- [ ] **Step 2: Запустить все тесты**

```bash
make tests
```

Ожидается: `PASS` — все тесты зелёные (3 Unit `UserRole` + 8 Unit `Inventory` handlers + 7 Feature).

- [ ] **Step 3: Коммит**

```bash
git add tests/Feature/Actions/Inventory/InventoryActionTest.php
git commit --author="Claude <claude@anthropic.com>" -m "test(bb5-s1): update feature tests for role-based access control"
```
