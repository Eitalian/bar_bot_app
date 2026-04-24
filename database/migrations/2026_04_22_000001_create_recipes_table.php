<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(/** @lang PostgreSQL */ "
            CREATE TABLE recipes (
                id           UUID         NOT NULL DEFAULT uuid_generate_v7(),
                name_ru      VARCHAR(255) NOT NULL,
                name_en      VARCHAR(255) NULL,
                description  TEXT         NULL,
                instructions TEXT         NULL,
                glass        VARCHAR(255) NULL,
                abv          NUMERIC(5,2) NULL,
                volume       INTEGER      NULL,
                icon         VARCHAR(255) NULL,
                photo        VARCHAR(255) NULL,
                taste_tags   JSONB        NULL,
                created_at   TIMESTAMPTZ  NOT NULL DEFAULT NOW(),
                updated_at   TIMESTAMPTZ  NOT NULL DEFAULT NOW(),
                CONSTRAINT pk_recipes PRIMARY KEY (id),
                CONSTRAINT chk_recipes_abv CHECK (abv IS NULL OR (abv >= 0.00 AND abv <= 100.00))
            );
        ");
    }

    public function down(): void
    {
        DB::unprepared(/** @lang PostgreSQL */ "
            DROP TABLE IF EXISTS recipes;
        ");
    }
};
