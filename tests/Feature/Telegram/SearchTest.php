<?php

use App\Models\Ingredient;
use App\Models\Recipe;
use App\Models\User;
use App\Services\BrowseContext;
use SergiX44\Nutgram\Nutgram;

it('cmd:search — conversation находит рецепт по названию', function () {
    $telegramId = 111000020;
    User::factory()->create(['telegram_id' => $telegramId]);
    Recipe::factory()->create(['name_ru' => 'Маргарита']);

    $bot = app(Nutgram::class)->willStartConversation()->setCommonUser(tgUser($telegramId));

    $bot->hearCallbackQueryData('cmd:search')
        ->reply()
        ->assertCalled('sendMessage');

    $bot->hearText('Маргарита')
        ->reply()
        ->assertCalled('sendMessage');
});

it('cmd:ingredients — conversation находит рецепт по ингредиенту через /done', function () {
    $telegramId = 111000021;
    User::factory()->create(['telegram_id' => $telegramId]);
    $ing = Ingredient::factory()->create(['name_ru' => 'Лайм']);
    $recipe = Recipe::factory()->create();
    \App\Models\RecipeIngredient::create(['recipe_id' => $recipe->id, 'ingredient_id' => $ing->id, 'sort_order' => 1]);

    $bot = app(Nutgram::class)->willStartConversation()->setCommonUser(tgUser($telegramId));

    $bot->hearCallbackQueryData('cmd:ingredients')
        ->reply()
        ->assertCalled('sendMessage');

    $bot->hearText('Лайм')
        ->reply()
        ->assertCalled('sendMessage');

    $bot->hearText('/done')
        ->reply()
        ->assertCalled('sendMessage')
        ->assertNoConversation();
});

it('cmd:filter — conversation фильтрует по крепости', function () {
    $telegramId = 111000022;
    User::factory()->create(['telegram_id' => $telegramId]);
    Recipe::factory()->create(['abv' => 5.0]);

    $bot = app(Nutgram::class)->willStartConversation()->setCommonUser(tgUser($telegramId));

    $bot->hearCallbackQueryData('cmd:filter')
        ->reply()
        ->assertCalled('sendMessage');

    $bot->hearCallbackQueryData('filter:start:abv')
        ->reply()
        ->assertCalled('sendMessage');

    $bot->hearCallbackQueryData('filter:abv:0:10')
        ->reply()
        ->assertCalled('sendMessage')
        ->assertNoConversation();
});

it('recipe:browse:{key}:{pos} — постранично листает и не падает (регрессия BB-12)', function () {
    $telegramId = 111000023;
    User::factory()->create(['telegram_id' => $telegramId]);
    $recipe = Recipe::factory()->create();

    $browseKey = app(BrowseContext::class)->store([$recipe->id], $telegramId);

    app(Nutgram::class)
        ->setCommonUser(tgUser($telegramId))
        ->hearCallbackQueryData("recipe:browse:{$browseKey}:0")
        ->reply()
        ->assertCalled('editMessageText')
        ->assertCalled('answerCallbackQuery');
});

it('recipe:{id}:show отвечает карточкой рецепта', function () {
    $telegramId = 111000024;
    User::factory()->create(['telegram_id' => $telegramId]);
    $recipe = Recipe::factory()->create();

    app(Nutgram::class)
        ->setCommonUser(tgUser($telegramId))
        ->hearCallbackQueryData("recipe:{$recipe->id}:show")
        ->reply()
        ->assertCalled('editMessageText')
        ->assertCalled('answerCallbackQuery');
});

it('browse:back возвращает в главное меню', function () {
    $telegramId = 111000025;
    User::factory()->create(['telegram_id' => $telegramId]);

    app(Nutgram::class)
        ->setCommonUser(tgUser($telegramId))
        ->hearCallbackQueryData('browse:back')
        ->reply()
        ->assertCalled('sendMessage');
});

it('noop только подтверждает callback без ошибок', function () {
    $telegramId = 111000026;
    User::factory()->create(['telegram_id' => $telegramId]);

    app(Nutgram::class)
        ->setCommonUser(tgUser($telegramId))
        ->hearCallbackQueryData('noop')
        ->reply()
        ->assertCalled('answerCallbackQuery');
});
