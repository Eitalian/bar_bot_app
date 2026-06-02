<?php

namespace App\Actions\Ratings;

use App\Actions\Search\GetRecipeAction;
use App\Handlers\Ratings\RateRecipeHandler;
use App\Models\Rating;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use SergiX44\Nutgram\Nutgram;

final class RateAction
{
    public function __construct(
        private RateRecipeHandler $handler,
    ) {}

    public function __invoke(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'score' => 'required|integer|between:1,5',
        ]);

        $userId = Auth::id();
        $rating = $this->handler->handle($userId, $id, $request->integer('score'));

        $stats = Rating::where('recipe_id', $id)
            ->selectRaw('ROUND(AVG(score), 1) as avg, COUNT(*) as count')
            ->first();

        return response()->json([
            'score' => $rating->score,
            'avg'   => $stats?->avg,
            'count' => $stats?->count ?? 0,
        ]);
    }

    public function fromTelegram(Nutgram $bot, string $id, int $score): void
    {
        $user = User::where('telegram_id', $bot->userId())->firstOrFail();
        $this->handler->handle($user->id, $id, $score);
        app(GetRecipeAction::class)->fromTelegram($bot, $id);
    }
}
