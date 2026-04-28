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

// All protected routes are callback queries; answerCallbackQuery is always valid here.
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
