<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(/** @lang PostgreSQL */ "
            -- favorites: replace composite PK with surrogate id, add UNIQUE, drop updated_at (Phase 4 design: \$timestamps=false)
            ALTER TABLE favorites
                DROP CONSTRAINT pk_favorites,
                ADD COLUMN id INTEGER GENERATED ALWAYS AS IDENTITY,
                ADD CONSTRAINT pk_favorites PRIMARY KEY (id),
                ADD CONSTRAINT uq_favorites_user_recipe UNIQUE (user_id, recipe_id),
                DROP COLUMN updated_at;

            -- ratings: replace composite PK with surrogate id, add UNIQUE
            ALTER TABLE ratings
                DROP CONSTRAINT pk_ratings,
                ADD COLUMN id INTEGER GENERATED ALWAYS AS IDENTITY,
                ADD CONSTRAINT pk_ratings PRIMARY KEY (id),
                ADD CONSTRAINT uq_ratings_user_recipe UNIQUE (user_id, recipe_id);
        ");
    }

    public function down(): void
    {
        DB::unprepared(/** @lang PostgreSQL */ "
            ALTER TABLE favorites
                DROP CONSTRAINT pk_favorites,
                DROP CONSTRAINT uq_favorites_user_recipe,
                DROP COLUMN id,
                ADD CONSTRAINT pk_favorites PRIMARY KEY (user_id, recipe_id),
                ADD COLUMN updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW();

            ALTER TABLE ratings
                DROP CONSTRAINT pk_ratings,
                DROP CONSTRAINT uq_ratings_user_recipe,
                DROP COLUMN id,
                ADD CONSTRAINT pk_ratings PRIMARY KEY (user_id, recipe_id);
        ");
    }
};
