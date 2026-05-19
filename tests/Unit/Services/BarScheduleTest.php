<?php

use App\Models\Bar;
use App\Services\BarSchedule;
use Carbon\CarbonImmutable;

beforeEach(function () {
    $this->bar = new Bar(
        id: 1,
        name: 'Test',
        workStart: '12:00',
        workEnd: '06:00',
        openCutoffMinutes: 30,
    );
    $this->schedule = new BarSchedule($this->bar);
});

afterEach(fn() => CarbonImmutable::setTestNow());

it('currentWindow: inside daytime working hours', function () {
    CarbonImmutable::setTestNow('2026-05-10 18:00:00');
    $w = $this->schedule->currentWindow();

    expect($w['start']->toIso8601String())->toBe('2026-05-10T12:00:00+00:00')
        ->and($w['end']->toIso8601String())->toBe('2026-05-11T06:00:00+00:00');
});

it('currentWindow: after midnight, still in yesterday window', function () {
    CarbonImmutable::setTestNow('2026-05-11 03:00:00');
    $w = $this->schedule->currentWindow();

    expect($w['start']->toIso8601String())->toBe('2026-05-10T12:00:00+00:00')
        ->and($w['end']->toIso8601String())->toBe('2026-05-11T06:00:00+00:00');
});

it('currentWindow: bar closed (between 06:00 and 12:00)', function () {
    CarbonImmutable::setTestNow('2026-05-10 09:00:00');
    expect($this->schedule->currentWindow())->toBeNull();
});

it('currentWindow: at exact start (12:00) — open', function () {
    CarbonImmutable::setTestNow('2026-05-10 12:00:00');
    expect($this->schedule->currentWindow())->not->toBeNull();
});

it('currentWindow: at exact end (06:00) — closed', function () {
    CarbonImmutable::setTestNow('2026-05-10 06:00:00');
    expect($this->schedule->currentWindow())->toBeNull();
});

it('canOpenAt: true inside main window', function () {
    CarbonImmutable::setTestNow('2026-05-10 20:00:00');
    expect($this->schedule->canOpenAt(now()))->toBeTrue();
});

it('canOpenAt: false during cutoff (last 30 min)', function () {
    CarbonImmutable::setTestNow('2026-05-11 05:30:00');
    expect($this->schedule->canOpenAt(now()))->toBeFalse();
});

it('canOpenAt: false at 05:29:59 — true (still before cutoff)', function () {
    CarbonImmutable::setTestNow('2026-05-11 05:29:59');
    expect($this->schedule->canOpenAt(now()))->toBeTrue();
});

it('canOpenAt: false when bar closed', function () {
    CarbonImmutable::setTestNow('2026-05-10 09:00:00');
    expect($this->schedule->canOpenAt(now()))->toBeFalse();
});

it('windowFor: respects start time of the session', function () {
    $startedAt = CarbonImmutable::parse('2026-05-10 23:00:00');
    $w = $this->schedule->windowFor($startedAt);

    expect($w['start']->toIso8601String())->toBe('2026-05-10T12:00:00+00:00')
        ->and($w['end']->toIso8601String())->toBe('2026-05-11T06:00:00+00:00');
});

it('isInWindow: true if now in same window as startedAt', function () {
    $startedAt = CarbonImmutable::parse('2026-05-10 18:30:00');
    $now = CarbonImmutable::parse('2026-05-11 02:00:00');

    expect($this->schedule->isInWindow($startedAt, $now))->toBeTrue();
});

it('isInWindow: false if now outside window of startedAt', function () {
    $startedAt = CarbonImmutable::parse('2026-05-10 18:30:00');
    $now = CarbonImmutable::parse('2026-05-11 11:00:00');

    expect($this->schedule->isInWindow($startedAt, $now))->toBeFalse();
});

it('expectedEndAt: returns end of window for startedAt', function () {
    $startedAt = CarbonImmutable::parse('2026-05-10 13:30:00');

    expect($this->schedule->expectedEndAt($startedAt)->toIso8601String())
        ->toBe('2026-05-11T06:00:00+00:00');
});
