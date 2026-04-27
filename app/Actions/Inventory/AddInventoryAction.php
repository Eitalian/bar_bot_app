<?php

namespace App\Actions\Inventory;

use App\Data\Inventory\AddInventoryData;
use App\Handlers\Inventory\AddInventoryHandler;
use App\Handlers\Inventory\ListInventoryHandler;
use App\Models\User;
use App\Services\TelegramUserResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use SergiX44\Nutgram\Nutgram;

class AddInventoryAction
{
    public function __construct(
        private AddInventoryHandler $handler,
        private ListInventoryHandler $listHandler,
        private TelegramUserResolver $resolver,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $user = User::where('telegram_id', $request->integer('telegram_id'))->firstOrFail();

        $data = new AddInventoryData(
            user_id: $user->id,
            ingredient_id: $request->string('ingredient_id')->toString(),
            quantity: $request->filled('quantity') ? (float) $request->input('quantity') : null,
            unit: $request->filled('unit') ? $request->string('unit')->toString() : null,
        );

        $item = $this->handler->handle($data);

        return response()->json($item->load('ingredient'), 201);
    }

    public function fromTelegram(Nutgram $bot, string $ingredientId, ?float $quantity, ?string $unit): void
    {
        $user = $this->resolver->resolve($bot);

        $data = new AddInventoryData(
            user_id: $user->id,
            ingredient_id: $ingredientId,
            quantity: $quantity,
            unit: $unit,
        );

        $this->handler->handle($data);

        $items = $this->listHandler->handle($user->id);
        [$text, $keyboard] = InventoryAction::buildMessage($items);

        $bot->sendMessage(text: $text, parse_mode: 'Markdown', reply_markup: $keyboard);
    }
}
