<?php

use App\Models\Favorite;
use App\Models\Recipe;
use App\Models\User;
use SergiX44\Nutgram\Nutgram;

it('/favorites — без избранного сразу завершает conversation', function () {
    $telegramId = 111000050;
    User::factory()->create(['telegram_id' => $telegramId]);

    app(Nutgram::class)
        ->willStartConversation()
        ->setCommonUser(tgUser($telegramId))
        ->hearText('/favorites')
        ->reply()
        ->assertCalled('sendMessage')
        ->assertNoConversation();
});

it('/favorites — с избранным, выбор рецепта по номеру открывает карточку', function () {
    $telegramId = 111000051;
    $user = User::factory()->create(['telegram_id' => $telegramId]);
    $recipe = Recipe::factory()->create();
    Favorite::create(['user_id' => $user->id, 'recipe_id' => $recipe->id]);

    $bot = app(Nutgram::class)->willStartConversation()->setCommonUser(tgUser($telegramId));

    $bot->hearText('/favorites')
        ->reply()
        ->assertCalled('sendMessage');

    $bot->hearText('1')
        ->reply()
        ->assertCalled('editMessageText')
        ->assertNoConversation();
});

it('/favorites — browse:back завершает conversation', function () {
    $telegramId = 111000052;
    $user = User::factory()->create(['telegram_id' => $telegramId]);
    $recipe = Recipe::factory()->create();
    Favorite::create(['user_id' => $user->id, 'recipe_id' => $recipe->id]);

    $bot = app(Nutgram::class)->willStartConversation()->setCommonUser(tgUser($telegramId));

    $bot->hearText('/favorites')
        ->reply()
        ->assertCalled('sendMessage');

    $bot->hearCallbackQueryData('browse:back')
        ->reply()
        ->assertCalled('sendMessage')
        ->assertNoConversation();
});

it('recipe:{id}:favorite — переключает избранное и перерисовывает карточку', function () {
    $telegramId = 111000053;
    $user = User::factory()->create(['telegram_id' => $telegramId]);
    $recipe = Recipe::factory()->create();

    app(Nutgram::class)
        ->setCommonUser(tgUser($telegramId))
        ->hearCallbackQueryData("recipe:{$recipe->id}:favorite")
        ->reply()
        ->assertCalled('editMessageText')
        ->assertCalled('answerCallbackQuery');

    $this->assertDatabaseHas('favorites', ['user_id' => $user->id, 'recipe_id' => $recipe->id]);
});
