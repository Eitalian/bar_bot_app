<?php

namespace App\Actions\Favorites;

use App\Handlers\Favorites\ListFavoritesHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

final class ListFavoritesAction
{
    public function __construct(private readonly ListFavoritesHandler $handler) {}

    public function __invoke(Request $request): JsonResponse
    {
        $userId = Auth::id();
        $favorites = $this->handler->handle($userId);

        return response()->json($favorites->values());
    }
}
