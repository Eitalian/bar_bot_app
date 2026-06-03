<?php

namespace App\Actions\Favorites;

use App\Data\Favorites\FavoritesPage;
use App\Handlers\Favorites\ListFavoritesHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

final class ListFavoritesAction
{
    private const PER_PAGE = 10;

    public function __construct(private readonly ListFavoritesHandler $handler) {}

    public function __invoke(Request $request): JsonResponse
    {
        $result = $this->handler->handle(Auth::id());

        return response()->json($result->items->values());
    }

    public function fromTelegram(Nutgram $bot, int $page = 1, bool $edit = false): FavoritesPage
    {
        $favoritesPage = $this->handler->handle(Auth::id(), $page, self::PER_PAGE);

        if ($favoritesPage->isEmpty()) {
            return $favoritesPage;
        }

        $text = $this->buildText($favoritesPage);
        $keyboard = $this->buildKeyboard($favoritesPage);

        if ($edit) {
            $bot->editMessageText(text: $text, parse_mode: 'Markdown', reply_markup: $keyboard);
            $bot->answerCallbackQuery();
        } else {
            $bot->sendMessage(text: $text, parse_mode: 'Markdown', reply_markup: $keyboard);
        }

        return $favoritesPage;
    }

    private function buildText(FavoritesPage $page): string
    {
        $offset = ($page->page - 1) * $page->perPage;
        $lines = ["*⭐ Избранное* (стр. {$page->page}/{$page->lastPage()})\n"];
        $lines[] = '```';

        foreach ($page->items as $i => $item) {
            $num = str_pad((string) ($offset + $i + 1), 2, ' ', STR_PAD_LEFT);

            $name = mb_strlen($item->name_ru) > 19
                ? mb_substr($item->name_ru, 0, 19) . '…'
                : str_pad($item->name_ru, 20);

            $rate = $item->avgRating !== null ? str_pad((string) $item->avgRating, 4, ' ', STR_PAD_LEFT) : '    ';
            $abv = $item->abv !== null ? str_pad((int) $item->abv . '%', 3, ' ', STR_PAD_LEFT) : '   ';
            $vol = $item->volume !== null ? str_pad($item->volume . 'мл', 5, ' ', STR_PAD_LEFT) : '     ';

            $lines[] = "{$num}. {$name} {$rate} {$abv} {$vol}";
        }

        $lines[] = '```';
        $lines[] = "\nВведи номер для просмотра рецепта.";

        return implode("\n", $lines);
    }

    private function buildKeyboard(FavoritesPage $page): InlineKeyboardMarkup
    {
        $isFirst = $page->page === 1;
        $isLast = $page->page >= $page->lastPage();

        return InlineKeyboardMarkup::make()
            ->addRow(
                InlineKeyboardButton::make('<<', callback_data: $isFirst ? 'noop' : 'favorites:prev'),
                InlineKeyboardButton::make('🏠 Главная', callback_data: 'browse:back'),
                InlineKeyboardButton::make('>>', callback_data: $isLast ? 'noop' : 'favorites:next'),
            );
    }
}
