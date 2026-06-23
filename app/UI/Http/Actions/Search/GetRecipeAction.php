<?php

namespace App\UI\Http\Actions\Search;

use App\Data\Search\GetRecipeResult;
use App\Handlers\Search\GetRecipeHandler;
use App\Handlers\Session\GetActiveSessionHandler;
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
        $result = $this->handler->handle($id, Auth::id());

        if ($result->recipe === null) {
            return response()->json(['message' => 'Рецепт не найден'], 404);
        }

        return response()->json([
            ...$result->recipe->toArray(),
            'is_favorite' => $result->isFavorite,
            'user_rating' => $result->userRating,
            'avg_rating' => $result->avgRating,
            'ratings_count' => $result->ratingsCount,
        ]);
    }

    public function fromTelegram(Nutgram $bot, string $id): void
    {
        $result = $this->handler->handle($id, Auth::id());

        if ($result->recipe === null) {
            $bot->answerCallbackQuery(text: 'Рецепт не найден 😔');

            return;
        }

        $ratingLine = $this->buildRatingLine($result);

        $keyboard = InlineKeyboardMarkup::make()
            ->addRow(
                InlineKeyboardButton::make('🔙 К поиску', callback_data: 'browse:back'),
            );

        if ($this->sessionHandler->handle() !== null) {
            $keyboard->addRow(
                InlineKeyboardButton::make('🛒 Заказать', callback_data: "recipe:{$result->recipe->id}:order"),
            );
        }

        $favoriteLabel = $result->isFavorite ? '❤️ Убрать из избранного' : '🤍 В избранное';
        $keyboard->addRow(
            InlineKeyboardButton::make($favoriteLabel, callback_data: "recipe:{$id}:favorite"),
        );

        $this->addRatingButtons($keyboard, $id, $result->userRating);

        $bot->editMessageText(
            text: $result->recipe->toTelegramMessage() . $ratingLine,
            parse_mode: 'Markdown',
            reply_markup: $keyboard,
        );

        $bot->answerCallbackQuery();
    }

    private function buildRatingLine(GetRecipeResult $result): string
    {
        if ($result->ratingsCount === 0) {
            return '';
        }

        return $result->userRating !== null
            ? "\n⭐ {$result->avgRating} ({$result->ratingsCount} оценок) · ваша: {$result->userRating}⭐"
            : "\n⭐ {$result->avgRating} ({$result->ratingsCount} оценок)";
    }

    private function addRatingButtons(InlineKeyboardMarkup $keyboard, string $id, ?int $userRating): void
    {
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
    }
}
