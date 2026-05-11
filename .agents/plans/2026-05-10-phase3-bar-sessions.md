# Phase 3: Бар-сессии — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.
>
> **Before starting:** Read `.agents/knowledge/codebase.md` и `.agents/specs/bar-bot-design.md`.
> **After completing:** Update `.agents/knowledge/codebase.md` (статус Phase 3, паттерн Bar/BarSchedule, маршруты сессии, queue worker). Update `.agents/specs/bar-bot-design.md` (Phase 3: только авто-закрытие, маршруты `/api/bars/{id}/session`). Update `.agents/specs/migration-conventions.md` (правило про обоснование размера PK).

**Goal:** Бармен открывает бар-сессию командой `/session` или `POST /api/bars/{id}/session`; сессия автоматически закрывается в конце рабочего окна (12:00–06:00) через delayed job либо self-healing при следующем открытии; одна активная сессия на бар.

**Architecture:**
- `Bar` — POPO (`app/Models/Bar.php`), не Eloquent. Атрибуты из `config/bar.php`. Singleton в DI.
- `BarSchedule` — сервис чистой логики времени (currentWindow / windowFor / isInWindow / canOpenAt / expectedEndAt).
- `StartSessionHandler` — Bus-handler, идемпотентный, с self-healing просроченной сессии через синхронный `CloseSessionJob::dispatchSync`.
- `GetActiveSessionHandler` — query через DI, фильтр `ended_at IS NULL` AND `BarSchedule::isInWindow`.
- `CloseSessionJob` — atomic UPDATE, `tries=3`, идемпотентен (NO-OP если `ended_at` уже не NULL).
- DB-инвариант «одна активная сессия на бар» — partial unique index `WHERE ended_at IS NULL`.
- Action — один файл на фичу, оба транспорта (HTTP `__invoke` + Telegram `fromTelegram`).

**Tech Stack:** новое относительно проекта — Laravel queues с `database` driver, partial unique index PostgreSQL.

**Branch:** `feature/bb7_bar-sessions`

---

## Карта файлов

### Новые файлы

| Файл | Ответственность |
|---|---|
| `database/migrations/2026_05_10_000001_create_bar_sessions_table.php` | DDL `bar_sessions` + partial unique index `uq_bar_sessions_active` |
| `database/migrations/2026_05_10_000002_create_jobs_table.php` | Стандартная Laravel-таблица `jobs` для queue driver `database` (генерируется `php artisan queue:table`) |
| `database/factories/BarSessionFactory.php` | `BarSession::factory()` + state `closed()` |
| `config/bar.php` | `id`, `name`, `working_hours.start/end`, `open_cutoff_minutes` |
| `app/Models/Bar.php` | POPO с readonly props + `Bar::default()` из config |
| `app/Models/BarSession.php` | Eloquent: PK SMALLINT, `$timestamps = false`, casts `started_at/ended_at → immutable_datetime` |
| `app/Services/BarSchedule.php` | currentWindow / windowFor / isInWindow / canOpenAt / expectedEndAt |
| `app/Jobs/CloseSessionJob.php` | Queueable job, atomic UPDATE, `$tries = 3` |
| `app/Exceptions/BarClosedException.php` | Доменное исключение «бар сейчас не работает» |
| `app/Data/Session/StartSessionData.php` | DTO (пустой контракт — старт не требует параметров; нужен только для Bus-маппинга) |
| `app/Handlers/Session/StartSessionHandler.php` | Идемпотентный старт + self-healing |
| `app/Handlers/Session/GetActiveSessionHandler.php` | Возвращает `?BarSession` с проверкой окна |
| `app/Actions/Session/SessionAction.php` | HTTP `GET /api/bars/{id}/session` + Telegram `/session` команда |
| `app/Actions/Session/StartSessionAction.php` | HTTP `POST /api/bars/{id}/session` + Telegram `session:start` callback |
| `tests/Unit/Models/BarTest.php` | `Bar::default()` читает config |
| `tests/Unit/Services/BarScheduleTest.php` | Все ветки времени (внутри окна / cutoff / через полночь / закрыт) |
| `tests/Unit/Jobs/CloseSessionJobTest.php` | Закрывает активную, NO-OP на закрытой |
| `tests/Unit/Handlers/Session/StartSessionHandlerTest.php` | 4 ветки: ошибка / идемпотентность / self-healing / создание |
| `tests/Unit/Handlers/Session/GetActiveSessionHandlerTest.php` | Активная в окне / просроченная (null) / нет активной |
| `tests/Feature/Actions/Session/SessionActionTest.php` | HTTP GET (200 / 204 / 404), Telegram `/session` рендерит правильную клавиатуру |
| `tests/Feature/Actions/Session/StartSessionActionTest.php` | HTTP POST (201 для guest = 403, 201 для bartender), Telegram callback |
| `tests/Feature/PhaseThreeFlowTest.php` | E2E: open → active → travel time → auto-closed by job |

### Изменяемые файлы

| Файл | Изменение |
|---|---|
| `routes/api.php` | Добавить `POST` и `GET` `/bars/{id}/session` (под `auth.telegram` + write под `CanManageMiddleware`) |
| `routes/telegram.php` | Добавить `onCommand('session', SessionAction::fromTelegram)`, в группе `CanManageMiddleware` — `onCallbackQueryData('session:start', StartSessionAction::fromTelegram)` |
| `app/Telegram/Handlers/StartHandler.php` | Добавить кнопку «🍸 Сессия» в главное меню (callback `cmd:session`) и обработать его как редирект на `SessionAction::fromTelegram` |
| `app/Providers/AppServiceProvider.php` | `$this->app->singleton(Bar::class, fn () => Bar::default())` |
| `app/Providers/BusServiceProvider.php` | `StartSessionData::class => StartSessionHandler::class` |
| `docker-compose.yml` | Новый сервис `queue` (тот же образ, что `app`), команда `php artisan queue:work --sleep=3` (без `--tries`, чтобы значение из job не переопределялось) |
| `.env.example` | `QUEUE_CONNECTION=database` |
| `.agents/knowledge/codebase.md` | Phase 3 → ✅ Готово; новые паттерны (Bar POPO, BarSchedule, queued jobs); новые маршруты |
| `.agents/specs/bar-bot-design.md` | Phase 3 переписать под фактический дизайн |
| `.agents/specs/migration-conventions.md` | Добавить правило «размер PK обосновывается в комментарии миграции» |

---

## Порядок исполнения

```
Группа 1 (параллельно): T1, T2, T3
  — T1 (миграция bar_sessions + модель + factory): отдельный namespace Models/Migrations/Factories
  — T2 (queue infra: queue:table + driver + worker): отдельные конфиги, не пересекаются с T1/T3
  — T3 (config/bar.php + Bar POPO + DI): отдельный namespace Models/Bar, не зависит от БД

Группа 2 (параллельно): T4, T5
  — T4 (BarSchedule, depends T3): отдельный неймспейс Services
  — T5 (CloseSessionJob, depends T1): отдельный неймспейс Jobs
  — независимы между собой: разные файлы, разные обязанности

Группа 3 (параллельно): T6, T7
  — T6 (StartSessionHandler+DTO+Exception, depends T4, T5)
  — T7 (GetActiveSessionHandler, depends T1, T4)
  — независимы: разные неймспейсы Handlers/Session/*, общие — только модель и BarSchedule (read-only)

Группа 4 (параллельно): T8, T9
  — T8 (SessionAction, depends T7): один файл app/Actions/Session/SessionAction.php
  — T9 (StartSessionAction, depends T6): один файл app/Actions/Session/StartSessionAction.php
  — независимы: разные файлы Actions; routes ещё не правятся

Группа 5 (финал, последовательно): T10
  — Routes, кнопка меню, e2e-тест, обновление codebase.md / bar-bot-design.md / migration-conventions.md, PR
  — depends: T8, T9
```

---

## Критерии handoff между задачами

См. раздел **«Handoff-чеклист задачи»** в `CLAUDE.md` — план соблюдает его без изменений. Каждая задача в Steps содержит явные команды `make tests` (или таргетный фильтр) и `make pint-dirty-dry`. Файлы вне «Карты файлов» — reviewer отклоняет без явного обоснования в отчёте.

---

## Task 1: Миграция `bar_sessions` + модель `BarSession` + factory

**Depends on:** None

**Files:**
- Create: `database/migrations/2026_05_10_000001_create_bar_sessions_table.php`
- Create: `app/Models/BarSession.php`
- Create: `database/factories/BarSessionFactory.php`
- Create: `tests/Unit/Models/BarSessionFactoryTest.php`

- [ ] **Step 1: Создать миграцию**

Файл: `database/migrations/2026_05_10_000001_create_bar_sessions_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(/** @lang PostgreSQL */ "
            -- SMALLINT: 32 767 строк = ~89 лет ежедневных сессий, BIGINT здесь избыточен.
            CREATE TABLE bar_sessions (
                id         SMALLINT     GENERATED ALWAYS AS IDENTITY,
                bar_id     SMALLINT     NOT NULL DEFAULT 1,
                started_at TIMESTAMPTZ  NOT NULL,
                ended_at   TIMESTAMPTZ  NULL,
                CONSTRAINT pk_bar_sessions PRIMARY KEY (id)
            );

            -- DB-инвариант: одна активная сессия на бар.
            CREATE UNIQUE INDEX uq_bar_sessions_active
                ON bar_sessions (bar_id) WHERE ended_at IS NULL;
        ");
    }

    public function down(): void
    {
        DB::unprepared(/** @lang PostgreSQL */ "
            DROP TABLE IF EXISTS bar_sessions;
        ");
    }
};
```

- [ ] **Step 2: Прогнать миграцию**

```bash
make migration-run
```

Ожидаемо: `Migrated: 2026_05_10_000001_create_bar_sessions_table` без ошибок.

- [ ] **Step 3: Создать модель**

Файл: `app/Models/BarSession.php`

```php
<?php

namespace App\Models;

use Database\Factories\BarSessionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

final class BarSession extends Model
{
    /** @use HasFactory<BarSessionFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = ['bar_id', 'started_at', 'ended_at'];

    protected $casts = [
        'started_at' => 'immutable_datetime',
        'ended_at'   => 'immutable_datetime',
    ];
}
```

- [ ] **Step 4: Создать factory**

Файл: `database/factories/BarSessionFactory.php`

```php
<?php

namespace Database\Factories;

use App\Models\BarSession;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BarSession>
 */
final class BarSessionFactory extends Factory
{
    protected $model = BarSession::class;

    public function definition(): array
    {
        return [
            'bar_id'     => 1,
            'started_at' => now(),
            'ended_at'   => null,
        ];
    }

    public function closed(): self
    {
        return $this->state(fn () => ['ended_at' => now()]);
    }
}
```

- [ ] **Step 5: Тест factory**

Файл: `tests/Unit/Models/BarSessionFactoryTest.php`

```php
<?php

use App\Models\BarSession;

it('creates an active session by default', function () {
    $session = BarSession::factory()->create();

    expect($session->ended_at)->toBeNull()
        ->and($session->started_at)->not->toBeNull()
        ->and($session->bar_id)->toBe(1);
});

it('creates a closed session via state', function () {
    $session = BarSession::factory()->closed()->create();

    expect($session->ended_at)->not->toBeNull();
});

it('enforces single active session per bar via unique index', function () {
    BarSession::factory()->create();

    expect(fn () => BarSession::factory()->create())
        ->toThrow(\Illuminate\Database\QueryException::class);
});
```

- [ ] **Step 6: Прогнать тесты**

```bash
docker compose exec app php artisan test --filter=BarSessionFactoryTest
```

Ожидаемо: 3 PASS.

- [ ] **Step 7: Pint + commit**

```bash
make pint-dirty
git add database/migrations/2026_05_10_000001_create_bar_sessions_table.php \
        app/Models/BarSession.php \
        database/factories/BarSessionFactory.php \
        tests/Unit/Models/BarSessionFactoryTest.php
git commit --author="Claude <claude@anthropic.com>" -m "feat(bb-7): bar_sessions table + BarSession model + factory"
```

**Handoff:** 3 теста factory pass; partial unique index работает; pint-dirty-dry — 0.

---

## Task 2: Queue infrastructure

**Depends on:** None

**Files:**
- Create: `database/migrations/2026_05_10_000002_create_jobs_table.php` (генерируется artisan)
- Modify: `.env.example`
- Modify: `docker-compose.yml`

- [ ] **Step 1: Сгенерировать миграцию `jobs`**

```bash
docker compose exec app php artisan queue:table
```

Это создаст файл `database/migrations/{stub_ts}_create_jobs_table.php`. **Переименовать его** в `2026_05_10_000002_create_jobs_table.php`, чтобы порядок миграций был детерминирован.

- [ ] **Step 2: Прогнать миграцию**

```bash
make migration-run
```

- [ ] **Step 3: Установить queue driver в `.env.example`**

Файл: `.env.example` — найти `QUEUE_CONNECTION=` и заменить значение на `database`. Если нет строки — добавить:

```
QUEUE_CONNECTION=database
```

Также убедиться, что в локальном `.env` стоит то же значение (попросить пользователя проверить вручную, миграция не помогает).

- [ ] **Step 4: Добавить queue worker в `docker-compose.yml`**

Найти секцию `services:` и добавить новый сервис `queue` рядом с `app`:

```yaml
  queue:
    build:
      context: .
      dockerfile: docker/app/Dockerfile
    container_name: bar-bot-queue
    restart: unless-stopped
    # --tries не указан намеренно: значение должен задавать сам Job ($tries),
    # чтобы worker не переписывал контракт.
    command: php artisan queue:work --sleep=3 --queue=default
    volumes:
      - ./:/var/www/html
    depends_on:
      - postgres
    environment:
      - QUEUE_CONNECTION=database
```

(Точные имена `build.context`, `dockerfile`, `volumes`, `container_name` — зеркало текущего сервиса `app`, проверить и подогнать перед коммитом.)

- [ ] **Step 5: Перезапустить compose**

```bash
docker compose up -d queue
docker compose logs --tail=20 queue
```

Ожидаемо: воркер стартанул, лог содержит «Processing jobs» (или пустой, если очередь пуста).

- [ ] **Step 6: Тест dispatch (sanity check)**

```bash
docker compose exec app php artisan tinker
> dispatch(function () { logger('queue ok'); });
> exit
docker compose logs --tail=5 queue
docker compose exec app tail -n 5 storage/logs/laravel.log
```

В логах laravel должно появиться `queue ok` в течение нескольких секунд.

- [ ] **Step 7: Pint + commit**

```bash
make pint-dirty
git add database/migrations/2026_05_10_000002_create_jobs_table.php \
        .env.example \
        docker-compose.yml
git commit --author="Claude <claude@anthropic.com>" -m "feat(bb-7): queue infra — database driver + worker container"
```

**Handoff:** worker стартует и отрабатывает тестовый dispatch; jobs-таблица создана; pint-dirty-dry — 0.

---

## Task 3: `config/bar.php` + `Bar` POPO + DI singleton

**Depends on:** None

**Files:**
- Create: `config/bar.php`
- Create: `app/Models/Bar.php`
- Create: `tests/Unit/Models/BarTest.php`
- Modify: `app/Providers/AppServiceProvider.php`

- [ ] **Step 1: Конфиг бара**

Файл: `config/bar.php`

```php
<?php

return [
    'id'   => 1,
    'name' => 'Полторушка',

    'working_hours' => [
        'start' => '12:00',
        'end'   => '06:00', // через полночь
    ],

    /*
     * Запрет открытия сессии за N минут до конца окна.
     * Не даёт стартовать сессию, которая тут же закроется.
     */
    'open_cutoff_minutes' => 30,
];
```

- [ ] **Step 2: Тест Bar (red)**

Файл: `tests/Unit/Models/BarTest.php`

```php
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
```

Запустить — должен фейлиться («class not found»):

```bash
docker compose exec app php artisan test --filter=BarTest
```

- [ ] **Step 3: Bar POPO**

Файл: `app/Models/Bar.php`

```php
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
```

- [ ] **Step 4: Тест pass**

```bash
docker compose exec app php artisan test --filter=BarTest
```

Ожидаемо: 1 PASS.

- [ ] **Step 5: DI singleton**

В `app/Providers/AppServiceProvider.php`, в методе `register()`, добавить:

```php
$this->app->singleton(\App\Models\Bar::class, fn () => \App\Models\Bar::default());
```

- [ ] **Step 6: Pint + commit**

```bash
make pint-dirty
git add config/bar.php app/Models/Bar.php tests/Unit/Models/BarTest.php app/Providers/AppServiceProvider.php
git commit --author="Claude <claude@anthropic.com>" -m "feat(bb-7): Bar POPO + config/bar.php + DI singleton"
```

**Handoff:** 1 unit-тест pass; `app(Bar::class)` возвращает singleton с конфигом; pint-dirty-dry — 0.

---

## Task 4: `BarSchedule` сервис

**Depends on:** Task 3

**Files:**
- Create: `app/Services/BarSchedule.php`
- Create: `tests/Unit/Services/BarScheduleTest.php`

- [ ] **Step 1: Тесты (red)**

Файл: `tests/Unit/Services/BarScheduleTest.php`

```php
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

afterEach(fn () => CarbonImmutable::setTestNow());

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
```

```bash
docker compose exec app php artisan test --filter=BarScheduleTest
```

Ожидаемо: все FAIL (класса нет).

- [ ] **Step 2: Реализация**

Файл: `app/Services/BarSchedule.php`

```php
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
    public function currentWindow(?CarbonInterface $now = null): ?array
    {
        $now = $now ? CarbonImmutable::instance($now) : CarbonImmutable::now();

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
        $startedAt = CarbonImmutable::instance($startedAt);

        // Окно начинается в ближайшее прошедшее workStart относительно startedAt.
        $startToday = $this->buildBoundary($startedAt->toDateString(), $this->bar->workStart);

        if ($startedAt->greaterThanOrEqualTo($startToday)) {
            $start = $startToday;
        } else {
            $start = $startToday->subDay();
        }

        return ['start' => $start, 'end' => $this->endAfter($start)];
    }

    public function isInWindow(CarbonInterface $startedAt, ?CarbonInterface $now = null): bool
    {
        $now = $now ? CarbonImmutable::instance($now) : CarbonImmutable::now();
        $window = $this->windowFor($startedAt);

        return $now->greaterThanOrEqualTo($window['start']) && $now->lessThan($window['end']);
    }

    public function canOpenAt(CarbonInterface $now): bool
    {
        $window = $this->currentWindow($now);
        if ($window === null) {
            return false;
        }

        $cutoff = $window['end']->subMinutes($this->bar->openCutoffMinutes);

        return CarbonImmutable::instance($now)->lessThan($cutoff);
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
```

- [ ] **Step 3: Тесты pass**

```bash
docker compose exec app php artisan test --filter=BarScheduleTest
```

Ожидаемо: 13 PASS.

- [ ] **Step 4: Pint + commit**

```bash
make pint-dirty
git add app/Services/BarSchedule.php tests/Unit/Services/BarScheduleTest.php
git commit --author="Claude <claude@anthropic.com>" -m "feat(bb-7): BarSchedule service with full window/cutoff logic"
```

**Handoff:** 13 unit-тестов pass; покрыты все ветки времени (вход в окно, через полночь, cutoff, закрытый бар); pint-dirty-dry — 0.

---

## Task 5: `CloseSessionJob`

**Depends on:** Task 1

**Files:**
- Create: `app/Jobs/CloseSessionJob.php`
- Create: `tests/Unit/Jobs/CloseSessionJobTest.php`

- [ ] **Step 1: Тест (red)**

Файл: `tests/Unit/Jobs/CloseSessionJobTest.php`

```php
<?php

use App\Jobs\CloseSessionJob;
use App\Models\BarSession;
use Carbon\CarbonImmutable;

it('closes an active session', function () {
    $session = BarSession::factory()->create();
    $endAt = CarbonImmutable::parse('2026-05-11 06:00:00');

    (new CloseSessionJob($session->id, $endAt))->handle();

    expect($session->fresh()->ended_at?->toIso8601String())
        ->toBe('2026-05-11T06:00:00+00:00');
});

it('is no-op when session is already closed', function () {
    $session = BarSession::factory()->closed()->create();
    $originalEnd = $session->ended_at;
    $endAt = CarbonImmutable::parse('2026-05-11 06:00:00');

    (new CloseSessionJob($session->id, $endAt))->handle();

    expect($session->fresh()->ended_at?->toIso8601String())
        ->toBe($originalEnd->toIso8601String());
});

it('is no-op when session does not exist', function () {
    $endAt = CarbonImmutable::parse('2026-05-11 06:00:00');

    expect(fn () => (new CloseSessionJob(9999, $endAt))->handle())
        ->not->toThrow(\Throwable::class);
});
```

```bash
docker compose exec app php artisan test --filter=CloseSessionJobTest
```

Ожидаемо: 3 FAIL.

- [ ] **Step 2: Реализация**

Файл: `app/Jobs/CloseSessionJob.php`

```php
<?php

namespace App\Jobs;

use Carbon\CarbonInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

final class CloseSessionJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /** Source of truth для tries — здесь, не в worker --tries. */
    public int $tries = 3;

    public function __construct(
        public readonly int $sessionId,
        public readonly CarbonInterface $endAt,
    ) {}

    public function handle(): void
    {
        // Atomic: WHERE ended_at IS NULL делает закрытие идемпотентным.
        DB::table('bar_sessions')
            ->where('id', $this->sessionId)
            ->whereNull('ended_at')
            ->update(['ended_at' => $this->endAt]);
    }
}
```

- [ ] **Step 3: Тесты pass**

```bash
docker compose exec app php artisan test --filter=CloseSessionJobTest
```

Ожидаемо: 3 PASS.

- [ ] **Step 4: Pint + commit**

```bash
make pint-dirty
git add app/Jobs/CloseSessionJob.php tests/Unit/Jobs/CloseSessionJobTest.php
git commit --author="Claude <claude@anthropic.com>" -m "feat(bb-7): CloseSessionJob — atomic idempotent close"
```

**Handoff:** 3 unit-теста pass (закрытие активной / NO-OP на закрытой / NO-OP на несуществующей); `$tries=3` в job, не в worker; pint-dirty-dry — 0.

---

## Task 6: `StartSessionHandler` + DTO + Exception + Bus-маппинг

**Depends on:** Task 4, Task 5

**Files:**
- Create: `app/Data/Session/StartSessionData.php`
- Create: `app/Exceptions/BarClosedException.php`
- Create: `app/Handlers/Session/StartSessionHandler.php`
- Create: `tests/Unit/Handlers/Session/StartSessionHandlerTest.php`
- Modify: `app/Providers/BusServiceProvider.php`

- [ ] **Step 1: DTO**

Файл: `app/Data/Session/StartSessionData.php`

```php
<?php

namespace App\Data\Session;

use Spatie\LaravelData\Data;

/**
 * Старт сессии не принимает параметров — DTO нужен только для Bus-маппинга
 * (один формат для всех мутирующих команд проекта).
 */
final class StartSessionData extends Data
{
}
```

- [ ] **Step 2: Exception**

Файл: `app/Exceptions/BarClosedException.php`

```php
<?php

namespace App\Exceptions;

use RuntimeException;

final class BarClosedException extends RuntimeException
{
    public function __construct(string $message = 'Бар сейчас не работает')
    {
        parent::__construct($message);
    }
}
```

- [ ] **Step 3: Тесты handler (red)**

Файл: `tests/Unit/Handlers/Session/StartSessionHandlerTest.php`

```php
<?php

use App\Data\Session\StartSessionData;
use App\Exceptions\BarClosedException;
use App\Handlers\Session\StartSessionHandler;
use App\Jobs\CloseSessionJob;
use App\Models\BarSession;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Queue;

afterEach(fn () => CarbonImmutable::setTestNow());

it('throws when bar is closed', function () {
    CarbonImmutable::setTestNow('2026-05-10 09:00:00'); // бар закрыт
    $handler = app(StartSessionHandler::class);

    expect(fn () => $handler->handle(new StartSessionData))
        ->toThrow(BarClosedException::class);
});

it('throws when bar is in cutoff zone', function () {
    CarbonImmutable::setTestNow('2026-05-11 05:45:00'); // 15 минут до закрытия
    $handler = app(StartSessionHandler::class);

    expect(fn () => $handler->handle(new StartSessionData))
        ->toThrow(BarClosedException::class);
});

it('returns existing active session if still in window', function () {
    Queue::fake();
    CarbonImmutable::setTestNow('2026-05-10 18:00:00');
    $existing = BarSession::factory()->create(['started_at' => now()]);

    CarbonImmutable::setTestNow('2026-05-10 22:00:00'); // позже, но то же окно
    $handler = app(StartSessionHandler::class);
    $result = $handler->handle(new StartSessionData);

    expect($result->id)->toBe($existing->id);
    Queue::assertNotPushed(CloseSessionJob::class); // новую джобу не диспатчим
});

it('self-heals stale session and creates new', function () {
    Queue::fake();
    // Просроченная сессия со вчерашних 18:00
    CarbonImmutable::setTestNow('2026-05-09 18:00:00');
    BarSession::factory()->create(['started_at' => now()]);

    // Сейчас 13:00 следующего дня — вчерашнее окно давно закрылось
    CarbonImmutable::setTestNow('2026-05-10 13:00:00');
    $handler = app(StartSessionHandler::class);
    $result = $handler->handle(new StartSessionData);

    // Старая сессия закрыта (sync), создана новая
    expect(BarSession::count())->toBe(2)
        ->and($result->ended_at)->toBeNull()
        ->and($result->started_at->toIso8601String())->toBe('2026-05-10T13:00:00+00:00');

    // Delayed CloseSessionJob для новой сессии
    Queue::assertPushed(CloseSessionJob::class);
});

it('creates new session when none exists', function () {
    Queue::fake();
    CarbonImmutable::setTestNow('2026-05-10 18:00:00');
    $handler = app(StartSessionHandler::class);

    $result = $handler->handle(new StartSessionData);

    expect($result)->not->toBeNull()
        ->and($result->ended_at)->toBeNull()
        ->and($result->bar_id)->toBe(1);

    Queue::assertPushed(
        CloseSessionJob::class,
        fn (CloseSessionJob $job) =>
            $job->sessionId === $result->id
            && $job->endAt->toIso8601String() === '2026-05-11T06:00:00+00:00'
    );
});
```

```bash
docker compose exec app php artisan test --filter=StartSessionHandlerTest
```

Ожидаемо: 5 FAIL.

- [ ] **Step 4: Реализация handler**

Файл: `app/Handlers/Session/StartSessionHandler.php`

```php
<?php

namespace App\Handlers\Session;

use App\Data\Session\StartSessionData;
use App\Exceptions\BarClosedException;
use App\Jobs\CloseSessionJob;
use App\Models\Bar;
use App\Models\BarSession;
use App\Services\BarSchedule;
use Carbon\CarbonImmutable;

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
            throw new BarClosedException;
        }

        $active = BarSession::query()
            ->where('bar_id', $this->bar->id)
            ->whereNull('ended_at')
            ->first();

        if ($active && $this->schedule->isInWindow($active->started_at, $now)) {
            return $active; // идемпотентность
        }

        if ($active) {
            // Self-healing: закрыть протухшую синхронно, не дожидаясь delayed job.
            $expectedEnd = $this->schedule->expectedEndAt($active->started_at);
            CloseSessionJob::dispatchSync($active->id, $expectedEnd);
        }

        $session = BarSession::create([
            'bar_id'     => $this->bar->id,
            'started_at' => $now,
            'ended_at'   => null,
        ]);

        CloseSessionJob::dispatch($session->id, $this->schedule->expectedEndAt($now))
            ->delay($this->schedule->expectedEndAt($now));

        return $session;
    }
}
```

- [ ] **Step 5: Bus-маппинг**

В `app/Providers/BusServiceProvider.php`, в методе `register()`, добавить в массив `map`:

```php
\App\Data\Session\StartSessionData::class => \App\Handlers\Session\StartSessionHandler::class,
```

- [ ] **Step 6: Тесты pass**

```bash
docker compose exec app php artisan test --filter=StartSessionHandlerTest
```

Ожидаемо: 5 PASS.

- [ ] **Step 7: Pint + commit**

```bash
make pint-dirty
git add app/Data/Session/StartSessionData.php \
        app/Exceptions/BarClosedException.php \
        app/Handlers/Session/StartSessionHandler.php \
        app/Providers/BusServiceProvider.php \
        tests/Unit/Handlers/Session/StartSessionHandlerTest.php
git commit --author="Claude <claude@anthropic.com>" -m "feat(bb-7): StartSessionHandler — idempotent + self-healing"
```

**Handoff:** 5 unit-тестов pass (4 ветки логики + cutoff); Bus-маппинг зарегистрирован; pint-dirty-dry — 0.

---

## Task 7: `GetActiveSessionHandler`

**Depends on:** Task 1, Task 4

**Files:**
- Create: `app/Handlers/Session/GetActiveSessionHandler.php`
- Create: `tests/Unit/Handlers/Session/GetActiveSessionHandlerTest.php`

- [ ] **Step 1: Тесты (red)**

Файл: `tests/Unit/Handlers/Session/GetActiveSessionHandlerTest.php`

```php
<?php

use App\Handlers\Session\GetActiveSessionHandler;
use App\Models\BarSession;
use Carbon\CarbonImmutable;

afterEach(fn () => CarbonImmutable::setTestNow());

it('returns active session if it is still in window', function () {
    CarbonImmutable::setTestNow('2026-05-10 18:00:00');
    $session = BarSession::factory()->create(['started_at' => now()]);

    CarbonImmutable::setTestNow('2026-05-10 22:00:00');

    expect(app(GetActiveSessionHandler::class)->handle()?->id)
        ->toBe($session->id);
});

it('returns null if active session is past its window', function () {
    CarbonImmutable::setTestNow('2026-05-09 18:00:00');
    BarSession::factory()->create(['started_at' => now()]);

    CarbonImmutable::setTestNow('2026-05-10 13:00:00'); // следующий день, окно закрылось

    expect(app(GetActiveSessionHandler::class)->handle())->toBeNull();
});

it('returns null when no active session', function () {
    BarSession::factory()->closed()->create();

    expect(app(GetActiveSessionHandler::class)->handle())->toBeNull();
});
```

```bash
docker compose exec app php artisan test --filter=GetActiveSessionHandlerTest
```

Ожидаемо: 3 FAIL.

- [ ] **Step 2: Реализация**

Файл: `app/Handlers/Session/GetActiveSessionHandler.php`

```php
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
```

- [ ] **Step 3: Тесты pass**

```bash
docker compose exec app php artisan test --filter=GetActiveSessionHandlerTest
```

Ожидаемо: 3 PASS.

- [ ] **Step 4: Pint + commit**

```bash
make pint-dirty
git add app/Handlers/Session/GetActiveSessionHandler.php tests/Unit/Handlers/Session/GetActiveSessionHandlerTest.php
git commit --author="Claude <claude@anthropic.com>" -m "feat(bb-7): GetActiveSessionHandler with window check"
```

**Handoff:** 3 unit-теста pass; handler возвращает null для просроченных сессий, не модифицируя БД; pint-dirty-dry — 0.

---

## Task 8: `SessionAction` (HTTP GET + Telegram `/session`)

**Depends on:** Task 7

**Files:**
- Create: `app/Actions/Session/SessionAction.php`
- Create: `tests/Feature/Actions/Session/SessionActionTest.php`

- [ ] **Step 1: Feature-тест (со `->skip` до T10)**

> **Замечание для исполнителя:** route регистрируется в финальной T10. Тесты пишутся сразу со `->skip('routes registered in T10')` — `->skip` снимается в T10. Так T8 не падает на отсутствующих маршрутах.

Файл: `tests/Feature/Actions/Session/SessionActionTest.php`

```php
<?php

use App\Models\BarSession;
use App\Models\User;
use Carbon\CarbonImmutable;

afterEach(fn () => CarbonImmutable::setTestNow());

it('GET returns 200 with active session payload', function () {
    CarbonImmutable::setTestNow('2026-05-10 18:00:00');
    $user = User::factory()->create();
    $session = BarSession::factory()->create(['started_at' => now()]);

    $this->getJson("/api/bars/1/session?telegram_id={$user->telegram_id}")
        ->assertOk()
        ->assertJsonPath('id', $session->id)
        ->assertJsonPath('bar_id', 1)
        ->assertJsonPath('ended_at', null);
})->skip('routes registered in T10');

it('GET returns 204 when no active session', function () {
    $user = User::factory()->create();

    $this->getJson("/api/bars/1/session?telegram_id={$user->telegram_id}")
        ->assertNoContent();
})->skip('routes registered in T10');

it('GET returns 204 when session exists but is past its window', function () {
    CarbonImmutable::setTestNow('2026-05-09 18:00:00');
    $user = User::factory()->create();
    BarSession::factory()->create(['started_at' => now()]);
    CarbonImmutable::setTestNow('2026-05-10 13:00:00');

    $this->getJson("/api/bars/1/session?telegram_id={$user->telegram_id}")
        ->assertNoContent();
})->skip('routes registered in T10');

it('GET returns 404 when bar id does not match config', function () {
    $user = User::factory()->create();

    $this->getJson("/api/bars/2/session?telegram_id={$user->telegram_id}")
        ->assertNotFound();
})->skip('routes registered in T10');
```

Прогон необязателен — все тесты `->skip`. Запустить можно для подтверждения, что Pest не падает на парсинге:

```bash
docker compose exec app php artisan test --filter=SessionActionTest
```

Ожидаемо: 4 SKIPPED.

- [ ] **Step 2: Реализация Action**

Файл: `app/Actions/Session/SessionAction.php`

```php
<?php

namespace App\Actions\Session;

use App\Handlers\Session\GetActiveSessionHandler;
use App\Models\Bar;
use App\Services\BarSchedule;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Properties\ParseMode;

final class SessionAction
{
    public function __construct(
        private readonly GetActiveSessionHandler $handler,
        private readonly Bar $bar,
        private readonly BarSchedule $schedule,
    ) {}

    public function __invoke(Request $request, int $id): JsonResponse|Response
    {
        if ($id !== $this->bar->id) {
            abort(404);
        }

        $session = $this->handler->handle();

        if ($session === null) {
            return response()->noContent();
        }

        return response()->json($session);
    }

    public function fromTelegram(Nutgram $bot): void
    {
        $session = $this->handler->handle();
        $now = CarbonImmutable::now();
        $canManage = $bot->user()
            ? auth()->user()?->role->canManage() ?? false
            : false;

        if ($session !== null) {
            $bot->sendMessage(
                text: sprintf(
                    "🍸 *Сессия открыта*\nС %s\nЗакроется в %s",
                    $session->started_at->format('H:i'),
                    $this->schedule->expectedEndAt($session->started_at)->format('H:i'),
                ),
                parse_mode: ParseMode::MARKDOWN,
            );

            return;
        }

        if (! $canManage) {
            $bot->sendMessage(text: '🍸 Сессия не открыта.');

            return;
        }

        if ($this->schedule->canOpenAt($now)) {
            $bot->sendMessage(
                text: '🍸 Бар работает. Открыть сессию?',
                reply_markup: \SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup::make()
                    ->addRow(\SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton::make(
                        text: '🟢 Старт',
                        callback_data: 'session:start',
                    )),
            );

            return;
        }

        $bot->sendMessage(text: sprintf(
            "🍸 Бар закрыт. Работает с %s до %s (последний старт за %d минут до закрытия).",
            $this->bar->workStart,
            $this->bar->workEnd,
            $this->bar->openCutoffMinutes,
        ));
    }
}
```

- [ ] **Step 3: Pint + commit**

```bash
make pint-dirty
git add app/Actions/Session/SessionAction.php tests/Feature/Actions/Session/SessionActionTest.php
git commit --author="Claude <claude@anthropic.com>" -m "feat(bb-7): SessionAction — HTTP GET + Telegram /session"
```

**Handoff:** Action-класс собран, контракт API/Telegram реализован; feature-тесты помечены `->skip('routes registered in T10')` — снимутся в финальной задаче; pint-dirty-dry — 0.

---

## Task 9: `StartSessionAction` (HTTP POST + Telegram `session:start`)

**Depends on:** Task 6

**Files:**
- Create: `app/Actions/Session/StartSessionAction.php`
- Create: `tests/Feature/Actions/Session/StartSessionActionTest.php`

- [ ] **Step 1: Feature-тест (red, тоже со `->skip('routes in T10')`)**

Файл: `tests/Feature/Actions/Session/StartSessionActionTest.php`

```php
<?php

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Queue;

afterEach(fn () => CarbonImmutable::setTestNow());

it('POST returns 201 for bartender, body contains created session', function () {
    Queue::fake();
    CarbonImmutable::setTestNow('2026-05-10 18:00:00');
    $user = User::factory()->bartender()->create();

    $this->postJson("/api/bars/1/session?telegram_id={$user->telegram_id}")
        ->assertCreated()
        ->assertJsonPath('bar_id', 1)
        ->assertJsonPath('ended_at', null);
})->skip('routes registered in T10');

it('POST returns 403 for guest', function () {
    CarbonImmutable::setTestNow('2026-05-10 18:00:00');
    $user = User::factory()->create(); // guest по дефолту

    $this->postJson("/api/bars/1/session?telegram_id={$user->telegram_id}")
        ->assertForbidden();
})->skip('routes registered in T10');

it('POST is idempotent — returns existing active session on second call', function () {
    Queue::fake();
    CarbonImmutable::setTestNow('2026-05-10 18:00:00');
    $user = User::factory()->bartender()->create();

    $first = $this->postJson("/api/bars/1/session?telegram_id={$user->telegram_id}")->json('id');
    $second = $this->postJson("/api/bars/1/session?telegram_id={$user->telegram_id}")->json('id');

    expect($first)->toBe($second);
})->skip('routes registered in T10');

it('POST returns 409 when bar is closed (BarClosedException)', function () {
    CarbonImmutable::setTestNow('2026-05-10 09:00:00'); // бар закрыт
    $user = User::factory()->bartender()->create();

    $this->postJson("/api/bars/1/session?telegram_id={$user->telegram_id}")
        ->assertStatus(409); // Conflict — domain rule violation
})->skip('routes registered in T10');

it('POST returns 404 when bar id does not match config', function () {
    $user = User::factory()->bartender()->create();

    $this->postJson("/api/bars/2/session?telegram_id={$user->telegram_id}")
        ->assertNotFound();
})->skip('routes registered in T10');
```

- [ ] **Step 2: Реализация Action**

Файл: `app/Actions/Session/StartSessionAction.php`

```php
<?php

namespace App\Actions\Session;

use App\Data\Session\StartSessionData;
use App\Exceptions\BarClosedException;
use App\Models\Bar;
use App\Models\BarSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;
use SergiX44\Nutgram\Nutgram;

final class StartSessionAction
{
    public function __construct(private readonly Bar $bar) {}

    public function __invoke(Request $request, int $id): JsonResponse
    {
        if ($id !== $this->bar->id) {
            abort(404);
        }

        try {
            /** @var BarSession $session */
            $session = Bus::dispatch(new StartSessionData);
        } catch (BarClosedException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }

        return response()->json($session, 201);
    }

    public function fromTelegram(Nutgram $bot): void
    {
        try {
            Bus::dispatch(new StartSessionData);
            $bot->answerCallbackQuery(text: '✅ Сессия открыта');
        } catch (BarClosedException $e) {
            $bot->answerCallbackQuery(text: '🚫 Бар закрыт');
        }
    }
}
```

- [ ] **Step 3: Pint + commit**

```bash
make pint-dirty
git add app/Actions/Session/StartSessionAction.php tests/Feature/Actions/Session/StartSessionActionTest.php
git commit --author="Claude <claude@anthropic.com>" -m "feat(bb-7): StartSessionAction — HTTP POST + Telegram callback"
```

**Handoff:** Action собран, обрабатывает идемпотентность и `BarClosedException`; feature-тесты `->skip` до T10; pint-dirty-dry — 0.

---

## Task 10 (Финал): Routes + меню + e2e + docs + PR

**Depends on:** Task 8, Task 9

**Files:**
- Modify: `routes/api.php`
- Modify: `routes/telegram.php`
- Modify: `app/Telegram/Handlers/StartHandler.php`
- Modify: `tests/Feature/Actions/Session/SessionActionTest.php` (снять `->skip`)
- Modify: `tests/Feature/Actions/Session/StartSessionActionTest.php` (снять `->skip`)
- Create: `tests/Feature/PhaseThreeFlowTest.php`
- Modify: `.agents/knowledge/codebase.md`
- Modify: `.agents/specs/bar-bot-design.md`
- Modify: `.agents/specs/migration-conventions.md`

- [ ] **Step 1: Зарегистрировать HTTP-маршруты**

В `routes/api.php` добавить (рядом с группой inventory):

```php
use App\Actions\Session\SessionAction;
use App\Actions\Session\StartSessionAction;

Route::middleware('auth.telegram')->group(function () {
    Route::get('/bars/{id}/session', SessionAction::class);

    Route::middleware(\App\Http\Middleware\CanManageMiddleware::class)->group(function () {
        Route::post('/bars/{id}/session', StartSessionAction::class);
    });
});
```

- [ ] **Step 2: Зарегистрировать Telegram-маршруты**

В `routes/telegram.php` найти секцию активных маршрутов и добавить:

```php
$bot->onCommand('session', [SessionAction::class, 'fromTelegram']);
$bot->onCallbackQueryData('cmd:session', [SessionAction::class, 'fromTelegram']);

$bot->group(function (Nutgram $bot) {
    $bot->onCallbackQueryData('session:start', [StartSessionAction::class, 'fromTelegram']);
})->middleware(CanManageMiddleware::class);
```

(Проверить, что use-statements `SessionAction`, `StartSessionAction` и `CanManageMiddleware` уже добавлены вверху файла.)

- [ ] **Step 3: Кнопка «🍸 Сессия» в главном меню**

В `app/Telegram/Handlers/StartHandler.php` найти место, где собираются кнопки главного меню (сейчас там «🔍 Поиск», «🧪 По ингредиентам», «🎛 Фильтры», «📦 Инвентарь»), и добавить пятую:

```php
InlineKeyboardButton::make(text: '🍸 Сессия', callback_data: 'cmd:session'),
```

Расположить логически рядом с «📦 Инвентарь» (обе — операционные).

- [ ] **Step 4: Снять `->skip()` с feature-тестов T8/T9**

В обоих файлах `SessionActionTest.php` и `StartSessionActionTest.php` удалить `->skip('routes registered in T10')` со всех тестов.

- [ ] **Step 5: E2E flow тест**

Файл: `tests/Feature/PhaseThreeFlowTest.php`

```php
<?php

use App\Jobs\CloseSessionJob;
use App\Models\BarSession;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Queue;

afterEach(fn () => CarbonImmutable::setTestNow());

it('full flow: open via API → see active via API → time travel → auto-closed', function () {
    Queue::fake();
    $user = User::factory()->bartender()->create();

    // 18:00 — открываем сессию
    CarbonImmutable::setTestNow('2026-05-10 18:00:00');
    $created = $this->postJson("/api/bars/1/session?telegram_id={$user->telegram_id}")
        ->assertCreated()
        ->json('id');

    // GET — активна
    $this->getJson("/api/bars/1/session?telegram_id={$user->telegram_id}")
        ->assertOk()
        ->assertJsonPath('id', $created);

    // delayed CloseSessionJob запланирован
    Queue::assertPushed(CloseSessionJob::class, function (CloseSessionJob $job) use ($created) {
        return $job->sessionId === $created
            && $job->endAt->toIso8601String() === '2026-05-11T06:00:00+00:00';
    });

    // Симулируем выполнение job в 06:00 следующего дня
    CarbonImmutable::setTestNow('2026-05-11 06:00:00');
    $session = BarSession::find($created);
    (new CloseSessionJob($created, CarbonImmutable::parse('2026-05-11 06:00:00')))->handle();

    // GET — 204
    $this->getJson("/api/bars/1/session?telegram_id={$user->telegram_id}")
        ->assertNoContent();
});

it('self-healing: stale session is closed when new start arrives', function () {
    Queue::fake();
    $user = User::factory()->bartender()->create();

    // Вчера 18:00 — старая сессия
    CarbonImmutable::setTestNow('2026-05-09 18:00:00');
    $stale = BarSession::factory()->create(['started_at' => now()]);

    // Сегодня 13:00 — открываем новую
    CarbonImmutable::setTestNow('2026-05-10 13:00:00');
    $newId = $this->postJson("/api/bars/1/session?telegram_id={$user->telegram_id}")
        ->assertCreated()
        ->json('id');

    // Старая закрыта, новая активна
    expect(BarSession::find($stale->id)->ended_at)->not->toBeNull()
        ->and(BarSession::find($newId)->ended_at)->toBeNull();
});
```

- [ ] **Step 6: Прогнать весь suite**

```bash
make tests
```

Ожидаемо: все тесты pass, включая новые `SessionActionTest` (4) + `StartSessionActionTest` (5) + `PhaseThreeFlowTest` (2).

- [ ] **Step 7: Обновить `codebase.md`**

В `.agents/knowledge/codebase.md`:

1. В таблице «Статус реализации фаз» поменять Phase 3 → ✅ Готово.
2. Добавить раздел **«BarSchedule (app/Services/BarSchedule.php)»** с описанием контракта и примером использования.
3. Добавить раздел **«Bar (app/Models/Bar.php) — POPO singleton»** с примером.
4. Добавить раздел **«Queue infrastructure»**: `database` driver, контейнер `queue`, паттерн `$tries` в job.
5. В разделе «Telegram-маршруты» добавить:
   ```
   onCommand('session')                      → SessionAction::fromTelegram
   onCallbackQueryData('cmd:session')        → SessionAction::fromTelegram

   Group [CanManageMiddleware]:
     onCallbackQueryData('session:start')    → StartSessionAction::fromTelegram
   ```
6. В «Кнопки главного меню» добавить «🍸 Сессия → cmd:session».
7. В «HTTP API-маршруты» добавить:
   ```
   GET    /api/bars/{id}/session     → SessionAction
   POST   /api/bars/{id}/session     → StartSessionAction  [+CanManageMiddleware]
   ```
8. В «Известные особенности» — добавить пункт про авто-закрытие через delayed job + self-healing.

- [ ] **Step 8: Обновить `bar-bot-design.md`**

В `.agents/specs/bar-bot-design.md`:

1. Раздел **«Фаза 3 — Бар-сессия»** переписать под фактический дизайн:
   - Только авто-закрытие в конце рабочего окна (12:00–06:00); ручной кнопки «Завершить» нет.
   - `Bar` — POPO из конфига; эволюция к таблице — без переписывания потребителей.
   - HTTP: `POST /api/bars/{id}/session`, `GET /api/bars/{id}/session` (204 на пусто).
   - Telegram: `/session` команда + кнопка меню; callback `session:start` под `CanManageMiddleware`.
   - Открытие запрещено за `open_cutoff_minutes` минут до конца окна.
2. В таблице моделей (раздел 4) `BarSession` уточнить: PK SMALLINT, без timestamps.
3. В разделе **9 «Открытые вопросы»** убрать (или пометить как закрытое) всё, что относится к Phase 3.

- [ ] **Step 9: Обновить `migration-conventions.md`**

В разделе «Первичные ключи» добавить пометку перед примером BIGINT IDENTITY:

> Размер PK обосновывается в комментарии миграции — нет «дефолтного» BIGINT по умолчанию.
> SMALLINT/INTEGER допустим для таблиц с явно ограниченной кардинальностью (см. пример в `bar_sessions` migration: 32k значений = 89 лет ежедневных сессий).

- [ ] **Step 10: Финальный pint + tests**

```bash
make pint-dirty
make tests
```

Ожидаемо: 0 изменений pint, весь suite зелёный.

- [ ] **Step 11: Commit + push + PR**

```bash
git add routes/api.php \
        routes/telegram.php \
        app/Telegram/Handlers/StartHandler.php \
        tests/Feature/Actions/Session/SessionActionTest.php \
        tests/Feature/Actions/Session/StartSessionActionTest.php \
        tests/Feature/PhaseThreeFlowTest.php \
        .agents/knowledge/codebase.md \
        .agents/specs/bar-bot-design.md \
        .agents/specs/migration-conventions.md
git commit --author="Claude <claude@anthropic.com>" -m "feat(bb-7): wire routes + e2e flow + docs"

git push -u origin feature/bb7_bar-sessions

gh pr create \
  --title "bb7: bar sessions — auto-close + self-healing" \
  --body "$(cat <<'EOF'
## Summary
- Bar-сессия: один бар, одна активная сессия на момент времени, сессия = время работы бара (12:00–06:00 через полночь)
- Идемпотентный старт через Telegram (\`/session\` + кнопка) и HTTP (\`POST /api/bars/{id}/session\`)
- Авто-закрытие через delayed \`CloseSessionJob\` в конце рабочего окна; self-healing просроченных сессий при следующем открытии
- Запрет открытия за \`open_cutoff_minutes\` (30 мин) до закрытия
- DB-инвариант «одна активная сессия на бар» через partial unique index
- Queue infra: \`database\` driver + \`queue\` worker контейнер
- \`Bar\` — POPO singleton из \`config/bar.php\`, без таблицы; эволюция к мульти-бару — без переписывания
- Spec и codebase.md обновлены; \`migration-conventions.md\` дополнен правилом про обоснование размера PK
EOF
)"
```

**Handoff (финальный):** весь suite pass; pint-dirty-dry — 0; codebase.md / bar-bot-design.md / migration-conventions.md обновлены; PR открыт — ссылка в отчёте.

---

## Pre-PR проверка автора плана

- [x] Секция **Goal** заполнена (одно предложение).
- [x] Секция **Branch** содержит конкретное имя ветки (`feature/bb7_bar-sessions`).
- [x] **Карта файлов** перечисляет все файлы, упомянутые в Steps (включая ancillary: `codebase.md`, `bar-bot-design.md`, `migration-conventions.md`, `routes/*.php`, фабрики, `BusServiceProvider`, `AppServiceProvider`, `docker-compose.yml`, `.env.example`).
- [x] **Порядок исполнения** присутствует, каждая параллельная группа имеет однострочное обоснование.
- [x] У каждой задачи есть **Depends on** (`None` или ссылки).
- [x] У каждой задачи есть секция **Files** (Create / Modify).
- [x] Финальная задача содержит Step «Обновить codebase.md» и Step «Открыть PR».
