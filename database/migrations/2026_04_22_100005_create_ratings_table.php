<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(/** @lang PostgreSQL */ "
            CREATE TABLE ratings (
                user_id    BIGINT      NOT NULL,
                recipe_id  TEXT        NOT NULL,
                score      SMALLINT    NOT NULL,
                created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                CONSTRAINT pk_ratings PRIMARY KEY (user_id, recipe_id),
                CONSTRAINT fk_ratings_user_id
                    FOREIGN KEY (user_id)   REFERENCES users   (id) ON DELETE CASCADE,
                CONSTRAINT fk_ratings_recipe_id
                    FOREIGN KEY (recipe_id) REFERENCES recipes (id) ON DELETE CASCADE
            );
        ");
    }

    public function down(): void
    {
        DB::unprepared(/** @lang PostgreSQL */ "
            DROP TABLE IF EXISTS ratings;
        ");
    }
};
