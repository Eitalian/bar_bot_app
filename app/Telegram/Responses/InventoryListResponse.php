<?php

namespace App\Telegram\Responses;

use App\Models\Inventory;
use Illuminate\Database\Eloquent\Collection;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

final class InventoryListResponse
{
    /** @param Collection<int, Inventory> $items */
    public function __construct(private Collection $items) {}

    public function send(Nutgram $bot): void
    {
        $bot->sendMessage(
            text: $this->text(),
            parse_mode: 'Markdown',
            reply_markup: $this->keyboard(),
        );
    }

    private function text(): string
    {
        if ($this->items->isEmpty()) {
            return '📦 *Инвентарь пуст.*';
        }

        $count = $this->items->count();

        return "📦 *Инвентарь бара* — {$count} {$this->pluralize($count)}:";
    }

    private function keyboard(): InlineKeyboardMarkup
    {
        $keyboard = InlineKeyboardMarkup::make();

        foreach ($this->items as $item) {
            $label = $item->ingredient->name_ru ?? '—';

            if ($item->quantity !== null) {
                $qty = rtrim(rtrim(number_format((float) $item->quantity, 2, '.', ''), '0'), '.');
                $label .= " — {$qty}";
                if ($item->unit) {
                    $label .= " {$item->unit}";
                }
            }

            $keyboard->addRow(
                InlineKeyboardButton::make($label, callback_data: 'noop'),
                InlineKeyboardButton::make('❌', callback_data: "inventory:remove:{$item->id}"),
            );
        }

        $keyboard->addRow(
            InlineKeyboardButton::make('➕ Добавить ингредиент', callback_data: 'inventory:add'),
        );

        return $keyboard;
    }

    private function pluralize(int $count): string
    {
        $mod10 = $count % 10;
        $mod100 = $count % 100;

        if ($mod100 >= 11 && $mod100 <= 19) {
            return 'позиций';
        }

        return match ($mod10) {
            1 => 'позиция',
            2, 3, 4 => 'позиции',
            default => 'позиций',
        };
    }
}
