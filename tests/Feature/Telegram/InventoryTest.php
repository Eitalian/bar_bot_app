<?php

use App\Models\Ingredient;
use App\Models\Inventory;
use App\Models\User;
use SergiX44\Nutgram\Nutgram;

it('/inventory отвечает списком инвентаря', function () {
    $telegramId = 111000010;
    User::factory()->create(['telegram_id' => $telegramId]);
    Inventory::factory()->count(2)->create();

    app(Nutgram::class)
        ->setCommonUser(tgUser($telegramId))
        ->hearText('/inventory')
        ->reply()
        ->assertCalled('sendMessage');
});

it('inventory:show отвечает списком инвентаря', function () {
    $telegramId = 111000011;
    User::factory()->create(['telegram_id' => $telegramId]);

    app(Nutgram::class)
        ->setCommonUser(tgUser($telegramId))
        ->hearCallbackQueryData('inventory:show')
        ->reply()
        ->assertCalled('sendMessage');
});

it('inventory:add — bartender проходит conversation до сохранения позиции', function () {
    $telegramId = 111000012;
    User::factory()->bartender()->create(['telegram_id' => $telegramId]);
    $ingredient = Ingredient::factory()->create(['name_ru' => 'Ром тёмный']);

    $bot = app(Nutgram::class)->willStartConversation()->setCommonUser(tgUser($telegramId));

    $bot->hearCallbackQueryData('inventory:add')
        ->reply()
        ->assertCalled('sendMessage');

    $bot->hearText('Ром')
        ->reply()
        ->assertCalled('sendMessage');

    $bot->hearCallbackQueryData("inventory:select:{$ingredient->id}")
        ->reply()
        ->assertCalled('sendMessage');

    $bot->hearText('500 мл')
        ->reply()
        ->assertCalled('sendMessage', 2)
        ->assertNoConversation();

    $this->assertDatabaseHas('bar_inventory', ['ingredient_id' => $ingredient->id, 'quantity' => 500]);
});

it('inventory:add — guest получает отказ доступа (CanManageMiddleware)', function () {
    $telegramId = 111000013;
    User::factory()->create(['telegram_id' => $telegramId]);

    app(Nutgram::class)
        ->setCommonUser(tgUser($telegramId))
        ->hearCallbackQueryData('inventory:add')
        ->reply()
        ->assertCalled('answerCallbackQuery');
});

it('inventory:remove:{id} — bartender удаляет позицию', function () {
    $telegramId = 111000014;
    User::factory()->bartender()->create(['telegram_id' => $telegramId]);
    $item = Inventory::factory()->create();

    app(Nutgram::class)
        ->setCommonUser(tgUser($telegramId))
        ->hearCallbackQueryData("inventory:remove:{$item->id}")
        ->reply()
        ->assertCalled('sendMessage', 2);

    $this->assertDatabaseMissing('bar_inventory', ['id' => $item->id]);
});
