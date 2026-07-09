<?php

use App\Models\Rating;
use App\Models\Recipe;
use App\Models\User;
use SergiX44\Nutgram\Nutgram;

it('recipe:{id}:rate:{score} сохраняет оценку и перерисовывает карточку', function () {
    $telegramId = 111000060;
    $user = User::factory()->create(['telegram_id' => $telegramId]);
    $recipe = Recipe::factory()->create();

    app(Nutgram::class)
        ->setCommonUser(tgUser($telegramId))
        ->hearCallbackQueryData("recipe:{$recipe->id}:rate:4")
        ->reply()
        ->assertCalled('editMessageText')
        ->assertCalled('answerCallbackQuery');

    $this->assertDatabaseHas('ratings', [
        'user_id' => $user->id,
        'recipe_id' => $recipe->id,
        'score' => 4,
    ]);
});

it('recipe:{id}:rate:new показывает клавиатуру выбора оценки', function () {
    $telegramId = 111000061;
    User::factory()->create(['telegram_id' => $telegramId]);
    $recipe = Recipe::factory()->create();

    app(Nutgram::class)
        ->setCommonUser(tgUser($telegramId))
        ->hearCallbackQueryData("recipe:{$recipe->id}:rate:new")
        ->reply()
        ->assertCalled('editMessageReplyMarkup')
        ->assertCalled('answerCallbackQuery');

    $this->assertDatabaseCount('ratings', 0);
});
