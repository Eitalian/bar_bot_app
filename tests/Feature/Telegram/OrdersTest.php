<?php

use App\Enums\OrderStatus;
use App\Models\BarSession;
use App\Models\Order;
use App\Models\Recipe;
use App\Models\User;
use Carbon\CarbonImmutable;
use SergiX44\Nutgram\Nutgram;

afterEach(fn () => CarbonImmutable::setTestNow());

it('recipe:{id}:order показывает выбор количества', function () {
    $telegramId = 111000040;
    User::factory()->create(['telegram_id' => $telegramId]);
    $recipe = Recipe::factory()->create();

    app(Nutgram::class)
        ->setCommonUser(tgUser($telegramId))
        ->hearCallbackQueryData("recipe:{$recipe->id}:order")
        ->reply()
        ->assertCalled('editMessageReplyMarkup')
        ->assertCalled('answerCallbackQuery');
});

it('recipe:{id}:order:{qty} создаёт заказ с App\\Models\\User.id, а не telegram id (регрессия BB-12)', function () {
    CarbonImmutable::setTestNow('2026-05-10 18:00:00');
    $telegramId = 111000041;
    $guest = User::factory()->create(['telegram_id' => $telegramId]);
    $recipe = Recipe::factory()->create();
    BarSession::factory()->create(['started_at' => now()]);

    app(Nutgram::class)
        ->setCommonUser(tgUser($telegramId))
        ->hearCallbackQueryData("recipe:{$recipe->id}:order:3")
        ->reply()
        ->assertCalled('answerCallbackQuery');

    $order = Order::sole();
    expect($order->user_id)->toBe($guest->id)
        ->and($order->user_id)->not->toBe($telegramId)
        ->and($order->recipe_id)->toBe($recipe->id)
        ->and($order->quantity)->toBe(3)
        ->and($order->status)->toBe(OrderStatus::Pending);
});

it('recipe:{id}:order:{qty} без активной сессии — отказ, заказ не создаётся', function () {
    $telegramId = 111000042;
    User::factory()->create(['telegram_id' => $telegramId]);
    $recipe = Recipe::factory()->create();

    app(Nutgram::class)
        ->setCommonUser(tgUser($telegramId))
        ->hearCallbackQueryData("recipe:{$recipe->id}:order:2")
        ->reply()
        ->assertCalled('answerCallbackQuery');

    $this->assertDatabaseCount('orders', 0);
});

it('orders:my — гость видит свои заказы', function () {
    $telegramId = 111000043;
    $guest = User::factory()->create(['telegram_id' => $telegramId]);
    Order::factory()->create(['user_id' => $guest->id]);

    app(Nutgram::class)
        ->setCommonUser(tgUser($telegramId))
        ->hearCallbackQueryData('orders:my')
        ->reply()
        ->assertCalled('sendMessage');
});

it('orders:my — бармен видит заказы активной сессии', function () {
    CarbonImmutable::setTestNow('2026-05-10 18:00:00');
    $telegramId = 111000044;
    User::factory()->bartender()->create(['telegram_id' => $telegramId]);
    $session = BarSession::factory()->create(['started_at' => now()]);
    Order::factory()->create(['session_id' => $session->id]);

    app(Nutgram::class)
        ->setCommonUser(tgUser($telegramId))
        ->hearCallbackQueryData('orders:my')
        ->reply()
        ->assertCalled('sendMessage');
});

it('order:qty:{id}:{n} — бармен принимает заказ', function () {
    $telegramId = 111000045;
    User::factory()->bartender()->create(['telegram_id' => $telegramId]);
    $order = Order::factory()->create(['quantity' => 2]);

    app(Nutgram::class)
        ->setCommonUser(tgUser($telegramId))
        ->hearCallbackQueryData("order:qty:{$order->id}:2")
        ->reply()
        ->assertCalled('answerCallbackQuery')
        ->assertCalled('sendMessage');

    expect($order->fresh()->status)->toBe(OrderStatus::Accepted);
});

it('order:qty:{id}:{n} — гость получает отказ доступа (CanManageMiddleware)', function () {
    $telegramId = 111000046;
    User::factory()->create(['telegram_id' => $telegramId]);
    $order = Order::factory()->create();

    app(Nutgram::class)
        ->setCommonUser(tgUser($telegramId))
        ->hearCallbackQueryData("order:qty:{$order->id}:1")
        ->reply()
        ->assertCalled('answerCallbackQuery');

    expect($order->fresh()->status)->toBe(OrderStatus::Pending);
});

it('order:cancel:{id} — бармен отклоняет заказ', function () {
    $telegramId = 111000047;
    User::factory()->bartender()->create(['telegram_id' => $telegramId]);
    $order = Order::factory()->create();

    app(Nutgram::class)
        ->setCommonUser(tgUser($telegramId))
        ->hearCallbackQueryData("order:cancel:{$order->id}")
        ->reply()
        ->assertCalled('answerCallbackQuery')
        ->assertCalled('sendMessage');

    expect($order->fresh()->status)->toBe(OrderStatus::Cancelled);
});

it('order:cancel:{id} — гость получает отказ доступа (CanManageMiddleware)', function () {
    $telegramId = 111000048;
    User::factory()->create(['telegram_id' => $telegramId]);
    $order = Order::factory()->create();

    app(Nutgram::class)
        ->setCommonUser(tgUser($telegramId))
        ->hearCallbackQueryData("order:cancel:{$order->id}")
        ->reply()
        ->assertCalled('answerCallbackQuery');

    expect($order->fresh()->status)->toBe(OrderStatus::Pending);
});
