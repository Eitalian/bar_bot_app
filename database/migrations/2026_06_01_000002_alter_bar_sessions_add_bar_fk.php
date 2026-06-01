<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(/** @lang PostgreSQL */ "
            -- bar_id уже существует (DEFAULT 1) со времён создания таблицы — теперь даём ему FK.
            -- CASCADE: нет бара → нет его сессий.
            -- FK-индекс опущен: bar_sessions заведомо крошечная (SMALLINT PK, одна активная сессия на бар).
            ALTER TABLE bar_sessions
                ADD CONSTRAINT fk_bar_sessions_bar_id
                    FOREIGN KEY (bar_id) REFERENCES bars (id) ON DELETE CASCADE;
        ");
    }

    public function down(): void
    {
        DB::unprepared(/** @lang PostgreSQL */ "
            ALTER TABLE bar_sessions
                DROP CONSTRAINT IF EXISTS fk_bar_sessions_bar_id;
        ");
    }
};
