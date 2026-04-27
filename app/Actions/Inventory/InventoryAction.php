<?php

namespace App\Actions\Inventory;

use App\Handlers\Inventory\ListInventoryHandler;
use App\Telegram\Responses\InventoryListResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use SergiX44\Nutgram\Nutgram;

final class InventoryAction
{
    public function __construct(private ListInventoryHandler $handler) {}

    public function __invoke(Request $request): JsonResponse
    {
        $items = $this->handler->handle();

        return response()->json($items->load('ingredient'));
    }

    public function fromTelegram(Nutgram $bot): void
    {
        $items = $this->handler->handle();

        (new InventoryListResponse($items))->send($bot);
    }
}
