<?php

namespace App\Actions\Favorites;

use App\Actions\Search\GetRecipeAction;
use App\Handlers\Favorites\FavoriteToggleHandler;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use SergiX44\Nutgram\Nutgram;

final class FavoriteToggleAction
{
    public function __construct(private FavoriteToggleHandler $handler) {}

    public function __invoke(Request $request, string $id): JsonResponse
    {
        $userId = Auth::id();
        $isFavorite = $this->handler->handle($userId, $id);

        return response()->json(['favorited' => $isFavorite]);
    }

    public function fromTelegram(Nutgram $bot, string $id): void
    {
        $user = User::where('telegram_id', $bot->userId())->firstOrFail();
        $this->handler->handle($user->id, $id);

        app(GetRecipeAction::class)->fromTelegram($bot, $id);
    }
}
