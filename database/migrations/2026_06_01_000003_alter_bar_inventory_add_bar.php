<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(/** @lang PostgreSQL */ "
            -- Инвентарь был глобальным (UNIQUE по ingredient_id); привязываем к бару.
            -- Данных нет — DEFAULT 1 + смена UNIQUE безопасны.
            -- FK-индекс на bar_id не нужен: он ведущая колонка нового UNIQUE(bar_id, ingredient_id).
            ALTER TABLE bar_inventory
                ADD COLUMN bar_id SMALLINT NOT NULL DEFAULT 1,
                DROP CONSTRAINT IF EXISTS uq_bar_inventory_ingredient_id,
                ADD CONSTRAINT fk_bar_inventory_bar_id
                    FOREIGN KEY (bar_id) REFERENCES bars (id) ON DELETE CASCADE,
                ADD CONSTRAINT uq_bar_inventory_bar_id_ingredient_id
                    UNIQUE (bar_id, ingredient_id);
        ");
    }

    public function down(): void
    {
        DB::unprepared(/** @lang PostgreSQL */ "
            ALTER TABLE bar_inventory
                DROP CONSTRAINT IF EXISTS uq_bar_inventory_bar_id_ingredient_id,
                DROP CONSTRAINT IF EXISTS fk_bar_inventory_bar_id,
                DROP COLUMN IF EXISTS bar_id,
                ADD CONSTRAINT uq_bar_inventory_ingredient_id UNIQUE (ingredient_id);
        ");
    }
};
