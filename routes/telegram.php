<?php

/** @var Nutgram $bot */

use App\Actions\Inventory\InventoryAction;
use App\Actions\Inventory\RemoveInventoryAction;
use App\Telegram\Conversations\AddInventoryConversation;
use App\Telegram\Handlers\StartHandler;
use App\Telegram\Middleware\AuthenticateTelegramUser;
use SergiX44\Nutgram\Nutgram;

/*
|--------------------------------------------------------------------------
| Nutgram Handlers
|--------------------------------------------------------------------------
*/

$bot->middleware(AuthenticateTelegramUser::class);

$bot->onCommand('start', StartHandler::class)->description('Главное меню');
$bot->onCommand('inventory', [InventoryAction::class, 'fromTelegram'])->description('Инвентарь бара');

$bot->onCallbackQueryData('inventory:show', [InventoryAction::class, 'fromTelegram']);
$bot->onCallbackQueryData('inventory:add', fn (Nutgram $bot) => AddInventoryConversation::begin($bot));
$bot->onCallbackQueryData('inventory:remove:{id}', [RemoveInventoryAction::class, 'fromTelegram']);

// Заглушка для неактивных кнопок
$bot->onCallbackQueryData('noop', fn (Nutgram $bot) => $bot->answerCallbackQuery());

// Не активированы до Phase 2:
// $bot->onCommand('search', SearchByNameConversation::class);
// $bot->onCommand('ingredients', SearchByIngredientConversation::class);
// $bot->onCommand('filter', FilterConversation::class);
// $bot->onCallbackQueryData('recipe:show:{id}', \App\Telegram\Handlers\RecipeHandler::class);
