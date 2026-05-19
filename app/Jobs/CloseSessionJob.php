<?php

namespace App\Jobs;

use Carbon\CarbonInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

final class CloseSessionJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /** Source of truth для tries — здесь, не в worker --tries. */
    public int $tries = 3;

    public function __construct(
        public readonly int $sessionId,
        public readonly CarbonInterface $endAt,
    ) {}

    public function handle(): void
    {
        // Atomic: WHERE ended_at IS NULL делает закрытие идемпотентным.
        DB::table('bar_sessions')
            ->where('id', $this->sessionId)
            ->whereNull('ended_at')
            ->update(['ended_at' => $this->endAt]);
    }
}
