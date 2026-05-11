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
    $bot->onCallbackQueryData('inventory:add', fn(Nutgram $bot) => AddInventoryConversation::begin($bot));
    $bot->onCallbackQueryData('inventory:remove:{id}', [RemoveInventoryAction::class, 'fromTelegram']);
})->middleware(CanManageMiddleware::class);

// Phase 2: Search
$bot->onCallbackQueryData('cmd:search', fn(Nutgram $bot) => SearchByNameConversation::begin($bot));
$bot->onCallbackQueryData('cmd:ingredients', fn(Nutgram $bot) => SearchByIngredientConversation::begin($bot));
$bot->onCallbackQueryData('cmd:filter', fn(Nutgram $bot) => FilterConversation::begin($bot));

// Phase 2: Recipe browsing
$bot->onCallbackQueryData('recipe:browse:{browseKey}:{pos}', RecipeBrowseHandler::class);
$bot->onCallbackQueryData('recipe:show:{id}', RecipeHandler::class);
$bot->onCallbackQueryData('browse:back', StartHandler::class);

$bot->onCallbackQueryData('noop', fn(Nutgram $bot) => $bot->answerCallbackQuery());
