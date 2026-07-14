<?php

namespace App\UI\Http\Actions\Ratings;

use App\Handlers\Ratings\RateRecipeHandler;
use App\UI\Http\Actions\Search\GetRecipeAction;
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

        $rating = $this->handler->handle(Auth::id(), $id, $request->integer('score'));

        return response()->json(['score' => $rating->score]);
    }

    public function fromTelegram(Nutgram $bot, string $id, int $score): void
    {
        $this->handler->handle(Auth::id(), $id, $score);
        app(GetRecipeAction::class)->fromTelegram($bot, $id);
    }
}
