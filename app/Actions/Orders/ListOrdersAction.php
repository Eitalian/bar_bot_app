<?php

namespace App\Actions\Orders;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Handlers\Orders\ListGuestOrdersHandler;
use App\Handlers\Orders\ListSessionOrdersHandler;
use App\Handlers\Session\GetActiveSessionHandler;
use App\Models\BarSession;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

final class ListOrdersAction
{
    public function __construct(
        private readonly ListGuestOrdersHandler $guestHandler,
        private readonly ListSessionOrdersHandler $sessionHandler,
        private readonly GetActiveSessionHandler $activeSession,
    ) {}

    // HTTP GET /api/sessions/{id}/orders
    public function __invoke(int $id): JsonResponse
    {
        /** @var User $authUser */
        $authUser = auth()->user();
        $session  = BarSession::findOrFail($id);

        $orders = $authUser->role === UserRole::Guest
            ? $this->guestHandler->handle($authUser->id)
            : $this->sessionHandler->handle($session->id);

        return response()->json($orders);
    }

    // Telegram: orders:my
    public function fromTelegram(Nutgram $bot): void
    {
        /** @var User $authUser */
        $authUser = User::where('telegram_id', $bot->userId())->firstOrFail();

        if ($authUser->role === UserRole::Guest) {
            $orders = $this->guestHandler->handle($authUser->id);
        } else {
            $session = $this->activeSession->handle();
            $orders  = $session ? $this->sessionHandler->handle($session->id) : collect();
        }

        if ($orders->isEmpty()) {
            $bot->sendMessage(
                text:         'Заказов пока нет 🍹',
                reply_markup: InlineKeyboardMarkup::make()
                    ->addRow(InlineKeyboardButton::make('🏠 Главное меню', callback_data: 'browse:back')),
            );
            return;
        }

        $lines = $orders->map(function ($order) use ($authUser) {
            $icon = match ($order->status) {
                OrderStatus::Accepted  => "✅ ×{$order->quantity}",
                OrderStatus::Cancelled => '❌',
                default                => '⏳',
            };

            $line = "• {$order->recipe->name_ru} — {$icon}";

            if ($authUser->role !== UserRole::Guest) {
                $line .= " ({$order->user->first_name})";
            }

            return $line;
        })->join("\n");

        $title = $authUser->role === UserRole::Guest ? '📋 *Твои заказы за вечер*' : '📋 *Заказы сессии*';

        $bot->sendMessage(
            text:         "{$title}\n\n{$lines}",
            parse_mode:   'Markdown',
            reply_markup: InlineKeyboardMarkup::make()
                ->addRow(InlineKeyboardButton::make('🏠 Главное меню', callback_data: 'browse:back')),
        );
    }
}
