<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(/** @lang PostgreSQL */ "
            CREATE TABLE users (
                id          BIGINT       GENERATED ALWAYS AS IDENTITY,
                telegram_id BIGINT       NOT NULL,
                first_name  VARCHAR(255) NOT NULL,
                username    VARCHAR(255) NULL,
                created_at  TIMESTAMPTZ  NOT NULL DEFAULT NOW(),
                CONSTRAINT pk_users             PRIMARY KEY (id),
                CONSTRAINT uq_users_telegram_id UNIQUE (telegram_id)
            );
        ");
    }

    public function down(): void
    {
        DB::unprepared(/** @lang PostgreSQL */ "
            DROP TABLE IF EXISTS users;
        ");
    }
};
