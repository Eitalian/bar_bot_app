<?php

namespace App\Actions\Inventory;

use App\Data\Inventory\RemoveInventoryData;
use App\Handlers\Inventory\ListInventoryHandler;
use App\Handlers\Inventory\RemoveInventoryHandler;
use App\Services\TelegramUserResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use SergiX44\Nutgram\Nutgram;

class RemoveInventoryAction
{
    public function __construct(
        private RemoveInventoryHandler $handler,
        private ListInventoryHandler $listHandler,
        private TelegramUserResolver $resolver,
    ) {}

    public function __invoke(Request $request, int $id): JsonResponse
    {
        $user = \App\Models\User::where('telegram_id', $request->integer('telegram_id'))->firstOrFail();

        $data = new RemoveInventoryData(user_id: $user->id, inventory_id: $id);
        $deleted = $this->handler->handle($data);

        return $deleted
            ? response()->json(null, 204)
            : response()->json(['error' => 'Not found'], 404);
    }

    public function fromTelegram(Nutgram $bot, int $id): void
    {
        $user = $this->resolver->resolve($bot);

        $data = new RemoveInventoryData(user_id: $user->id, inventory_id: $id);
        $this->handler->handle($data);

        $bot->answerCallbackQuery();

        $items = $this->listHandler->handle($user->id);
        [$text, $keyboard] = InventoryAction::buildMessage($items);

        $bot->editMessageText(text: $text, parse_mode: 'Markdown', reply_markup: $keyboard);
    }
}
