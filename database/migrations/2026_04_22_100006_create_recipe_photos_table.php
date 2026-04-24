<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(/** @lang PostgreSQL */ "
            CREATE TABLE recipe_photos (
                id               BIGINT      GENERATED ALWAYS AS IDENTITY,
                recipe_id        TEXT        NOT NULL,
                user_id          BIGINT      NOT NULL,
                telegram_file_id TEXT        NOT NULL,
                created_at       TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                updated_at       TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                CONSTRAINT pk_recipe_photos PRIMARY KEY (id),
                CONSTRAINT fk_recipe_photos_recipe_id
                    FOREIGN KEY (recipe_id) REFERENCES recipes (id) ON DELETE CASCADE,
                CONSTRAINT fk_recipe_photos_user_id
                    FOREIGN KEY (user_id)   REFERENCES users   (id) ON DELETE CASCADE
            );
        ");
    }

    public function down(): void
    {
        DB::unprepared(/** @lang PostgreSQL */ "
            DROP TABLE IF EXISTS recipe_photos;
        ");
    }
};
