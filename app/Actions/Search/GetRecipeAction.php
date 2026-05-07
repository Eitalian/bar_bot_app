<?php

namespace App\Actions\Search;

use App\Handlers\Search\GetRecipeHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class GetRecipeAction
{
    public function __construct(private GetRecipeHandler $handler) {}

    public function __invoke(Request $request, string $id): JsonResponse
    {
        $recipe = $this->handler->handle($id);

        if (! $recipe) {
            return response()->json(['message' => 'Рецепт не найден'], 404);
        }

        return response()->json($recipe);
    }
}
