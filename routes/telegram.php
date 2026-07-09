<?php

/** @var Nutgram $bot */

use App\UI\Http\Actions\Favorites\FavoriteToggleAction;
use App\UI\Http\Actions\Inventory\InventoryAction;
use App\UI\Http\Actions\Inventory\RemoveInventoryAction;
use App\UI\Http\Actions\Orders\AcceptOrderAction;
use App\UI\Http\Actions\Orders\CancelOrderAction;
use App\UI\Http\Actions\Orders\ListOrdersAction;
use App\UI\Http\Actions\Orders\PlaceOrderAction;
use App\UI\Http\Actions\Ratings\RateAction;
use App\UI\Http\Actions\Ratings\ShowRatingPickerAction;
use App\UI\Http\Actions\Search\BrowseRecipesAction;
use App\UI\Http\Actions\Search\GetRecipeAction;
use App\UI\Http\Actions\Session\SessionAction;
use App\UI\Http\Actions\Session\StartSessionAction;
use App\UI\Http\Actions\StartAction;
use App\UI\Middleware\AuthenticateTelegramUser;
use App\UI\Middleware\CanManageMiddleware;
use App\UI\Telegram\Conversations\AddInventoryConversation;
use App\UI\Telegram\Conversations\FilterConversation;
use App\UI\Telegram\Conversations\ListFavoritesConversation;
use App\UI\Telegram\Conversations\SearchByIngredientConversation;
use App\UI\Telegram\Conversations\SearchByNameConversation;
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
$bot->onCallbackQueryData('recipe:{id}:show', [GetRecipeAction::class, 'fromTelegram']);
$bot->onCallbackQueryData('browse:back', [StartAction::class, 'fromTelegram']);

$bot->onCallbackQueryData('noop', fn(Nutgram $bot) => $bot->answerCallbackQuery());

// Phase 3: Session
$bot->onCommand('session', [SessionAction::class, 'fromTelegram']);
$bot->onCallbackQueryData('cmd:session', [SessionAction::class, 'fromTelegram']);

$bot->group(function (Nutgram $bot): void {
    $bot->onCallbackQueryData('session:start', [StartSessionAction::class, 'fromTelegram']);
})->middleware(CanManageMiddleware::class);

// Phase 3.1: Orders
$bot->onCallbackQueryData('recipe:{id}:order', [PlaceOrderAction::class, 'fromTelegram']);
$bot->onCallbackQueryData('recipe:{id}:order:{qty}', [PlaceOrderAction::class, 'confirm']);
$bot->onCallbackQueryData('orders:my', [ListOrdersAction::class, 'fromTelegram']);

// Phase 4: Favorites & Ratings
$bot->onCommand('favorites', fn(Nutgram $bot) => ListFavoritesConversation::begin($bot));
$bot->onCallbackQueryData('recipe:{id}:favorite', [FavoriteToggleAction::class, 'fromTelegram']);
$bot->onCallbackQueryData('recipe:{id}:rate:{score}', [RateAction::class, 'fromTelegram'])->whereNumber('score');
$bot->onCallbackQueryData('recipe:{id}:rate:new', [ShowRatingPickerAction::class, 'fromTelegram']);

$bot->group(function (Nutgram $bot): void {
    $bot->onCallbackQueryData('order:qty:{id}:{n}', [AcceptOrderAction::class, 'fromTelegram']);
    $bot->onCallbackQueryData('order:cancel:{id}', [CancelOrderAction::class, 'fromTelegram']);
})->middleware(CanManageMiddleware::class);
