<?php

namespace App\Actions\Search;

use App\Handlers\Search\GetRecipeHandler;
use App\Handlers\Session\GetActiveSessionHandler;
use App\Models\Favorite;
use App\Models\Rating;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

final class GetRecipeAction
{
    public function __construct(
        private GetRecipeHandler $handler,
        private GetActiveSessionHandler $sessionHandler,
    ) {}

    public function __invoke(Request $request, string $id): JsonResponse
    {
        $recipe = $this->handler->handle($id);

        if (! $recipe) {
            return response()->json(['message' => 'Рецепт не найден'], 404);
        }

        $userId = Auth::id();
        $isFavorite = $userId
            ? Favorite::where('user_id', $userId)->where('recipe_id', $id)->exists()
            : false;
        $userRating = $userId
            ? Rating::where('user_id', $userId)->where('recipe_id', $id)->value('score')
            : null;
        $stats = Rating::where('recipe_id', $id)
            ->selectRaw('ROUND(AVG(score), 1) as avg, COUNT(*) as count')
            ->first();

        return response()->json([
            ...$recipe->toArray(),
            'is_favorite' => $isFavorite,
            'user_rating' => $userRating,
            'avg_rating' => $stats?->avg,
            'ratings_count' => (int) ($stats?->count ?? 0),
        ]);
    }

    public function fromTelegram(Nutgram $bot, string $id): void
    {
        $recipe = $this->handler->handle($id);

        if (! $recipe) {
            $bot->answerCallbackQuery(text: 'Рецепт не найден 😔');

            return;
        }

        $userId = User::where('telegram_id', $bot->userId())->value('id');
        $isFavorite = $userId
            ? Favorite::where('user_id', $userId)->where('recipe_id', $id)->exists()
            : false;
        $userRating = $userId
            ? Rating::where('user_id', $userId)->where('recipe_id', $id)->value('score')
            : null;
        $stats = Rating::where('recipe_id', $id)
            ->selectRaw('ROUND(AVG(score), 1) as avg, COUNT(*) as count')
            ->first();
        $avg = $stats?->avg;
        $count = (int) ($stats?->count ?? 0);

        $ratingLine = '';
        if ($count > 0) {
            $ratingLine = $userRating !== null
                ? "\n⭐ {$avg} ({$count} оценок) · ваша: {$userRating}⭐"
                : "\n⭐ {$avg} ({$count} оценок)";
        }

        $keyboard = InlineKeyboardMarkup::make()
            ->addRow(
                InlineKeyboardButton::make('🔙 К поиску', callback_data: 'browse:back'),
            );

        if ($this->sessionHandler->handle() !== null) {
            $keyboard->addRow(
                InlineKeyboardButton::make('🛒 Заказать', callback_data: "recipe:order:{$recipe->id}"),
            );
        }

        $favoriteLabel = $isFavorite ? '❤️ Убрать из избранного' : '🤍 В избранное';
        $keyboard->addRow(
            InlineKeyboardButton::make($favoriteLabel, callback_data: "recipe:{$id}:favorite"),
        );

        if ($userRating === null) {
            $keyboard->addRow(
                InlineKeyboardButton::make('⭐1', callback_data: "recipe:{$id}:rate:1"),
                InlineKeyboardButton::make('⭐2', callback_data: "recipe:{$id}:rate:2"),
                InlineKeyboardButton::make('⭐3', callback_data: "recipe:{$id}:rate:3"),
                InlineKeyboardButton::make('⭐4', callback_data: "recipe:{$id}:rate:4"),
                InlineKeyboardButton::make('⭐5', callback_data: "recipe:{$id}:rate:5"),
            );
        } else {
            $keyboard->addRow(
                InlineKeyboardButton::make("Переоценить ({$userRating}⭐)", callback_data: "recipe:{$id}:rate:new"),
            );
        }

        $bot->editMessageText(
            text: $recipe->toTelegramMessage() . $ratingLine,
            parse_mode: 'Markdown',
            reply_markup: $keyboard,
        );

        $bot->answerCallbackQuery();
    }
}
