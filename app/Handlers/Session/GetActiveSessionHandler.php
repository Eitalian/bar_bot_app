<?php

namespace App\Handlers\Session;

use App\Models\Bar;
use App\Models\BarSession;
use App\Services\BarSchedule;

final class GetActiveSessionHandler
{
    public function __construct(
        private readonly Bar $bar,
        private readonly BarSchedule $schedule,
    ) {}

    public function handle(): ?BarSession
    {
        $session = BarSession::findOpen($this->bar->id);

        return $session && $this->schedule->isInWindow($session->started_at)
            ? $session
            : null;
    }
}
