<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(/** @lang PostgreSQL */ "
            CREATE TABLE recipe_tags (
                id        BIGINT       GENERATED ALWAYS AS IDENTITY,
                recipe_id TEXT         NOT NULL,
                tag       VARCHAR(255) NOT NULL,
                CONSTRAINT pk_recipe_tags PRIMARY KEY (id),
                CONSTRAINT fk_recipe_tags_recipe_id
                    FOREIGN KEY (recipe_id) REFERENCES recipes (id) ON DELETE CASCADE
            );
        ");
    }

    public function down(): void
    {
        DB::unprepared(/** @lang PostgreSQL */ "
            DROP TABLE IF EXISTS recipe_tags;
        ");
    }
};
