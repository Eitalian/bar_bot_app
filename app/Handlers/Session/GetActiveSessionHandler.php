<?php

namespace App\Handlers\Session;

use App\Models\Bar;
use App\Models\BarSession;
use App\Services\BarSchedule;
use Carbon\CarbonImmutable;

final class GetActiveSessionHandler
{
    public function __construct(
        private readonly Bar $bar,
        private readonly BarSchedule $schedule,
    ) {}

    public function handle(): ?BarSession
    {
        $session = BarSession::query()
            ->where('bar_id', $this->bar->id)
            ->whereNull('ended_at')
            ->first();

        if ($session === null) {
            return null;
        }

        return $this->schedule->isInWindow($session->started_at, CarbonImmutable::now())
            ? $session
            : null;
    }
}
