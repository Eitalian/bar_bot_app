<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(/** @lang PostgreSQL */ "
            CREATE TABLE bar_sessions (
                id         BIGINT      GENERATED ALWAYS AS IDENTITY,
                started_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                ended_at   TIMESTAMPTZ NULL,
                created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                CONSTRAINT pk_bar_sessions PRIMARY KEY (id)
            );
        ");
    }

    public function down(): void
    {
        DB::unprepared(/** @lang PostgreSQL */ "
            DROP TABLE IF EXISTS bar_sessions;
        ");
    }
};
