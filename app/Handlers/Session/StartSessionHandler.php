<?php

namespace App\Handlers\Session;

use App\Data\Session\StartSessionData;
use App\Exceptions\BarClosedException;
use App\Jobs\CloseSessionJob;
use App\Models\Bar;
use App\Models\BarSession;
use App\Services\BarSchedule;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;

final class StartSessionHandler
{
    public function __construct(
        private readonly Bar $bar,
        private readonly BarSchedule $schedule,
    ) {}

    public function handle(StartSessionData $data): BarSession
    {
        $now = CarbonImmutable::now();

        if (! $this->schedule->canOpenAt($now)) {
            throw new BarClosedException();
        }

        $active = BarSession::query()
            ->where('bar_id', $this->bar->id)
            ->whereNull('ended_at')
            ->first();

        if ($active && $this->schedule->isInWindow($active->started_at, $now)) {
            return $active; // идемпотентность
        }

        if ($active) {
            // Self-healing: закрыть протухшую синхронно через handle(),
            // минуя диспетчер (Queue::fake() перехватил бы dispatchSync).
            $expectedEnd = $this->schedule->expectedEndAt($active->started_at);
            (new CloseSessionJob($active->id, $expectedEnd))->handle();
        }

        try {
            $session = BarSession::create([
                'bar_id'     => $this->bar->id,
                'started_at' => $now,
                'ended_at'   => null,
            ]);
        } catch (QueryException $e) {
            // Гонка: конкурент создал активную сессию между SELECT и INSERT;
            // partial unique index uq_bar_sessions_active отклонил вставку.
            // Возвращаем сессию-победителя (победитель уже поставил свою CloseSessionJob).
            $winner = BarSession::query()
                ->where('bar_id', $this->bar->id)
                ->whereNull('ended_at')
                ->first();

            if ($winner && $this->schedule->isInWindow($winner->started_at, $now)) {
                return $winner;
            }

            throw $e;
        }

        $endAt = $this->schedule->expectedEndAt($now);
        CloseSessionJob::dispatch($session->id, $endAt)->delay($endAt);

        return $session;
    }
}
