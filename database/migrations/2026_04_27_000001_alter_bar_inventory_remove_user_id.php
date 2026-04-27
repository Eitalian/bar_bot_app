<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(/** @lang PostgreSQL */ "
            ALTER TABLE bar_inventory
                DROP CONSTRAINT IF EXISTS fk_bar_inventory_user_id,
                DROP CONSTRAINT IF EXISTS uq_bar_inventory_user_id_ingredient_id,
                DROP COLUMN IF EXISTS user_id,
                ADD CONSTRAINT uq_bar_inventory_ingredient_id UNIQUE (ingredient_id);
        ");
    }

    public function down(): void
    {
        DB::unprepared(/** @lang PostgreSQL */ "
            ALTER TABLE bar_inventory
                DROP CONSTRAINT IF EXISTS uq_bar_inventory_ingredient_id,
                ADD COLUMN user_id BIGINT NOT NULL DEFAULT 0,
                ADD CONSTRAINT fk_bar_inventory_user_id
                    FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
                ADD CONSTRAINT uq_bar_inventory_user_id_ingredient_id
                    UNIQUE (user_id, ingredient_id);
        ");
    }
};
