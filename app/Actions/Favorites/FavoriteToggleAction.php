<?php

namespace App\Actions\Favorites;

use App\Actions\Search\GetRecipeAction;
use App\Handlers\Favorites\FavoriteToggleHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use SergiX44\Nutgram\Nutgram;

final class FavoriteToggleAction
{
    public function __construct(private FavoriteToggleHandler $handler) {}

    public function __invoke(Request $request, string $id): JsonResponse
    {
        $this->handler->handle(Auth::id(), $id);

        return response()->json(['success' => true]);
    }

    public function fromTelegram(Nutgram $bot, string $id): void
    {
        $this->handler->handle(Auth::id(), $id);

        app(GetRecipeAction::class)->fromTelegram($bot, $id);
    }
}
