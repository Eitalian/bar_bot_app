<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // favorites: replace composite PK with surrogate id, add UNIQUE constraint, drop updated_at
        DB::unprepared('
            ALTER TABLE favorites
                DROP CONSTRAINT pk_favorites,
                ADD COLUMN id INTEGER GENERATED ALWAYS AS IDENTITY,
                ADD CONSTRAINT pk_favorites PRIMARY KEY (id),
                ADD CONSTRAINT uq_favorites_user_recipe UNIQUE (user_id, recipe_id),
                DROP COLUMN updated_at
        ');

        // ratings: replace composite PK with surrogate id, add UNIQUE constraint
        DB::unprepared('
            ALTER TABLE ratings
                DROP CONSTRAINT pk_ratings,
                ADD COLUMN id INTEGER GENERATED ALWAYS AS IDENTITY,
                ADD CONSTRAINT pk_ratings PRIMARY KEY (id),
                ADD CONSTRAINT uq_ratings_user_recipe UNIQUE (user_id, recipe_id)
        ');
    }

    public function down(): void
    {
        // favorites: restore composite PK, remove surrogate id, restore updated_at
        DB::unprepared('
            ALTER TABLE favorites
                DROP CONSTRAINT pk_favorites,
                DROP CONSTRAINT uq_favorites_user_recipe,
                DROP COLUMN id,
                ADD CONSTRAINT pk_favorites PRIMARY KEY (user_id, recipe_id),
                ADD COLUMN updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
        ');

        // ratings: restore composite PK, remove surrogate id
        DB::unprepared('
            ALTER TABLE ratings
                DROP CONSTRAINT pk_ratings,
                DROP CONSTRAINT uq_ratings_user_recipe,
                DROP COLUMN id,
                ADD CONSTRAINT pk_ratings PRIMARY KEY (user_id, recipe_id)
        ');
    }
};
