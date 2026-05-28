<?php

namespace App\Actions\Search;

use App\Handlers\Search\GetRecipeHandler;
use App\Handlers\Session\GetActiveSessionHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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

        return response()->json($recipe);
    }

    public function fromTelegram(Nutgram $bot, string $id): void
    {
        $recipe = $this->handler->handle($id);

        if (! $recipe) {
            $bot->answerCallbackQuery(text: 'Рецепт не найден 😔');

            return;
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

        $bot->editMessageText(
            text: $recipe->toTelegramMessage(),
            parse_mode: 'Markdown',
            reply_markup: $keyboard,
        );

        $bot->answerCallbackQuery();
    }
}
