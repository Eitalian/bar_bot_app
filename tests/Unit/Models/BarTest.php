<?php

use App\Models\Bar;
use Illuminate\Support\Facades\DB;

it('reads attributes from the bars table', function () {
    DB::table('bars')->update([
        'name'                => 'TestBar',
        'work_start'          => '15:00',
        'work_end'            => '03:00',
        'open_cutoff_minutes' => 45,
    ]);

    $bar = Bar::default();

    expect($bar->name)->toBe('TestBar')
        ->and($bar->workStart)->toBe('15:00')
        ->and($bar->workEnd)->toBe('03:00')
        ->and($bar->openCutoffMinutes)->toBe(45)
        ->and($bar->id)->toBeInt();
});
