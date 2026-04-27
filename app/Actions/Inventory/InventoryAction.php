<?php

namespace App\Actions\Inventory;

use App\Handlers\Inventory\ListInventoryHandler;
use App\Models\User;
use App\Services\TelegramUserResolver;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

class InventoryAction
{
    public function __construct(
        private ListInventoryHandler $handler,
        private TelegramUserResolver $resolver,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $user = User::where('telegram_id', $request->integer('telegram_id'))->firstOrFail();
        $items = $this->handler->handle($user->id);

        return response()->json($items->load('ingredient'));
    }

    public function fromTelegram(Nutgram $bot): void
    {
        $user = $this->resolver->resolve($bot);
        $items = $this->handler->handle($user->id);

        [$text, $keyboard] = $this->buildMessage($items);

        $bot->sendMessage(text: $text, parse_mode: 'Markdown', reply_markup: $keyboard);
    }

    public static function buildMessage(Collection $items): array
    {
        $keyboard = InlineKeyboardMarkup::make();

        if ($items->isEmpty()) {
            $text = '📦 *Инвентарь пуст.*';
        } else {
            $text = "📦 *Инвентарь бара* — {$items->count()} " . self::pluralize($items->count()) . ':';

            foreach ($items as $item) {
                $label = $item->ingredient->name_ru ?? '—';
                if ($item->quantity !== null) {
                    $label .= ' — ' . rtrim(rtrim(number_format((float) $item->quantity, 2, '.', ''), '0'), '.');
                    if ($item->unit) {
                        $label .= ' ' . $item->unit;
                    }
                }

                $keyboard->addRow(
                    InlineKeyboardButton::make($label, callback_data: 'noop'),
                    InlineKeyboardButton::make('❌', callback_data: "inventory:remove:{$item->id}"),
                );
            }
        }

        $keyboard->addRow(
            InlineKeyboardButton::make('➕ Добавить ингредиент', callback_data: 'inventory:add'),
        );

        return [$text, $keyboard];
    }

    private static function pluralize(int $count): string
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
