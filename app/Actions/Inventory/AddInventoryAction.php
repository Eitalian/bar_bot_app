<?php

namespace App\Actions\Inventory;

use App\Data\Inventory\AddInventoryData;
use App\Handlers\Inventory\ListInventoryHandler;
use App\Models\Inventory;
use App\Telegram\Responses\InventoryListResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;
use SergiX44\Nutgram\Nutgram;

final class AddInventoryAction
{
    public function __construct(private ListInventoryHandler $listHandler) {}

    public function __invoke(Request $request): JsonResponse
    {
        $data = new AddInventoryData(
            ingredientId: $request->string('ingredient_id')->toString(),
            quantity: $request->filled('quantity') ? (float) $request->input('quantity') : null,
            unit: $request->filled('unit') ? $request->string('unit')->toString() : null,
        );

        /** @var Inventory $item */
        $item = Bus::dispatch($data);

        return response()->json($item->load('ingredient'), 201);
    }

    public function fromTelegram(Nutgram $bot, string $ingredientId, ?float $quantity, ?string $unit): void
    {
        $data = new AddInventoryData(
            ingredientId: $ingredientId,
            quantity: $quantity,
            unit: $unit,
        );

        /** @var Inventory $item */
        $item = Bus::dispatch($data);

        $bot->sendMessage("✅ Добавлено: {$item->ingredient->name_ru}");

        $response = new InventoryListResponse($this->listHandler->handle());

        $bot->sendMessage(
            text: $response->text(),
            parse_mode: 'Markdown',
            reply_markup: $response->keyboard(),
        );
    }
}
