<?php

use App\Models\Bar;

it('reads attributes from config', function () {
    config(['bar.id' => 7]);
    config(['bar.name' => 'TestBar']);
    config(['bar.working_hours.start' => '15:00']);
    config(['bar.working_hours.end' => '03:00']);
    config(['bar.open_cutoff_minutes' => 45]);

    $bar = Bar::default();

    expect($bar->id)->toBe(7)
        ->and($bar->name)->toBe('TestBar')
        ->and($bar->workStart)->toBe('15:00')
        ->and($bar->workEnd)->toBe('03:00')
        ->and($bar->openCutoffMinutes)->toBe(45);
});
