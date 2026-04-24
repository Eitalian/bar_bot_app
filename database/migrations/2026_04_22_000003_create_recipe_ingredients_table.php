<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(/** @lang PostgreSQL */ "
            CREATE TABLE recipe_ingredients (
                id            BIGINT       GENERATED ALWAYS AS IDENTITY,
                recipe_id     UUID         NOT NULL,
                ingredient_id UUID         NOT NULL,
                amount        VARCHAR(255) NULL,
                unit          VARCHAR(255) NULL,
                note          VARCHAR(255) NULL,
                sort_order    INTEGER      NOT NULL DEFAULT 0,
                CONSTRAINT pk_recipe_ingredients PRIMARY KEY (id),
                CONSTRAINT fk_recipe_ingredients_recipe_id
                    FOREIGN KEY (recipe_id)     REFERENCES recipes     (id) ON DELETE CASCADE,
                CONSTRAINT fk_recipe_ingredients_ingredient_id
                    FOREIGN KEY (ingredient_id) REFERENCES ingredients (id) ON DELETE CASCADE
            );
        ");
    }

    public function down(): void
    {
        DB::unprepared(/** @lang PostgreSQL */ "
            DROP TABLE IF EXISTS recipe_ingredients;
        ");
    }
};
