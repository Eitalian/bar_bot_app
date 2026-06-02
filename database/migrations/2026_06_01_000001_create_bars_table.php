<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(/** @lang PostgreSQL */ "
            -- SMALLINT: согласуется с bar_sessions.bar_id; число баров заведомо мало.
            CREATE TABLE bars (
                id                  SMALLINT     GENERATED ALWAYS AS IDENTITY,
                owner_id            BIGINT       NOT NULL,
                name                VARCHAR(255) NOT NULL,
                work_start          TIME         NOT NULL,
                work_end            TIME         NOT NULL,
                open_cutoff_minutes SMALLINT     NOT NULL DEFAULT 30,
                created_at          TIMESTAMPTZ  NOT NULL DEFAULT NOW(),
                updated_at          TIMESTAMPTZ  NOT NULL DEFAULT NOW(),
                CONSTRAINT pk_bars PRIMARY KEY (id),
                -- RESTRICT: владельца нельзя удалить, пока он владеет баром.
                -- FK-индекс не нужен — таблица в считанные строки.
                CONSTRAINT fk_bars_owner_id
                    FOREIGN KEY (owner_id) REFERENCES users (id) ON DELETE RESTRICT
            );

            -- Плейсхолдер-владелец: tg_id 0 заменяется на реального владельца на уровне приложения.
            INSERT INTO users (telegram_id, first_name, role)
            VALUES (0, 'Owner', 'owner');

            -- Единственный бар; значения перенесены из config/bar.php.
            -- work_end < work_start (06:00 < 12:00) — окно через полночь, без CHECK.
            INSERT INTO bars (owner_id, name, work_start, work_end, open_cutoff_minutes)
            VALUES (
                (SELECT id FROM users WHERE telegram_id = 0),
                'Полторушка', '12:00', '06:00', 30
            );
        ");
    }

    public function down(): void
    {
        DB::unprepared(/** @lang PostgreSQL */ "
            DROP TABLE IF EXISTS bars;
            DELETE FROM users WHERE telegram_id = 0;
        ");
    }
};
