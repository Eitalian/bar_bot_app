<?php

/** @var Nutgram $bot */

// use App\Telegram\Handlers\StartHandler;
// use App\Telegram\Handlers\HelpHandler;
// use App\Telegram\Conversations\SearchByIngredientConversation;
// use App\Telegram\Conversations\SearchByNameConversation;
// use App\Telegram\Conversations\FilterConversation;
use SergiX44\Nutgram\Nutgram;

/*
|--------------------------------------------------------------------------
| Nutgram Handlers
|--------------------------------------------------------------------------
|
| Here is where you can register telegram handlers for Nutgram. These
| handlers are loaded by the NutgramServiceProvider. Enjoy!
|
*/

$bot->onCommand('start', function (Nutgram $bot) {
    $bot->sendMessage('Hello, world!');
})->description('The start command!');

// $bot->onCommand('start', StartHandler::class);
// $bot->onCommand('help', HelpHandler::class);
// $bot->onCommand('search', SearchByNameConversation::class);
// $bot->onCommand('ingredients', SearchByIngredientConversation::class);
// $bot->onCommand('filter', FilterConversation::class);

// Инлайн кнопки
// $bot->onCallbackQueryData('recipe:show:{id}', \App\Telegram\Handlers\RecipeHandler::class);
// $bot->onCallbackQueryData('search:page:{page}:{query}', \App\Telegram\Handlers\PaginationHandler::class);
// $bot->onCallbackQueryData('filter:glass:{glass}', \App\Telegram\Handlers\FilterGlassHandler::class);
// $bot->onCallbackQueryData('filter:tag:{tag}', \App\Telegram\Handlers\FilterTagHandler::class);
// $bot->onCallbackQueryData('filter:abv:{min}:{max}', \App\Telegram\Handlers\FilterAbvHandler::class);
