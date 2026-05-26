# Phase 3.1: Заказы коктейлей — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.
>
> **Before starting:** Read `.agents/knowledge/codebase.md` и `docs/superpowers/specs/2026-05-26-phase3-1-orders-design.md`.
> **After completing:** Update `.agents/knowledge/codebase.md` — статус Phase 3.1 → ✅ Готово; Order model + enum; маршруты orders; паттерн push-уведомлений через `sendMessage(chat_id:)`.

**Goal:** Гости заказывают коктейли в активной сессии; бармен принимает с количеством (×1–5) или отклоняет одним тапом на inline-кнопках; гость получает push-уведомление о решении и может просмотреть свои заказы за вечер.

**Architecture:**
- `Order` — Eloquent-модель, `session_id BIGINT` требует `ALTER COLUMN → SMALLINT` + FK (Phase 3 CASCADE-дропнул его при пересоздании `bar_sessions`); `order_status_type` ENUM уже создан исходной миграцией-заглушкой.
- PlaceOrder / AcceptOrder / CancelOrder — Bus-команды (Data → Handler через `BusServiceProvider`); Telegram-уведомления (барменю при заказе, гостю при решении) отправляет **Action** после вызова Handler — Handler про Nutgram не знает.
- Уведомление барменю содержит сразу кнопки ×1..×5 и «Отказать» (одна callback-route `order:qty:{id}:{n}`); двухшагового флоу нет.
- `ListOrdersAction` — двойной транспорт: Telegram (`orders:my`) + HTTP `GET /api/sessions/{id}/orders`.

**Tech Stack:** `$bot->sendMessage(chat_id: $telegramId, ...)` для push другому пользователю; `UserRole::canManage()` для поиска менеджеров-получателей уведомлений.

**Branch:** `feature/bb8_orders`

---

## Карта файлов

### Новые файлы

| Файл | Ответственность |
|---|---|
| `database/migrations/2026_05_26_000001_alter_orders_add_session_fk.php` | ALTER session_id BIGINT→SMALLINT + FK + индексы |
| `app/Enums/OrderStatus.php` | Enum Pending / Accepted / Cancelled |
| `database/factories/OrderFactory.php` | `Order::factory()` + state `accepted()` / `cancelled()` |
| `app/Data/Orders/PlaceOrderData.php` | DTO: recipeId (string), userId (int) |
| `app/Handlers/Orders/PlaceOrderHandler.php` | Проверяет активную сессию, создаёт Order(pending), возвращает Order |
| `app/Actions/Orders/PlaceOrderAction.php` | fromTelegram: создаёт заказ → уведомляет менеджеров → обновляет клавиатуру карточки |
| `app/Data/Orders/AcceptOrderData.php` | DTO: orderId (int), quantity (int 1–5) |
| `app/Handlers/Orders/AcceptOrderHandler.php` | Проверяет status=pending, обновляет accepted+qty, возвращает Order с user/recipe |
| `app/Actions/Orders/AcceptOrderAction.php` | fromTelegram под CanManage: вызывает handler, уведомляет гостя, убирает кнопки |
| `app/Data/Orders/CancelOrderData.php` | DTO: orderId (int) |
| `app/Handlers/Orders/CancelOrderHandler.php` | Проверяет status=pending, обновляет cancelled, возвращает Order с user/recipe |
| `app/Actions/Orders/CancelOrderAction.php` | fromTelegram под CanManage: вызывает handler, уведомляет гостя, убирает кнопки |
| `app/Exceptions/OrderAlreadyProcessedException.php` | Доменное исключение «заказ уже обработан» |
| `app/Handlers/Orders/ListGuestOrdersHandler.php` | Список заказов пользователя в текущей активной сессии |
| `app/Actions/Orders/ListOrdersAction.php` | fromTelegram (`orders:my`) + `__invoke` (HTTP GET /api/sessions/{id}/orders) |
| `app/Actions/Orders/UpdateOrderAction.php` | HTTP PATCH /api/orders/{id} — обновляет статус/количество через Bus |
| `tests/Unit/Handlers/Orders/PlaceOrderHandlerTest.php` | Создаёт заказ / бросает без сессии |
| `tests/Unit/Handlers/Orders/AcceptOrderHandlerTest.php` | Принимает с qty / бросает при повторном вызове |
| `tests/Unit/Handlers/Orders/CancelOrderHandlerTest.php` | Отменяет / бросает при повторном вызове |
| `tests/Unit/Handlers/Orders/ListGuestOrdersHandlerTest.php` | Список / пусто без сессии |
| `tests/Feature/Actions/Orders/ListOrdersActionTest.php` | HTTP GET /api/sessions/{id}/orders |
| `tests/Feature/PhaseThreeOneFlowTest.php` | E2E: place → accept → guest gets notification |

### Изменяемые файлы

| Файл | Изменение |
|---|---|
| `app/Models/Order.php` | fillable, casts (OrderStatus enum), relations session/user/recipe |
| `app/Providers/BusServiceProvider.php` | +3 маппинга Place/Accept/CancelOrderData |
| `app/Actions/Search/GetRecipeAction.php` | Добавить кнопку `recipe:order:{id}` при активной сессии |
| `app/Actions/Search/BrowseRecipesAction.php` | Заменить noop-кнопку «Заказать» на `recipe:order:{id}` (условно при активной сессии) |
| `app/Actions/StartAction.php` | Добавить кнопку «📋 Мои заказы» (`orders:my`) при активной сессии |
| `routes/telegram.php` | 4 новых callback-маршрута |
| `routes/api.php` | GET /api/sessions/{id}/orders + PATCH /api/orders/{id} |
| `.agents/knowledge/codebase.md` | Phase 3.1 → ✅; Order model; маршруты; паттерн уведомлений |

---

## Порядок исполнения

```
Группа 1 (последовательно): T1 — DB foundation; все последующие зависят от модели Order

Группа 2 (параллельно): T2, T3, T4, T5 — depends T1
  T2 (PlaceOrder):  app/Data/Orders/PlaceOrderData + PlaceOrderHandler + PlaceOrderAction
  T3 (AcceptOrder): AcceptOrderData + AcceptOrderHandler + AcceptOrderAction + OrderAlreadyProcessedException
  T4 (CancelOrder): CancelOrderData + CancelOrderHandler + CancelOrderAction
  T5 (ListOrders):  ListGuestOrdersHandler + ListOrdersAction
  Независимы: разные namespace'ы (Orders/{Place/Accept/Cancel/List}*), нет общих файлов;
  BusServiceProvider обновляется только в T6 — конфликт записи исключён.

Группа 3 (последовательно): T6 — routes + UI + BusServiceProvider + e2e; depends T2, T3, T4, T5

Группа 4 (финал): T7 — codebase.md + PR; depends T6
```

---

## Критерии handoff между задачами

См. **«Handoff-чеклист задачи»** в `CLAUDE.md`. Файлы вне «Карты файлов» без явного обоснования → reviewer отклоняет. Каждая задача заканчивается `make pint-dirty-dry` → 0 и `docker compose exec app php artisan test --filter=...` → PASS.

---

## Task 1: DB foundation — миграция + Order model + enum + factory

**Depends on:** None

**Files:**
- Create: `database/migrations/2026_05_26_000001_alter_orders_add_session_fk.php`
- Create: `app/Enums/OrderStatus.php`
- Modify: `app/Models/Order.php`
- Create: `database/factories/OrderFactory.php`

- [ ] **Step 1: OrderStatus enum**

Файл: `app/Enums/OrderStatus.php`

```php
<?php

namespace App\Enums;

enum OrderStatus: string
{
    case Pending   = 'pending';
    case Accepted  = 'accepted';
    case Cancelled = 'cancelled';
}
```

- [ ] **Step 2: ALTER-миграция**

Файл: `database/migrations/2026_05_26_000001_alter_orders_add_session_fk.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(/** @lang PostgreSQL */ "
            -- Phase 3 пересоздала bar_sessions (SMALLINT PK) через DROP CASCADE,
            -- который снёс fk_orders_session_id из заглушки orders.
            -- Восстанавливаем FK; тип колонки приводим к SMALLINT для совместимости.
            ALTER TABLE orders
                ALTER COLUMN session_id TYPE SMALLINT;

            ALTER TABLE orders
                ADD CONSTRAINT fk_orders_session_id
                    FOREIGN KEY (session_id) REFERENCES bar_sessions (id) ON DELETE CASCADE;

            CREATE INDEX idx_orders_session_id ON orders (session_id);
            CREATE INDEX idx_orders_user_id    ON orders (user_id);
        ");
    }

    public function down(): void
    {
        DB::unprepared(/** @lang PostgreSQL */ "
            DROP INDEX IF EXISTS idx_orders_user_id;
            DROP INDEX IF EXISTS idx_orders_session_id;
            ALTER TABLE orders DROP CONSTRAINT IF EXISTS fk_orders_session_id;
            ALTER TABLE orders ALTER COLUMN session_id TYPE BIGINT;
        ");
    }
};
```

- [ ] **Step 3: Прогнать миграцию**

```bash
make migration-run
```

Ожидаемо: `Migrated: 2026_05_26_000001_alter_orders_add_session_fk` без ошибок.

- [ ] **Step 4: Обновить Order model**

Файл: `app/Models/Order.php`

```php
<?php

namespace App\Models;

use App\Enums\OrderStatus;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory;

    protected $fillable = [
        'session_id',
        'user_id',
        'recipe_id',
        'quantity',
        'status',
    ];

    protected $casts = [
        'status'   => OrderStatus::class,
        'quantity' => 'integer',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(BarSession::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function recipe(): BelongsTo
    {
        return $this->belongsTo(Recipe::class);
    }
}
```

- [ ] **Step 5: OrderFactory**

Файл: `database/factories/OrderFactory.php`

```php
<?php

namespace Database\Factories;

use App\Enums\OrderStatus;
use App\Models\BarSession;
use App\Models\Order;
use App\Models\Recipe;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
final class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition(): array
    {
        return [
            'session_id' => BarSession::factory(),
            'user_id'    => User::factory(),
            'recipe_id'  => Recipe::factory(),
            'quantity'   => null,
            'status'     => OrderStatus::Pending,
        ];
    }

    public function accepted(): self
    {
        return $this->state(fn () => [
            'status'   => OrderStatus::Accepted,
            'quantity' => 2,
        ]);
    }

    public function cancelled(): self
    {
        return $this->state(fn () => [
            'status' => OrderStatus::Cancelled,
        ]);
    }
}
```

- [ ] **Step 6: Тест factory (smoke)**

Нет отдельного тестового файла — factory проверяется в рамках handler-тестов. Быстрая проверка через tinker:

```bash
docker compose exec app php artisan tinker
>>> \App\Models\Order::factory()->create();
>>> \App\Models\Order::factory()->accepted()->create();
>>> exit
```

Ожидаемо: оба вызова без исключений, в таблице `orders` появились строки.

- [ ] **Step 7: Pint + commit**

```bash
make pint-dirty
git add database/migrations/2026_05_26_000001_alter_orders_add_session_fk.php \
        app/Enums/OrderStatus.php \
        app/Models/Order.php \
        database/factories/OrderFactory.php
git commit --author="Claude <claude@anthropic.com>" -m "feat(bb-8): orders table ALTER + OrderStatus enum + Order model + factory"
```

**Handoff:** миграция прошла, FK восстановлен; `Order::factory()` создаёт записи; pint-dirty-dry — 0.

---

## Task 2: PlaceOrder — Handler + Action

**Depends on:** Task 1

**Files:**
- Create: `app/Data/Orders/PlaceOrderData.php`
- Create: `app/Handlers/Orders/PlaceOrderHandler.php`
- Create: `app/Actions/Orders/PlaceOrderAction.php`
- Create: `tests/Unit/Handlers/Orders/PlaceOrderHandlerTest.php`

- [ ] **Step 1: DTO**

Файл: `app/Data/Orders/PlaceOrderData.php`

```php
<?php

namespace App\Data\Orders;

use Spatie\LaravelData\Data;

final class PlaceOrderData extends Data
{
    public function __construct(
        public readonly string $recipeId,
        public readonly int $userId,
    ) {}
}
```

- [ ] **Step 2: Тест handler (red)**

Файл: `tests/Unit/Handlers/Orders/PlaceOrderHandlerTest.php`

```php
<?php

use App\Data\Orders\PlaceOrderData;
use App\Enums\OrderStatus;
use App\Handlers\Orders\PlaceOrderHandler;
use App\Models\BarSession;
use App\Models\Recipe;
use App\Models\User;
use Carbon\CarbonImmutable;

afterEach(fn () => CarbonImmutable::setTestNow());

it('creates a pending order in active session', function () {
    CarbonImmutable::setTestNow('2026-05-26 18:00:00');
    $session = BarSession::factory()->create(['started_at' => now()]);
    $user    = User::factory()->create();
    $recipe  = Recipe::factory()->create();

    $order = app(PlaceOrderHandler::class)->handle(
        new PlaceOrderData(recipeId: $recipe->id, userId: $user->id)
    );

    expect($order->status)->toBe(OrderStatus::Pending)
        ->and($order->session_id)->toBe($session->id)
        ->and($order->user_id)->toBe($user->id)
        ->and($order->recipe_id)->toBe($recipe->id)
        ->and($order->quantity)->toBeNull();
});

it('throws RuntimeException when no active session', function () {
    $user   = User::factory()->create();
    $recipe = Recipe::factory()->create();

    expect(fn () => app(PlaceOrderHandler::class)->handle(
        new PlaceOrderData(recipeId: $recipe->id, userId: $user->id)
    ))->toThrow(\RuntimeException::class);
});
```

```bash
docker compose exec app php artisan test --filter=PlaceOrderHandlerTest
```

Ожидаемо: 2 FAIL (класс не найден).

- [ ] **Step 3: Реализация PlaceOrderHandler**

Файл: `app/Handlers/Orders/PlaceOrderHandler.php`

```php
<?php

namespace App\Handlers\Orders;

use App\Data\Orders\PlaceOrderData;
use App\Enums\OrderStatus;
use App\Handlers\Session\GetActiveSessionHandler;
use App\Models\Order;

final class PlaceOrderHandler
{
    public function __construct(
        private readonly GetActiveSessionHandler $sessionHandler,
    ) {}

    public function handle(PlaceOrderData $data): Order
    {
        $session = $this->sessionHandler->handle();

        if ($session === null) {
            throw new \RuntimeException('Нет активной бар-сессии');
        }

        return Order::create([
            'session_id' => $session->id,
            'user_id'    => $data->userId,
            'recipe_id'  => $data->recipeId,
            'status'     => OrderStatus::Pending,
            'quantity'   => null,
        ]);
    }
}
```

- [ ] **Step 4: Тест pass**

```bash
docker compose exec app php artisan test --filter=PlaceOrderHandlerTest
```

Ожидаемо: 2 PASS.

- [ ] **Step 5: PlaceOrderAction**

Файл: `app/Actions/Orders/PlaceOrderAction.php`

```php
<?php

namespace App\Actions\Orders;

use App\Data\Orders\PlaceOrderData;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\Bus;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

final class PlaceOrderAction
{
    public function fromTelegram(Nutgram $bot, string $id): void
    {
        try {
            $order = Bus::dispatch(new PlaceOrderData(
                recipeId: $id,
                userId:   $bot->userId(),
            ));
        } catch (\RuntimeException) {
            $bot->answerCallbackQuery(text: '🚫 Нет активной сессии');
            return;
        }

        // Уведомить всех менеджеров
        $managers = User::all()->filter(fn (User $u) => $u->role->canManage());
        $recipe   = $order->recipe;
        $guest    = $order->user;

        $keyboard = InlineKeyboardMarkup::make()
            ->addRow(
                InlineKeyboardButton::make("✅ Все (×5)", callback_data: "order:qty:{$order->id}:5"),
                InlineKeyboardButton::make("✅ ×4",       callback_data: "order:qty:{$order->id}:4"),
                InlineKeyboardButton::make("✅ ×3",       callback_data: "order:qty:{$order->id}:3"),
                InlineKeyboardButton::make("✅ ×2",       callback_data: "order:qty:{$order->id}:2"),
                InlineKeyboardButton::make("✅ ×1",       callback_data: "order:qty:{$order->id}:1"),
            )
            ->addRow(
                InlineKeyboardButton::make('❌ Отказать', callback_data: "order:cancel:{$order->id}"),
            );

        foreach ($managers as $manager) {
            $bot->sendMessage(
                text: "🍹 *Новый заказ*\n\nКоктейль: {$recipe->name_ru}\nГость: {$guest->first_name}" .
                      ($guest->username ? " (@{$guest->username})" : ''),
                chat_id:      $manager->telegram_id,
                parse_mode:   'Markdown',
                reply_markup: $keyboard,
            );
        }

        // Ответить гостю и обновить клавиатуру карточки
        $bot->answerCallbackQuery(text: 'Заказ отправлен! 🍸');
        $bot->editMessageReplyMarkup(
            reply_markup: InlineKeyboardMarkup::make()
                ->addRow(
                    InlineKeyboardButton::make('🔙 К поиску',   callback_data: 'browse:back'),
                    InlineKeyboardButton::make('📋 Мои заказы', callback_data: 'orders:my'),
                ),
        );
    }
}
```

> **Заметка для исполнителя:** `$order->recipe` и `$order->user` — lazy-load после `Order::create()`. Если тесты покажут N+1 проблему, добавить `$order->load('recipe', 'user')` перед рассылкой.

- [ ] **Step 6: Pint + commit**

```bash
make pint-dirty
git add app/Data/Orders/PlaceOrderData.php \
        app/Handlers/Orders/PlaceOrderHandler.php \
        app/Actions/Orders/PlaceOrderAction.php \
        tests/Unit/Handlers/Orders/PlaceOrderHandlerTest.php
git commit --author="Claude <claude@anthropic.com>" -m "feat(bb-8): PlaceOrder — handler + action + unit tests"
```

**Handoff:** 2 unit-теста pass; pint-dirty-dry — 0. BusServiceProvider **не трогаем** — обновляется в T6.

---

## Task 3: AcceptOrder — Handler + Action + Exception

**Depends on:** Task 1

**Files:**
- Create: `app/Exceptions/OrderAlreadyProcessedException.php`
- Create: `app/Data/Orders/AcceptOrderData.php`
- Create: `app/Handlers/Orders/AcceptOrderHandler.php`
- Create: `app/Actions/Orders/AcceptOrderAction.php`
- Create: `tests/Unit/Handlers/Orders/AcceptOrderHandlerTest.php`

- [ ] **Step 1: Exception**

Файл: `app/Exceptions/OrderAlreadyProcessedException.php`

```php
<?php

namespace App\Exceptions;

use RuntimeException;

final class OrderAlreadyProcessedException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Заказ уже обработан');
    }
}
```

- [ ] **Step 2: DTO**

Файл: `app/Data/Orders/AcceptOrderData.php`

```php
<?php

namespace App\Data\Orders;

use Spatie\LaravelData\Data;

final class AcceptOrderData extends Data
{
    public function __construct(
        public readonly int $orderId,
        public readonly int $quantity,
    ) {}
}
```

- [ ] **Step 3: Тест handler (red)**

Файл: `tests/Unit/Handlers/Orders/AcceptOrderHandlerTest.php`

```php
<?php

use App\Data\Orders\AcceptOrderData;
use App\Enums\OrderStatus;
use App\Exceptions\OrderAlreadyProcessedException;
use App\Handlers\Orders\AcceptOrderHandler;
use App\Models\Order;

it('accepts a pending order with quantity', function () {
    $order = Order::factory()->create();

    $result = (new AcceptOrderHandler)->handle(new AcceptOrderData(
        orderId:  $order->id,
        quantity: 3,
    ));

    expect($result->status)->toBe(OrderStatus::Accepted)
        ->and($result->quantity)->toBe(3)
        ->and($result->relationLoaded('user'))->toBeTrue()
        ->and($result->relationLoaded('recipe'))->toBeTrue();
});

it('throws OrderAlreadyProcessedException when order is already accepted', function () {
    $order = Order::factory()->accepted()->create();

    expect(fn () => (new AcceptOrderHandler)->handle(new AcceptOrderData(
        orderId:  $order->id,
        quantity: 2,
    )))->toThrow(OrderAlreadyProcessedException::class);
});

it('throws OrderAlreadyProcessedException when order is cancelled', function () {
    $order = Order::factory()->cancelled()->create();

    expect(fn () => (new AcceptOrderHandler)->handle(new AcceptOrderData(
        orderId:  $order->id,
        quantity: 1,
    )))->toThrow(OrderAlreadyProcessedException::class);
});
```

```bash
docker compose exec app php artisan test --filter=AcceptOrderHandlerTest
```

Ожидаемо: 3 FAIL.

- [ ] **Step 4: Реализация AcceptOrderHandler**

Файл: `app/Handlers/Orders/AcceptOrderHandler.php`

```php
<?php

namespace App\Handlers\Orders;

use App\Data\Orders\AcceptOrderData;
use App\Enums\OrderStatus;
use App\Exceptions\OrderAlreadyProcessedException;
use App\Models\Order;

final class AcceptOrderHandler
{
    public function handle(AcceptOrderData $data): Order
    {
        $order = Order::with('user', 'recipe')->findOrFail($data->orderId);

        if ($order->status !== OrderStatus::Pending) {
            throw new OrderAlreadyProcessedException;
        }

        $order->update([
            'status'   => OrderStatus::Accepted,
            'quantity' => $data->quantity,
        ]);

        return $order->fresh(['user', 'recipe']);
    }
}
```

- [ ] **Step 5: Тест pass**

```bash
docker compose exec app php artisan test --filter=AcceptOrderHandlerTest
```

Ожидаемо: 3 PASS.

- [ ] **Step 6: AcceptOrderAction**

Файл: `app/Actions/Orders/AcceptOrderAction.php`

```php
<?php

namespace App\Actions\Orders;

use App\Data\Orders\AcceptOrderData;
use App\Exceptions\OrderAlreadyProcessedException;
use Illuminate\Support\Facades\Bus;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

final class AcceptOrderAction
{
    public function fromTelegram(Nutgram $bot, string $id, int $n): void
    {
        try {
            $order = Bus::dispatch(new AcceptOrderData(
                orderId:  (int) $id,
                quantity: $n,
            ));
        } catch (OrderAlreadyProcessedException) {
            $bot->answerCallbackQuery(text: 'Заказ уже обработан');
            return;
        }

        // Убрать кнопки из уведомления бармена
        $bot->editMessageReplyMarkup(reply_markup: InlineKeyboardMarkup::make());

        // Уведомить гостя
        $recipe = $order->recipe;
        $bot->sendMessage(
            text:    "✅ Заказ принят! {$recipe->name_ru} ×{$n} — уже готовим 🍸",
            chat_id: $order->user->telegram_id,
        );
    }
}
```

- [ ] **Step 7: Pint + commit**

```bash
make pint-dirty
git add app/Exceptions/OrderAlreadyProcessedException.php \
        app/Data/Orders/AcceptOrderData.php \
        app/Handlers/Orders/AcceptOrderHandler.php \
        app/Actions/Orders/AcceptOrderAction.php \
        tests/Unit/Handlers/Orders/AcceptOrderHandlerTest.php
git commit --author="Claude <claude@anthropic.com>" -m "feat(bb-8): AcceptOrder — handler + action + idempotency + unit tests"
```

**Handoff:** 3 unit-теста pass; pint-dirty-dry — 0.

---

## Task 4: CancelOrder — Handler + Action

**Depends on:** Task 1

**Files:**
- Create: `app/Data/Orders/CancelOrderData.php`
- Create: `app/Handlers/Orders/CancelOrderHandler.php`
- Create: `app/Actions/Orders/CancelOrderAction.php`
- Create: `tests/Unit/Handlers/Orders/CancelOrderHandlerTest.php`

- [ ] **Step 1: DTO**

Файл: `app/Data/Orders/CancelOrderData.php`

```php
<?php

namespace App\Data\Orders;

use Spatie\LaravelData\Data;

final class CancelOrderData extends Data
{
    public function __construct(
        public readonly int $orderId,
    ) {}
}
```

- [ ] **Step 2: Тест handler (red)**

Файл: `tests/Unit/Handlers/Orders/CancelOrderHandlerTest.php`

```php
<?php

use App\Data\Orders\CancelOrderData;
use App\Enums\OrderStatus;
use App\Exceptions\OrderAlreadyProcessedException;
use App\Handlers\Orders\CancelOrderHandler;
use App\Models\Order;

it('cancels a pending order', function () {
    $order = Order::factory()->create();

    $result = (new CancelOrderHandler)->handle(new CancelOrderData(orderId: $order->id));

    expect($result->status)->toBe(OrderStatus::Cancelled)
        ->and($result->relationLoaded('user'))->toBeTrue()
        ->and($result->relationLoaded('recipe'))->toBeTrue();
});

it('throws OrderAlreadyProcessedException when order is already accepted', function () {
    $order = Order::factory()->accepted()->create();

    expect(fn () => (new CancelOrderHandler)->handle(new CancelOrderData(orderId: $order->id)))
        ->toThrow(OrderAlreadyProcessedException::class);
});

it('throws OrderAlreadyProcessedException when order is already cancelled', function () {
    $order = Order::factory()->cancelled()->create();

    expect(fn () => (new CancelOrderHandler)->handle(new CancelOrderData(orderId: $order->id)))
        ->toThrow(OrderAlreadyProcessedException::class);
});
```

```bash
docker compose exec app php artisan test --filter=CancelOrderHandlerTest
```

Ожидаемо: 3 FAIL.

- [ ] **Step 3: Реализация CancelOrderHandler**

Файл: `app/Handlers/Orders/CancelOrderHandler.php`

```php
<?php

namespace App\Handlers\Orders;

use App\Data\Orders\CancelOrderData;
use App\Enums\OrderStatus;
use App\Exceptions\OrderAlreadyProcessedException;
use App\Models\Order;

final class CancelOrderHandler
{
    public function handle(CancelOrderData $data): Order
    {
        $order = Order::with('user', 'recipe')->findOrFail($data->orderId);

        if ($order->status !== OrderStatus::Pending) {
            throw new OrderAlreadyProcessedException;
        }

        $order->update(['status' => OrderStatus::Cancelled]);

        return $order->fresh(['user', 'recipe']);
    }
}
```

- [ ] **Step 4: Тест pass**

```bash
docker compose exec app php artisan test --filter=CancelOrderHandlerTest
```

Ожидаемо: 3 PASS.

- [ ] **Step 5: CancelOrderAction**

Файл: `app/Actions/Orders/CancelOrderAction.php`

```php
<?php

namespace App\Actions\Orders;

use App\Data\Orders\CancelOrderData;
use App\Exceptions\OrderAlreadyProcessedException;
use Illuminate\Support\Facades\Bus;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

final class CancelOrderAction
{
    public function fromTelegram(Nutgram $bot, string $id): void
    {
        try {
            $order = Bus::dispatch(new CancelOrderData(orderId: (int) $id));
        } catch (OrderAlreadyProcessedException) {
            $bot->answerCallbackQuery(text: 'Заказ уже обработан');
            return;
        }

        // Убрать кнопки из уведомления бармена
        $bot->editMessageReplyMarkup(reply_markup: InlineKeyboardMarkup::make());

        // Уведомить гостя
        $recipe = $order->recipe;
        $bot->sendMessage(
            text:    "❌ Заказ на {$recipe->name_ru} отклонён 😔",
            chat_id: $order->user->telegram_id,
        );
    }
}
```

- [ ] **Step 6: Pint + commit**

```bash
make pint-dirty
git add app/Data/Orders/CancelOrderData.php \
        app/Handlers/Orders/CancelOrderHandler.php \
        app/Actions/Orders/CancelOrderAction.php \
        tests/Unit/Handlers/Orders/CancelOrderHandlerTest.php
git commit --author="Claude <claude@anthropic.com>" -m "feat(bb-8): CancelOrder — handler + action + unit tests"
```

**Handoff:** 3 unit-теста pass; pint-dirty-dry — 0.

---

## Task 5: ListOrders — Handler + Action

**Depends on:** Task 1

**Files:**
- Create: `app/Handlers/Orders/ListGuestOrdersHandler.php`
- Create: `app/Actions/Orders/ListOrdersAction.php`
- Create: `tests/Unit/Handlers/Orders/ListGuestOrdersHandlerTest.php`
- Create: `tests/Feature/Actions/Orders/ListOrdersActionTest.php`

- [ ] **Step 1: Тест handler (red)**

Файл: `tests/Unit/Handlers/Orders/ListGuestOrdersHandlerTest.php`

```php
<?php

use App\Enums\OrderStatus;
use App\Handlers\Orders\ListGuestOrdersHandler;
use App\Models\BarSession;
use App\Models\Order;
use App\Models\User;
use Carbon\CarbonImmutable;

afterEach(fn () => CarbonImmutable::setTestNow());

it('returns orders for user in active session ordered by created_at', function () {
    CarbonImmutable::setTestNow('2026-05-26 18:00:00');
    $session = BarSession::factory()->create(['started_at' => now()]);
    $user    = User::factory()->create();

    Order::factory()->count(3)->create(['session_id' => $session->id, 'user_id' => $user->id]);
    Order::factory()->create(['session_id' => $session->id]); // другой гость — не должен попасть

    $result = app(ListGuestOrdersHandler::class)->handle($user->id);

    expect($result)->toHaveCount(3)
        ->each->toHaveKey('status');
});

it('returns empty collection when no active session', function () {
    $user = User::factory()->create();

    expect(app(ListGuestOrdersHandler::class)->handle($user->id))->toBeEmpty();
});

it('returns empty collection when user has no orders in active session', function () {
    CarbonImmutable::setTestNow('2026-05-26 18:00:00');
    BarSession::factory()->create(['started_at' => now()]);
    $user = User::factory()->create();

    expect(app(ListGuestOrdersHandler::class)->handle($user->id))->toBeEmpty();
});
```

```bash
docker compose exec app php artisan test --filter=ListGuestOrdersHandlerTest
```

Ожидаемо: 3 FAIL.

- [ ] **Step 2: Реализация ListGuestOrdersHandler**

Файл: `app/Handlers/Orders/ListGuestOrdersHandler.php`

```php
<?php

namespace App\Handlers\Orders;

use App\Handlers\Session\GetActiveSessionHandler;
use App\Models\Order;
use Illuminate\Support\Collection;

final class ListGuestOrdersHandler
{
    public function __construct(
        private readonly GetActiveSessionHandler $sessionHandler,
    ) {}

    /** @return Collection<int, Order> */
    public function handle(int $userId): Collection
    {
        $session = $this->sessionHandler->handle();

        if ($session === null) {
            return collect();
        }

        return Order::with('recipe')
            ->where('session_id', $session->id)
            ->where('user_id', $userId)
            ->orderBy('created_at')
            ->get();
    }
}
```

- [ ] **Step 3: Тест pass**

```bash
docker compose exec app php artisan test --filter=ListGuestOrdersHandlerTest
```

Ожидаемо: 3 PASS.

- [ ] **Step 4: Feature-тест (со `->skip` до T6)**

Файл: `tests/Feature/Actions/Orders/ListOrdersActionTest.php`

```php
<?php

use App\Models\BarSession;
use App\Models\Order;
use App\Models\User;
use Carbon\CarbonImmutable;

afterEach(fn () => CarbonImmutable::setTestNow());

it('GET /api/sessions/{id}/orders returns orders for session', function () {
    CarbonImmutable::setTestNow('2026-05-26 18:00:00');
    $user    = User::factory()->bartender()->create();
    $session = BarSession::factory()->create(['started_at' => now()]);
    Order::factory()->count(3)->create(['session_id' => $session->id]);

    $this->getJson("/api/sessions/{$session->id}/orders?telegram_id={$user->telegram_id}")
        ->assertOk()
        ->assertJsonCount(3);
})->skip('routes registered in T6');

it('GET returns 404 for unknown session', function () {
    $user = User::factory()->bartender()->create();

    $this->getJson("/api/sessions/9999/orders?telegram_id={$user->telegram_id}")
        ->assertNotFound();
})->skip('routes registered in T6');
```

- [ ] **Step 5: ListOrdersAction**

Файл: `app/Actions/Orders/ListOrdersAction.php`

```php
<?php

namespace App\Actions\Orders;

use App\Handlers\Orders\ListGuestOrdersHandler;
use App\Models\BarSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

final class ListOrdersAction
{
    public function __construct(private readonly ListGuestOrdersHandler $handler) {}

    // HTTP GET /api/sessions/{id}/orders
    public function __invoke(Request $request, int $id): JsonResponse
    {
        $session = BarSession::findOrFail($id);
        $orders  = Order::with('user', 'recipe')
            ->where('session_id', $session->id)
            ->orderBy('created_at')
            ->get();

        return response()->json($orders);
    }

    // Telegram: orders:my
    public function fromTelegram(Nutgram $bot): void
    {
        $orders = $this->handler->handle($bot->userId());

        if ($orders->isEmpty()) {
            $bot->sendMessage(
                text:         'Ты ещё ничего не заказывал 🍹',
                reply_markup: InlineKeyboardMarkup::make()
                    ->addRow(InlineKeyboardButton::make('🏠 Главное меню', callback_data: 'browse:back')),
            );
            return;
        }

        $lines = $orders->map(function ($order) {
            $icon = match ($order->status->value) {
                'accepted'  => "✅ ×{$order->quantity}",
                'cancelled' => '❌',
                default     => '⏳',
            };
            return "• {$order->recipe->name_ru} — {$icon}";
        })->join("\n");

        $bot->sendMessage(
            text:         "📋 *Твои заказы за вечер*\n\n{$lines}",
            parse_mode:   'Markdown',
            reply_markup: InlineKeyboardMarkup::make()
                ->addRow(InlineKeyboardButton::make('🏠 Главное меню', callback_data: 'browse:back')),
        );
    }
}
```

> **Заметка:** `__invoke` для HTTP показывает все заказы сессии (для бармена), `fromTelegram` — только заказы текущего гостя. Разная логика, один класс, два транспорта.

- [ ] **Step 6: Pint + commit**

```bash
make pint-dirty
git add app/Handlers/Orders/ListGuestOrdersHandler.php \
        app/Actions/Orders/ListOrdersAction.php \
        tests/Unit/Handlers/Orders/ListGuestOrdersHandlerTest.php \
        tests/Feature/Actions/Orders/ListOrdersActionTest.php
git commit --author="Claude <claude@anthropic.com>" -m "feat(bb-8): ListOrders — handler + action (Telegram + HTTP)"
```

**Handoff:** 3 unit-теста pass; feature-тест `->skip`; pint-dirty-dry — 0.

---

## Task 6: Routes + UI + BusServiceProvider + HTTP PATCH + e2e

**Depends on:** Task 2, Task 3, Task 4, Task 5

**Files:**
- Create: `app/Actions/Orders/UpdateOrderAction.php`
- Create: `tests/Feature/PhaseThreeOneFlowTest.php`
- Modify: `app/Providers/BusServiceProvider.php`
- Modify: `routes/telegram.php`
- Modify: `routes/api.php`
- Modify: `app/Actions/Search/GetRecipeAction.php`
- Modify: `app/Actions/Search/BrowseRecipesAction.php`
- Modify: `app/Actions/StartAction.php`
- Modify: `tests/Feature/Actions/Orders/ListOrdersActionTest.php` (снять `->skip`)

- [ ] **Step 1: BusServiceProvider — добавить 3 маппинга**

В `app/Providers/BusServiceProvider.php` добавить в массив `map()`:

```php
\App\Data\Orders\PlaceOrderData::class  => \App\Handlers\Orders\PlaceOrderHandler::class,
\App\Data\Orders\AcceptOrderData::class => \App\Handlers\Orders\AcceptOrderHandler::class,
\App\Data\Orders\CancelOrderData::class => \App\Handlers\Orders\CancelOrderHandler::class,
```

- [ ] **Step 2: UpdateOrderAction (HTTP PATCH)**

Файл: `app/Actions/Orders/UpdateOrderAction.php`

```php
<?php

namespace App\Actions\Orders;

use App\Data\Orders\AcceptOrderData;
use App\Data\Orders\CancelOrderData;
use App\Exceptions\OrderAlreadyProcessedException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;

final class UpdateOrderAction
{
    public function __invoke(Request $request, int $id): JsonResponse
    {
        $status = $request->input('status');

        try {
            $order = match ($status) {
                'accepted'  => Bus::dispatch(new AcceptOrderData(
                    orderId:  $id,
                    quantity: (int) $request->input('quantity', 1),
                )),
                'cancelled' => Bus::dispatch(new CancelOrderData(orderId: $id)),
                default     => abort(422, "status must be 'accepted' or 'cancelled'"),
            };
        } catch (OrderAlreadyProcessedException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }

        return response()->json($order);
    }
}
```

- [ ] **Step 3: Telegram-маршруты**

В `routes/telegram.php` добавить после секции session-маршрутов:

```php
// Phase 3.1 — Orders
$bot->onCallbackQueryData('recipe:order:{id}', [PlaceOrderAction::class, 'fromTelegram']);
$bot->onCallbackQueryData('orders:my', [ListOrdersAction::class, 'fromTelegram']);

$bot->group(function (Nutgram $bot) {
    $bot->onCallbackQueryData('order:qty:{id}:{n}', [AcceptOrderAction::class, 'fromTelegram']);
    $bot->onCallbackQueryData('order:cancel:{id}', [CancelOrderAction::class, 'fromTelegram']);
})->middleware(CanManageMiddleware::class);
```

Добавить use-импорты вверху файла (рядом с остальными):

```php
use App\Actions\Orders\PlaceOrderAction;
use App\Actions\Orders\AcceptOrderAction;
use App\Actions\Orders\CancelOrderAction;
use App\Actions\Orders\ListOrdersAction;
```

- [ ] **Step 4: HTTP-маршруты**

В `routes/api.php` добавить в группу `auth.telegram`:

```php
use App\Actions\Orders\ListOrdersAction;
use App\Actions\Orders\UpdateOrderAction;

// Orders
Route::get('/sessions/{id}/orders', ListOrdersAction::class);
Route::patch('/orders/{id}', UpdateOrderAction::class);
```

- [ ] **Step 5: Кнопка «Заказать» в GetRecipeAction**

В `app/Actions/Search/GetRecipeAction.php` найти `fromTelegram`. После загрузки рецепта получить активную сессию и добавить кнопку условно:

```php
// Добавить в конструктор:
public function __construct(
    // ... существующие зависимости ...
    private readonly \App\Handlers\Session\GetActiveSessionHandler $sessionHandler,
) {}

// В fromTelegram, при построении клавиатуры:
$hasSession = $this->sessionHandler->handle() !== null;

// Добавить в keyboard:
if ($hasSession) {
    $keyboard->addRow(
        InlineKeyboardButton::make('🛒 Заказать', callback_data: "recipe:order:{$recipe->id}"),
    );
}
```

> **Заметка для исполнителя:** точное место вставки зависит от текущей структуры `fromTelegram`. Кнопку «Заказать» добавить в отдельный ряд ниже «🔙 К поиску».

- [ ] **Step 6: Кнопка «Заказать» в BrowseRecipesAction**

В `app/Actions/Search/BrowseRecipesAction.php` найти noop-кнопку «Заказать» (callback `noop`). Заменить:

```php
// Было: InlineKeyboardButton::make('🛒 Заказать', callback_data: 'noop')
// Стало (условно):
if ($hasSession) {
    $row[] = InlineKeyboardButton::make('🛒 Заказать', callback_data: "recipe:order:{$recipeId}");
}
```

`$hasSession` — результат `$this->sessionHandler->handle() !== null`. Добавить `GetActiveSessionHandler` в конструктор `BrowseRecipesAction` (аналогично Step 5).

- [ ] **Step 7: Кнопка «Мои заказы» в StartAction**

В `app/Actions/StartAction.php` получить активную сессию и добавить кнопку условно:

```php
// Добавить в конструктор:
private readonly \App\Handlers\Session\GetActiveSessionHandler $sessionHandler,

// В fromTelegram, при построении клавиатуры:
$hasSession = $this->sessionHandler->handle() !== null;

// В нужный ряд (рядом с «🍸 Сессия» или отдельным рядом):
if ($hasSession) {
    $keyboard->addRow(
        InlineKeyboardButton::make('📋 Мои заказы', callback_data: 'orders:my'),
    );
}
```

- [ ] **Step 8: Снять `->skip` с feature-тестов**

В `tests/Feature/Actions/Orders/ListOrdersActionTest.php` удалить `->skip('routes registered in T6')` со всех тестов.

- [ ] **Step 9: E2E flow тест**

Файл: `tests/Feature/PhaseThreeOneFlowTest.php`

```php
<?php

use App\Enums\OrderStatus;
use App\Models\BarSession;
use App\Models\Order;
use App\Models\Recipe;
use App\Models\User;
use Carbon\CarbonImmutable;

afterEach(fn () => CarbonImmutable::setTestNow());

it('full flow: place → accept via HTTP → order accepted', function () {
    CarbonImmutable::setTestNow('2026-05-26 18:00:00');
    $bartender = User::factory()->bartender()->create();
    $guest     = User::factory()->create();
    $session   = BarSession::factory()->create(['started_at' => now()]);
    $recipe    = Recipe::factory()->create();

    // Гость создаёт заказ (через factory напрямую — Telegram-флоу не тестируется через HTTP)
    $order = Order::factory()->create([
        'session_id' => $session->id,
        'user_id'    => $guest->id,
        'recipe_id'  => $recipe->id,
    ]);

    expect($order->status)->toBe(OrderStatus::Pending);

    // Бармен принимает через PATCH
    $this->patchJson("/api/orders/{$order->id}?telegram_id={$bartender->telegram_id}", [
        'status'   => 'accepted',
        'quantity' => 3,
    ])->assertOk()->assertJsonPath('status', 'accepted');

    expect($order->fresh()->status)->toBe(OrderStatus::Accepted)
        ->and($order->fresh()->quantity)->toBe(3);
});

it('PATCH returns 409 when order already processed', function () {
    $bartender = User::factory()->bartender()->create();
    $order     = Order::factory()->accepted()->create();

    $this->patchJson("/api/orders/{$order->id}?telegram_id={$bartender->telegram_id}", [
        'status' => 'cancelled',
    ])->assertStatus(409);
});

it('GET /api/sessions/{id}/orders returns all orders', function () {
    CarbonImmutable::setTestNow('2026-05-26 18:00:00');
    $bartender = User::factory()->bartender()->create();
    $session   = BarSession::factory()->create(['started_at' => now()]);
    Order::factory()->count(2)->create(['session_id' => $session->id]);

    $this->getJson("/api/sessions/{$session->id}/orders?telegram_id={$bartender->telegram_id}")
        ->assertOk()
        ->assertJsonCount(2);
});
```

- [ ] **Step 10: Прогнать весь suite**

```bash
make tests
```

Ожидаемо: весь suite pass, включая разблокированные `ListOrdersActionTest` и новый `PhaseThreeOneFlowTest`.

- [ ] **Step 11: Pint + commit**

```bash
make pint-dirty
git add app/Actions/Orders/UpdateOrderAction.php \
        app/Providers/BusServiceProvider.php \
        routes/telegram.php \
        routes/api.php \
        app/Actions/Search/GetRecipeAction.php \
        app/Actions/Search/BrowseRecipesAction.php \
        app/Actions/StartAction.php \
        tests/Feature/Actions/Orders/ListOrdersActionTest.php \
        tests/Feature/PhaseThreeOneFlowTest.php
git commit --author="Claude <claude@anthropic.com>" -m "feat(bb-8): wire routes + UI buttons + BusServiceProvider + e2e tests"
```

**Handoff:** весь suite pass; pint-dirty-dry — 0; все 4 Telegram-маршрута и 2 HTTP-маршрута зарегистрированы.

---

## Task 7 (Финал): codebase.md + PR

**Depends on:** Task 6

**Files:**
- Modify: `.agents/knowledge/codebase.md`

- [ ] **Step 1: Обновить codebase.md**

В `.agents/knowledge/codebase.md`:

1. В таблице «Статус реализации фаз» поменять Phase 3.1 → ✅ Готово.

2. В разделе «Модели и связи» добавить подраздел `Order`:
   ```
   ### Order
   // PK: BIGINT GENERATED ALWAYS AS IDENTITY
   // Fillable: session_id, user_id, recipe_id, quantity, status
   // Casts: status → OrderStatus enum
   // $timestamps = true (created_at/updated_at)
   $order->session()  // BelongsTo BarSession
   $order->user()     // BelongsTo User
   $order->recipe()   // BelongsTo Recipe
   ```

3. В разделе «Telegram-маршруты» добавить:
   ```
   // Phase 3.1 (активны):
   onCallbackQueryData('recipe:order:{id}')   → PlaceOrderAction::fromTelegram
   onCallbackQueryData('orders:my')           → ListOrdersAction::fromTelegram
   
   Group [CanManageMiddleware]:
     onCallbackQueryData('order:qty:{id}:{n}') → AcceptOrderAction::fromTelegram
     onCallbackQueryData('order:cancel:{id}')  → CancelOrderAction::fromTelegram
   ```

4. В разделе «HTTP API-маршруты» добавить:
   ```
   GET    /api/sessions/{id}/orders → ListOrdersAction  [auth.telegram]
   PATCH  /api/orders/{id}          → UpdateOrderAction [auth.telegram]
   ```

5. В «Известные особенности» добавить:
   ```
   9. **Push-уведомления** — PlaceOrderAction шлёт сообщение менеджерам через
      `$bot->sendMessage(chat_id: $manager->telegram_id, ...)`. Accept/CancelOrderAction
      шлют гостю через `$bot->sendMessage(chat_id: $order->user->telegram_id, ...)`.
      Handler ничего не знает о Nutgram — это ответственность Action.
   
   10. **BusServiceProvider** — Place/Accept/CancelOrderData зарегистрированы в T6
       (а не в T2–T4) чтобы избежать конфликта параллельной записи при одновременном
       выполнении задач.
   ```

6. В разделе «Кнопки главного меню» добавить:
   ```
   📋 Мои заказы  → callback_data: 'orders:my'  (показывается только при активной сессии)
   ```

- [ ] **Step 2: Финальный прогон + pint**

```bash
make pint-dirty-dry
make tests
```

Ожидаемо: 0 изменений pint, весь suite зелёный.

- [ ] **Step 3: Commit + push + PR**

```bash
git add .agents/knowledge/codebase.md
git commit --author="Claude <claude@anthropic.com>" -m "docs(bb-8): update codebase.md — Phase 3.1 complete"

git push -u origin feature/bb8_orders

gh pr create \
  --title "bb8: orders — place, accept/cancel with qty, guest notifications" \
  --body "$(cat <<'EOF'
## Summary
- Гости заказывают коктейли (`recipe:order:{id}`) в активной бар-сессии; заказ создаётся через PlaceOrderHandler + Bus
- Бармен получает уведомление с inline-кнопками ×1..×5 и «Отказать»; одним тапом принимает или отклоняет
- AcceptOrderHandler / CancelOrderHandler — идемпотентны: повторный тап → `answerCallbackQuery("Заказ уже обработан")`
- Гость получает push-уведомление о решении (sendMessage на telegram_id)
- Страница «Мои заказы» (`orders:my`) — статусы заказов гостя за вечер; кнопка в StartAction и на карточке рецепта после заказа
- ALTER-миграция: `orders.session_id BIGINT → SMALLINT` + восстановление FK (Phase 3 CASCADE-дропнул его)
- HTTP API: `GET /api/sessions/{id}/orders`, `PATCH /api/orders/{id}`
- codebase.md обновлён; паттерн push-уведомлений задокументирован
EOF
)"
```

**Handoff (финальный):** весь suite pass; pint-dirty-dry — 0; codebase.md обновлён; PR открыт — ссылка в отчёте.

---

## Pre-PR проверка автора плана

- [x] Секция **Goal** заполнена.
- [x] **Branch** указана: `feature/bb8_orders`.
- [x] **Карта файлов** перечисляет все файлы из Steps, включая ancillary (`codebase.md`, `routes/*.php`, `BusServiceProvider`).
- [x] **Порядок исполнения** присутствует; параллельная группа (T2–T5) имеет однострочное обоснование.
- [x] У каждой задачи есть **Depends on**.
- [x] У каждой задачи есть секция **Files**.
- [x] Финальная задача содержит «Обновить codebase.md» и «Открыть PR».
- [x] Нет тел миграций в промежуточных задачах кроме T1 (там миграция — и есть задача).
