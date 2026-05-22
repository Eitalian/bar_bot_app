<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(/** @lang PostgreSQL */ "
            -- Supersedes the placeholder stub from 2026_04_22_100002_create_bar_sessions_table
            -- which was already migrated but had wrong schema (no bar_id, BIGINT PK, stray timestamps).
            -- Table was empty at migration time; safe to recreate.
            -- CASCADE drops the FK constraint from the orders stub table (also a placeholder).
            DROP TABLE IF EXISTS bar_sessions CASCADE;

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
