<?php

use App\Data\Session\StartSessionData;
use App\Exceptions\BarClosedException;
use App\Handlers\Session\StartSessionHandler;
use App\Jobs\CloseSessionJob;
use App\Models\BarSession;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

afterEach(function () {
    CarbonImmutable::setTestNow();
    BarSession::flushEventListeners(); // снять временные creating-листенеры из теста гонки
});

it('throws when bar is closed', function () {
    CarbonImmutable::setTestNow('2026-05-10 09:00:00'); // бар закрыт
    $handler = app(StartSessionHandler::class);

    expect(fn() => $handler->handle(new StartSessionData()))
        ->toThrow(BarClosedException::class);
});

it('throws when bar is in cutoff zone', function () {
    CarbonImmutable::setTestNow('2026-05-11 05:45:00'); // 15 минут до закрытия
    $handler = app(StartSessionHandler::class);

    expect(fn() => $handler->handle(new StartSessionData()))
        ->toThrow(BarClosedException::class);
});

it('returns existing active session if still in window', function () {
    Queue::fake();
    CarbonImmutable::setTestNow('2026-05-10 18:00:00');
    $existing = BarSession::factory()->create(['started_at' => now()]);

    CarbonImmutable::setTestNow('2026-05-10 22:00:00'); // позже, но то же окно
    $handler = app(StartSessionHandler::class);
    $result = $handler->handle(new StartSessionData());

    expect($result->id)->toBe($existing->id);
    Queue::assertNotPushed(CloseSessionJob::class); // новую джобу не диспатчим
});

it('self-heals stale session and creates new', function () {
    Queue::fake();
    // Просроченная сессия со вчерашних 18:00
    CarbonImmutable::setTestNow('2026-05-09 18:00:00');
    $stale = BarSession::factory()->create(['started_at' => now()]);

    // Сейчас 13:00 следующего дня — вчерашнее окно давно закрылось
    CarbonImmutable::setTestNow('2026-05-10 13:00:00');
    $handler = app(StartSessionHandler::class);
    $result = $handler->handle(new StartSessionData());

    // Старая сессия закрыта синхронным вызовом handle() — минуя диспетчер
    expect($stale->fresh()->ended_at)->not->toBeNull();

    // Создана новая активная сессия
    expect(BarSession::count())->toBe(2)
        ->and($result->ended_at)->toBeNull()
        ->and($result->started_at->toIso8601String())->toBe('2026-05-10T13:00:00+00:00');

    // Delayed CloseSessionJob для новой сессии
    Queue::assertPushed(CloseSessionJob::class);
});

it('returns the winning session when a concurrent open races the insert', function () {
    Queue::fake();
    CarbonImmutable::setTestNow('2026-05-10 18:00:00');

    // Симулируем гонку: между SELECT (активной нет) и нашим INSERT
    // конкурент успел создать активную сессию. Вставляем строку-победителя
    // напрямую (минуя модельные события) и бросаем QueryException до того,
    // как выполнится INSERT хендлера — транзакция RefreshDatabase остаётся чистой.
    BarSession::creating(function () {
        $winnerId = DB::table('bar_sessions')->insertGetId([
            'bar_id'     => 1,
            'started_at' => now(),
            'ended_at'   => null,
        ]);

        throw new QueryException(
            'pgsql',
            'insert into "bar_sessions" ...',
            [],
            new RuntimeException("duplicate key value violates unique constraint \"uq_bar_sessions_active\" (winner id={$winnerId})"),
        );
    });

    $handler = app(StartSessionHandler::class);
    $result = $handler->handle(new StartSessionData());

    // Вернулась сессия-победитель, новая не создана.
    expect(BarSession::count())->toBe(1)
        ->and($result->ended_at)->toBeNull();

    // Дубликат CloseSessionJob не диспатчим — победитель уже поставил свою джобу.
    Queue::assertNotPushed(CloseSessionJob::class);
});

it('creates new session when none exists', function () {
    Queue::fake();
    CarbonImmutable::setTestNow('2026-05-10 18:00:00');
    $handler = app(StartSessionHandler::class);

    $result = $handler->handle(new StartSessionData());

    expect($result)->not->toBeNull()
        ->and($result->ended_at)->toBeNull()
        ->and($result->bar_id)->toBe(1);

    Queue::assertPushed(
        CloseSessionJob::class,
        fn(CloseSessionJob $job)
            => $job->sessionId === $result->id
            && $job->endAt->toIso8601String() === '2026-05-11T06:00:00+00:00',
    );
});
