<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(/** @lang PostgreSQL */ "
            CREATE TABLE ingredients (
                id         TEXT         NOT NULL,
                name_ru    VARCHAR(255) NULL,
                name_en    VARCHAR(255) NULL,
                category   VARCHAR(255) NULL,
                created_at TIMESTAMPTZ  NOT NULL DEFAULT NOW(),
                updated_at TIMESTAMPTZ  NOT NULL DEFAULT NOW(),
                CONSTRAINT pk_ingredients PRIMARY KEY (id)
            );
        ");
    }

    public function down(): void
    {
        DB::unprepared(/** @lang PostgreSQL */ "
            DROP TABLE IF EXISTS ingredients;
        ");
    }
};
