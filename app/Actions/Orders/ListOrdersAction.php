<?php

namespace App\Actions\Orders;

use App\Handlers\Orders\ListGuestOrdersHandler;
use App\Models\BarSession;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

final class ListOrdersAction
{
    public function __construct(private readonly ListGuestOrdersHandler $handler) {}

    // HTTP GET /api/sessions/{id}/orders
    public function __invoke(int $id): JsonResponse
    {
        $session = BarSession::findOrFail($id);
        $orders  = Order::with('user', 'recipe')
            ->where('session_id', $session->id)
            ->orderBy('created_at')
            ->get();

        return response()->json($orders);
    }

    // Telegram: orders:my
    public function fromTelegram(Nutgram $bot): void
    {
        $orders = $this->handler->handle($bot->userId());

        if ($orders->isEmpty()) {
            $bot->sendMessage(
                text:         'Ты ещё ничего не заказывал 🍹',
                reply_markup: InlineKeyboardMarkup::make()
                    ->addRow(InlineKeyboardButton::make('🏠 Главное меню', callback_data: 'browse:back')),
            );
            return;
        }

        $lines = $orders->map(function ($order) {
            $icon = match ($order->status->value) {
                'accepted'  => "✅ ×{$order->quantity}",
                'cancelled' => '❌',
                default     => '⏳',
            };
            return "• {$order->recipe->name_ru} — {$icon}";
        })->join("\n");

        $bot->sendMessage(
            text:         "📋 *Твои заказы за вечер*\n\n{$lines}",
            parse_mode:   'Markdown',
            reply_markup: InlineKeyboardMarkup::make()
                ->addRow(InlineKeyboardButton::make('🏠 Главное меню', callback_data: 'browse:back')),
        );
    }
}
