<?php

namespace App\Models;

use Illuminate\Support\Facades\DB;
use RuntimeException;

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
        $row = DB::table('bars')->orderBy('id')->first();

        if ($row === null) {
            throw new RuntimeException('No bar configured in the bars table.');
        }

        return new self(
            id: (int) $row->id,
            name: (string) $row->name,
            // TIME приходит как 'HH:MM:SS' — нормализуем к 'HH:MM' (контракт value-object и BarSchedule).
            workStart: substr((string) $row->work_start, 0, 5),
            workEnd: substr((string) $row->work_end, 0, 5),
            openCutoffMinutes: (int) $row->open_cutoff_minutes,
        );
    }
}
