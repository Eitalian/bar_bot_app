<?php

namespace App\Actions\Inventory;

use App\Data\Inventory\RemoveInventoryData;
use App\Handlers\Inventory\ListInventoryHandler;
use App\Telegram\Responses\InventoryListResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;
use SergiX44\Nutgram\Nutgram;

final class RemoveInventoryAction
{
    public function __construct(private ListInventoryHandler $listHandler) {}

    public function __invoke(Request $request, int $id): JsonResponse
    {
        $deleted = Bus::dispatch(new RemoveInventoryData(inventoryId: $id));

        return $deleted
            ? response()->json(null, 204)
            : response()->json(['error' => 'Not found'], 404);
    }

    public function fromTelegram(Nutgram $bot, int $id): void
    {
        Bus::dispatch(new RemoveInventoryData(inventoryId: $id));

        $bot->answerCallbackQuery();
        $bot->sendMessage('✅ Удалено из инвентаря');

        (new InventoryListResponse($this->listHandler->handle()))->send($bot);
    }
}
