<?php

namespace App\Services;

use App\Models\Bar;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

final class BarSchedule
{
    public function __construct(private readonly Bar $bar) {}

    /**
     * Возвращает [start, end) текущего рабочего окна или null, если сейчас бар закрыт.
     * Окно может проходить через полночь — start от вчерашней даты.
     */
    public function currentWindow(CarbonInterface $now = new CarbonImmutable()): ?array
    {
        // Кандидат-окно, начинающееся сегодня.
        $startToday = $this->buildBoundary($now->toDateString(), $this->bar->workStart);
        $endFromToday = $this->endAfter($startToday);

        if ($now->greaterThanOrEqualTo($startToday) && $now->lessThan($endFromToday)) {
            return ['start' => $startToday, 'end' => $endFromToday];
        }

        // Кандидат-окно, начавшееся вчера.
        $startYesterday = $startToday->subDay();
        $endFromYesterday = $this->endAfter($startYesterday);

        if ($now->greaterThanOrEqualTo($startYesterday) && $now->lessThan($endFromYesterday)) {
            return ['start' => $startYesterday, 'end' => $endFromYesterday];
        }

        return null;
    }

    /**
     * Окно [start, end), в котором стартовала сессия.
     * Предполагается, что startedAt валиден (handler гарантирует canOpenAt).
     */
    public function windowFor(CarbonInterface $startedAt): array
    {
        // Окно начинается в ближайшее прошедшее workStart относительно startedAt.
        $startToday = $this->buildBoundary($startedAt->toDateString(), $this->bar->workStart);

        if ($startedAt->greaterThanOrEqualTo($startToday)) {
            $start = $startToday;
        } else {
            $start = $startToday->subDay();
        }

        return ['start' => $start, 'end' => $this->endAfter($start)];
    }

    public function isInWindow(CarbonInterface $startedAt, CarbonInterface $now = new CarbonImmutable()): bool
    {
        $window = $this->windowFor($startedAt);

        return $now->greaterThanOrEqualTo($window['start']) && $now->lessThan($window['end']);
    }

    public function canOpenAt(CarbonInterface $now = new CarbonImmutable()): bool
    {
        $window = $this->currentWindow($now);

        if ($window === null) {
            return false;
        }

        return $now->lessThan($window['end']->subMinutes($this->bar->openCutoffMinutes));
    }

    public function expectedEndAt(CarbonInterface $startedAt): CarbonImmutable
    {
        return $this->windowFor($startedAt)['end'];
    }

    private function buildBoundary(string $date, string $time): CarbonImmutable
    {
        return CarbonImmutable::parse("$date $time:00");
    }

    /**
     * Прибавляет (через полночь, если нужно) рабочую длительность к началу окна.
     */
    private function endAfter(CarbonImmutable $start): CarbonImmutable
    {
        [$endHour, $endMinute] = explode(':', $this->bar->workEnd);
        $endSameDay = $start->copy()->setTime((int) $endHour, (int) $endMinute);

        // Если конец строго раньше начала — окно через полночь, +1 день.
        return $endSameDay->lessThanOrEqualTo($start) ? $endSameDay->addDay() : $endSameDay;
    }
}
