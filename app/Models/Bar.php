<?php

namespace App\Models;

final class Bar
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly string $workStart,
        public readonly string $workEnd,
        public readonly int $openCutoffMinutes,
    ) {}

    public static function default(): self
    {
        return new self(
            id: (int) config('bar.id'),
            name: (string) config('bar.name'),
            workStart: (string) config('bar.working_hours.start'),
            workEnd: (string) config('bar.working_hours.end'),
            openCutoffMinutes: (int) config('bar.open_cutoff_minutes'),
        );
    }
}
