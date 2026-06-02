<?php

namespace App\Telegram\Conversations;

use App\Actions\Search\GetRecipeAction;
use App\Handlers\Favorites\ListFavoritesHandler;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use SergiX44\Nutgram\Conversations\Conversation;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

final class ListFavoritesConversation extends Conversation
{
    protected const PER_PAGE = 10;

    protected int $page = 0;

    protected array $recipeIds = [];

    public function start(Nutgram $bot): void
    {
        $userId = User::where('telegram_id', $bot->userId())->value('id');
        $recipes = app(ListFavoritesHandler::class)->handle($userId);

        if ($recipes->isEmpty()) {
            $bot->sendMessage('У тебя пока нет избранных рецептов 🤍');
            $this->end();

            return;
        }

        $this->recipeIds = $recipes->pluck('id')->toArray();
        $this->sendPage($bot);
        $this->next('handleInput');
    }

    public function handleInput(Nutgram $bot): void
    {
        $callbackData = $bot->callbackQuery()?->data;

        if ($callbackData === 'favorites:prev') {
            $this->page--;
            $this->editPage($bot);
            $bot->answerCallbackQuery();
            $this->next('handleInput');

            return;
        }

        if ($callbackData === 'favorites:next') {
            $this->page++;
            $this->editPage($bot);
            $bot->answerCallbackQuery();
            $this->next('handleInput');

            return;
        }

        if ($callbackData === 'browse:back') {
            $bot->answerCallbackQuery();
            $this->end();

            return;
        }

        if ($callbackData === 'noop') {
            $bot->answerCallbackQuery();
            $this->next('handleInput');

            return;
        }

        // Text input: a number 1–10
        $text = trim($bot->message()?->text ?? '');
        if (ctype_digit($text)) {
            $num = (int) $text;
            $offset = $this->page * self::PER_PAGE;
            $pageIds = array_slice($this->recipeIds, $offset, self::PER_PAGE);
            $count = count($pageIds);

            if ($num >= 1 && $num <= $count) {
                $recipeId = $pageIds[$num - 1];
                app(GetRecipeAction::class)->fromTelegram($bot, $recipeId);
                $this->end();

                return;
            }
        }

        // Invalid input — repeat hint
        $bot->sendMessage("Введи число от 1 до " . min(self::PER_PAGE, count($this->recipeIds) - $this->page * self::PER_PAGE) . ' или используй кнопки навигации.');
        $this->next('handleInput');
    }

    private function sendPage(Nutgram $bot): void
    {
        $bot->sendMessage(
            text: $this->buildPageText(),
            parse_mode: 'Markdown',
            reply_markup: $this->buildKeyboard(),
        );
    }

    private function editPage(Nutgram $bot): void
    {
        $bot->editMessageText(
            text: $this->buildPageText(),
            parse_mode: 'Markdown',
            reply_markup: $this->buildKeyboard(),
        );
    }

    private function buildPageText(): string
    {
        $offset = $this->page * self::PER_PAGE;
        $pageIds = array_slice($this->recipeIds, $offset, self::PER_PAGE);

        // Batch-load avg ratings for page recipes
        $avgRatings = DB::table('ratings')
            ->whereIn('recipe_id', $pageIds)
            ->selectRaw('recipe_id, ROUND(AVG(score)::numeric, 1) as avg_score')
            ->get()
            ->keyBy('recipe_id')
            ->map(fn($r) => $r->avg_score);

        // Load recipe data (name, abv, volume)
        $recipes = \App\Models\Recipe::whereIn('id', $pageIds)
            ->get(['id', 'name_ru', 'abv', 'volume'])
            ->keyBy('id');

        $lines = ["*⭐ Избранное* (стр. " . ($this->page + 1) . "/" . $this->totalPages() . ")\n"];
        $lines[] = '```';

        foreach ($pageIds as $i => $id) {
            $num = str_pad((string) ($offset + $i + 1), 2, ' ', STR_PAD_LEFT);
            $recipe = $recipes->get($id);

            if (! $recipe) {
                $lines[] = "{$num}. —";
                continue;
            }

            $name = mb_strlen($recipe->name_ru) > 19
                ? mb_substr($recipe->name_ru, 0, 19) . '…'
                : str_pad($recipe->name_ru, 20);

            $avgScore = $avgRatings->get($id);
            $rate = $avgScore !== null ? str_pad((string) $avgScore, 4, ' ', STR_PAD_LEFT) : '    ';

            $abv = $recipe->abv !== null ? str_pad((int) $recipe->abv . '%', 3, ' ', STR_PAD_LEFT) : '   ';

            $vol = $recipe->volume !== null ? str_pad($recipe->volume . 'мл', 5, ' ', STR_PAD_LEFT) : '     ';

            $lines[] = "{$num}. {$name} {$rate} {$abv} {$vol}";
        }

        $lines[] = '```';
        $lines[] = "\nВведи номер для просмотра рецепта.";

        return implode("\n", $lines);
    }

    private function buildKeyboard(): InlineKeyboardMarkup
    {
        $isFirst = $this->page === 0;
        $isLast = $this->page >= $this->totalPages() - 1;

        return InlineKeyboardMarkup::make()
            ->addRow(
                InlineKeyboardButton::make('<<', callback_data: $isFirst ? 'noop' : 'favorites:prev'),
                InlineKeyboardButton::make('🏠 Главная', callback_data: 'browse:back'),
                InlineKeyboardButton::make('>>', callback_data: $isLast ? 'noop' : 'favorites:next'),
            );
    }

    private function totalPages(): int
    {
        return (int) ceil(count($this->recipeIds) / self::PER_PAGE);
    }
}
