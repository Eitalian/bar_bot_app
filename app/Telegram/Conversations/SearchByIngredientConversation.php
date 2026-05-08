<?php

namespace App\Telegram\Conversations;

use App\Data\Search\SearchByIngredientData;
use App\Handlers\Search\SearchByIngredientHandler;
use App\Models\Ingredient;
use App\Services\BrowseContext;
use SergiX44\Nutgram\Conversations\Conversation;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

final class SearchByIngredientConversation extends Conversation
{
    /** @var string[] */
    protected array $selectedIngredients = [];

    public function start(Nutgram $bot): void
    {
        $bot->sendMessage(
            "🧪 *Поиск по ингредиентам*\n\n"
            . "Введите название ингредиента (на русском или английском, например: `водка`, `bourbon`, `lime juice`).\n\n"
            . "Можно добавить несколько — бот найдёт коктейли, в которых *все* они есть.\n\n"
            . 'Чтобы начать поиск, напишите /done',
            parse_mode: 'Markdown',
        );
        $this->next('handleIngredient');
    }

    public function handleIngredient(Nutgram $bot): void
    {
        // Обработка нажатия кнопки выбора ингредиента из нескольких совпадений
        $callbackData = $bot->callbackQuery()?->data ?? '';
        if (str_starts_with($callbackData, 'ing:add:')) {
            $ingId = substr($callbackData, 8);
            $this->selectedIngredients[] = $ingId;
            $list = implode(', ', $this->selectedIngredients);
            $bot->answerCallbackQuery();
            $bot->sendMessage(
                "✅ Добавлен: *{$ingId}*\n\nТекущий список: `{$list}`\n\nДобавьте ещё или напишите /done",
                parse_mode: 'Markdown',
            );
            $this->next('handleIngredient');

            return;
        }

        $text = trim($bot->message()->text ?? '');

        if ($text === '/done' || $text === 'done') {
            $this->showResults($bot);
            $this->end();

            return;
        }

        if ($text === '/clear') {
            $this->selectedIngredients = [];
            $bot->sendMessage('✅ Список очищен. Введите ингредиент:');
            $this->next('handleIngredient');

            return;
        }

        if (empty($text)) {
            $this->next('handleIngredient');

            return;
        }

        $found = Ingredient::where('name_en', 'ilike', "%{$text}%")
            ->orWhere('name_ru', 'ilike', "%{$text}%")
            ->take(5)
            ->get();

        if ($found->isEmpty()) {
            $bot->sendMessage(
                "❌ Ингредиент *\"{$text}\"* не найден. Попробуйте другое название.",
                parse_mode: 'Markdown',
            );
            $this->next('handleIngredient');

            return;
        }

        if ($found->count() === 1) {
            $ing = $found->first();
            $this->selectedIngredients[] = $ing->id;
            $list = implode(', ', $this->selectedIngredients);
            $bot->sendMessage(
                "✅ Добавлен: *{$ing->id}*\n\nТекущий список: `{$list}`\n\nДобавьте ещё или напишите /done",
                parse_mode: 'Markdown',
            );
        } else {
            $keyboard = InlineKeyboardMarkup::make();
            foreach ($found as $ing) {
                $keyboard->addRow(
                    InlineKeyboardButton::make(
                        $ing->id . ($ing->name_ru ? " ({$ing->name_ru})" : ''),
                        callback_data: "ing:add:{$ing->id}",
                    ),
                );
            }
            $bot->sendMessage('Уточните ингредиент:', reply_markup: $keyboard);
        }

        $this->next('handleIngredient');
    }

    private function showResults(Nutgram $bot): void
    {
        if (empty($this->selectedIngredients)) {
            $bot->sendMessage('❌ Не выбрано ни одного ингредиента.');

            return;
        }

        $data = new SearchByIngredientData(ingredientIds: $this->selectedIngredients);
        $recipes = app(SearchByIngredientHandler::class)->handle($data);
        $list = implode(', ', $this->selectedIngredients);

        if ($recipes->isEmpty()) {
            $bot->sendMessage(
                "😔 Нет коктейлей со *всеми* ингредиентами: `{$list}`\n\nПопробуйте убрать один из ингредиентов.",
                parse_mode: 'Markdown',
            );

            return;
        }

        $browseKey = app(BrowseContext::class)->store($recipes->pluck('id')->all(), $bot->userId());
        $text = "🧪 Ингредиенты: `{$list}`\nНайдено коктейлей: *{$recipes->count()}*\n\n";
        $keyboard = InlineKeyboardMarkup::make();

        foreach ($recipes->take(10)->values() as $pos => $recipe) {
            $abv = $recipe->abv ? " {$recipe->abv}%" : '';
            $text .= "• {$recipe->name_ru}{$abv}\n";
            $keyboard->addRow(
                InlineKeyboardButton::make(
                    "🍹 {$recipe->name_ru}",
                    callback_data: "recipe:browse:{$browseKey}:{$pos}",
                ),
            );
        }

        if ($recipes->count() > 10) {
            $text .= "\n_...и ещё " . ($recipes->count() - 10) . ' рецептов_';
        }

        $bot->sendMessage(text: $text, parse_mode: 'Markdown', reply_markup: $keyboard);
    }
}
