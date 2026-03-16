<?php

namespace App\Telegram\Conversations;

use App\Models\Ingredient;
use App\Models\Recipe;
use SergiX44\Nutgram\Conversations\Conversation;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

class SearchByIngredientConversation extends Conversation
{
    protected const PER_PAGE = 5;

    /** @var string[] */
    protected array $selectedIngredients = [];

    protected int $page = 1;

    public function start(Nutgram $bot): void
    {
        $bot->sendMessage(
            "🧪 *Поиск по ингредиентам*\n\n"
            . "Введите название ингредиента (на английском, например: `bourbon`, `lime_juice`, `mint`).\n\n"
            . "Можно добавить несколько — бот найдёт коктейли, в которых *все* они есть.\n\n"
            . 'Чтобы начать поиск, напишите /done',
            parse_mode: 'Markdown',
        );
        $this->next('handleIngredient');
    }

    public function handleIngredient(Nutgram $bot): void
    {
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

        // Ищем ингредиент в БД (нечёткий поиск)
        $found = Ingredient::where('id', 'ilike', "%{$text}%")
            ->orWhere('name_en', 'ilike', "%{$text}%")
            ->orWhere('name_ru', 'ilike', "%{$text}%")
            ->take(5)
            ->get();

        if ($found->isEmpty()) {
            $bot->sendMessage("❌ Ингредиент *\"{$text}\"* не найден в базе. Попробуйте другой.", parse_mode: 'Markdown');
            $this->next('handleIngredient');

            return;
        }

        if ($found->count() === 1) {
            $ing = $found->first();
            $this->selectedIngredients[] = $ing->id;
            $list = implode(', ', $this->selectedIngredients);
            $bot->sendMessage(
                "✅ Добавлен: *{$ing->id}*\n\nТекущий список: `{$list}`\n\nДобавьте ещё или напишите /done для поиска.",
                parse_mode: 'Markdown',
            );
        } else {
            // Несколько совпадений — показываем кнопки
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

        $count = count($this->selectedIngredients);
        $list = implode(', ', $this->selectedIngredients);

        // Ищем рецепты где есть ВСЕ выбранные ингредиенты
        $recipeIds = \DB::table('recipe_ingredients')
            ->whereIn('ingredient_id', $this->selectedIngredients)
            ->groupBy('recipe_id')
            ->havingRaw('COUNT(DISTINCT ingredient_id) = ?', [$count])
            ->pluck('recipe_id');

        $recipes = Recipe::whereIn('id', $recipeIds)->orderBy('name_ru')->get();

        if ($recipes->isEmpty()) {
            $bot->sendMessage(
                "😔 Нет коктейлей со *всеми* ингредиентами: `{$list}`\n\n"
                . 'Попробуйте убрать один из ингредиентов.',
                parse_mode: 'Markdown',
            );

            return;
        }

        $text = "🧪 Ингредиенты: `{$list}`\nНайдено коктейлей: *{$recipes->count()}*\n\n";
        $keyboard = InlineKeyboardMarkup::make();

        foreach ($recipes->take(10) as $recipe) {
            $abv = $recipe->abv ? " {$recipe->abv}%" : '';
            $text .= "• {$recipe->name_ru}{$abv}\n";
            $keyboard->addRow(
                InlineKeyboardButton::make("🍹 {$recipe->name_ru}", callback_data: "recipe:show:{$recipe->id}"),
            );
        }

        if ($recipes->count() > 10) {
            $text .= "\n_...и ещё " . ($recipes->count() - 10) . ' рецептов_';
        }

        $bot->sendMessage(text: $text, parse_mode: 'Markdown', reply_markup: $keyboard);
    }
}
