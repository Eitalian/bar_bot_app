<?php

/** @var Nutgram $bot */

use App\Actions\Inventory\InventoryAction;
use App\Actions\Inventory\RemoveInventoryAction;
use App\Actions\Orders\AcceptOrderAction;
use App\Actions\Orders\CancelOrderAction;
use App\Actions\Orders\ListOrdersAction;
use App\Actions\Orders\PlaceOrderAction;
use App\Actions\Search\BrowseRecipesAction;
use App\Actions\Search\GetRecipeAction;
use App\Actions\Session\SessionAction;
use App\Actions\Session\StartSessionAction;
use App\Actions\StartAction;
use App\Middleware\CanManageMiddleware;
use App\Telegram\Conversations\AddInventoryConversation;
use App\Telegram\Conversations\FilterConversation;
use App\Telegram\Conversations\SearchByIngredientConversation;
use App\Telegram\Conversations\SearchByNameConversation;
use App\Telegram\Middleware\AuthenticateTelegramUser;
use Illuminate\Auth\Access\AuthorizationException;
use SergiX44\Nutgram\Nutgram;

$bot->middleware(AuthenticateTelegramUser::class);

$bot->onException(AuthorizationException::class, function (Nutgram $bot): void {
    $bot->answerCallbackQuery(text: '🚫 Нет доступа', show_alert: true);
});

$bot->onCommand('start', [StartAction::class, 'fromTelegram'])->description('Главное меню');
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
$bot->onCallbackQueryData('recipe:browse:{browseKey}:{pos}', [BrowseRecipesAction::class, 'fromTelegram']);
$bot->onCallbackQueryData('recipe:show:{id}', [GetRecipeAction::class, 'fromTelegram']);
$bot->onCallbackQueryData('browse:back', [StartAction::class, 'fromTelegram']);

$bot->onCallbackQueryData('noop', fn(Nutgram $bot) => $bot->answerCallbackQuery());

// Phase 3: Session
$bot->onCommand('session', [SessionAction::class, 'fromTelegram']);
$bot->onCallbackQueryData('cmd:session', [SessionAction::class, 'fromTelegram']);

$bot->group(function (Nutgram $bot): void {
    $bot->onCallbackQueryData('session:start', [StartSessionAction::class, 'fromTelegram']);
})->middleware(CanManageMiddleware::class);

// Phase 3.1: Orders
$bot->onCallbackQueryData('recipe:order:{id}',      [PlaceOrderAction::class, 'fromTelegram']);
$bot->onCallbackQueryData('recipe:order:{id}:{qty}', [PlaceOrderAction::class, 'confirm']);
$bot->onCallbackQueryData('orders:my', [ListOrdersAction::class, 'fromTelegram']);

$bot->group(function (Nutgram $bot): void {
    $bot->onCallbackQueryData('order:qty:{id}:{n}', [AcceptOrderAction::class, 'fromTelegram']);
    $bot->onCallbackQueryData('order:cancel:{id}', [CancelOrderAction::class, 'fromTelegram']);
})->middleware(CanManageMiddleware::class);
