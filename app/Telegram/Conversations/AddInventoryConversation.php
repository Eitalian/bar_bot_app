<?php

namespace App\Telegram\Conversations;

use App\Actions\Inventory\AddInventoryAction;
use App\Models\Ingredient;
use SergiX44\Nutgram\Conversations\Conversation;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

class AddInventoryConversation extends Conversation
{
    protected ?string $ingredientId = null;

    public function start(Nutgram $bot): void
    {
        $bot->sendMessage('🔍 Введите название ингредиента:');
        $this->next('searchIngredient');
    }

    public function searchIngredient(Nutgram $bot): void
    {
        $query = trim($bot->message()?->text ?? '');

        if (empty($query)) {
            $bot->sendMessage('❌ Введите хотя бы один символ.');
            $this->next('searchIngredient');

            return;
        }

        $ingredients = Ingredient::where('name_ru', 'ilike', "%{$query}%")
            ->orderBy('name_ru')
            ->limit(8)
            ->get();

        if ($ingredients->isEmpty()) {
            $bot->sendMessage("😔 Ничего не найдено по «{$query}». Попробуйте ещё раз:");
            $this->next('searchIngredient');

            return;
        }

        $keyboard = InlineKeyboardMarkup::make();
        foreach ($ingredients as $ingredient) {
            $keyboard->addRow(
                InlineKeyboardButton::make(
                    $ingredient->name_ru,
                    callback_data: "inventory:select:{$ingredient->id}",
                ),
            );
        }

        $bot->sendMessage('Выберите ингредиент:', reply_markup: $keyboard);
        $this->next('askQuantity');
    }

    public function askQuantity(Nutgram $bot): void
    {
        $callbackData = $bot->callbackQuery()?->data ?? '';

        if (! str_starts_with($callbackData, 'inventory:select:')) {
            $bot->sendMessage('Пожалуйста, выберите ингредиент из списка выше.');
            $this->next('askQuantity');

            return;
        }

        $this->ingredientId = substr($callbackData, strlen('inventory:select:'));

        $bot->answerCallbackQuery();

        $keyboard = InlineKeyboardMarkup::make()->addRow(
            InlineKeyboardButton::make('Пропустить', callback_data: 'inventory:skip'),
        );

        $bot->sendMessage(
            "Введите количество (например: 500 мл, 2 бутылки)\nили нажмите Пропустить:",
            reply_markup: $keyboard,
        );

        $this->next('save');
    }

    public function save(Nutgram $bot): void
    {
        $quantity = null;
        $unit = null;

        $callbackData = $bot->callbackQuery()?->data;
        $text = $bot->message()?->text;

        if ($callbackData !== 'inventory:skip' && $text !== null && $text !== '/skip') {
            [$quantity, $unit] = $this->parseQuantity($text);
        }

        if ($bot->callbackQuery()) {
            $bot->answerCallbackQuery();
        }

        app(AddInventoryAction::class)->fromTelegram($bot, $this->ingredientId, $quantity, $unit);

        $this->end();
    }

    private function parseQuantity(string $input): array
    {
        $input = trim($input);

        if (preg_match('/^(\d+(?:[.,]\d+)?)\s*(.*)$/', $input, $matches)) {
            $quantity = (float) str_replace(',', '.', $matches[1]);
            $unit = trim($matches[2]) ?: null;

            return [$quantity, $unit];
        }

        return [null, null];
    }
}
