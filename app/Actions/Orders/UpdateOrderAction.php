<?php

namespace App\Actions\Orders;

use App\Data\Orders\AcceptOrderData;
use App\Data\Orders\CancelOrderData;
use App\Exceptions\OrderAlreadyProcessedException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;

final class UpdateOrderAction
{
    public function __invoke(Request $request, int $id): JsonResponse
    {
        $status = $request->input('status');

        try {
            $order = match ($status) {
                'accepted'  => Bus::dispatch(new AcceptOrderData(
                    orderId:  $id,
                    quantity: (int) $request->input('quantity', 1),
                )),
                'cancelled' => Bus::dispatch(new CancelOrderData(orderId: $id)),
                default     => abort(422, "status must be 'accepted' or 'cancelled'"),
            };
        } catch (OrderAlreadyProcessedException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }

        return response()->json($order);
    }
}
