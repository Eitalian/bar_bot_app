<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(/** @lang PostgreSQL */ "
            CREATE TABLE bar_inventory (
                id            BIGINT       GENERATED ALWAYS AS IDENTITY,
                user_id       BIGINT       NOT NULL,
                ingredient_id UUID         NOT NULL,
                quantity      NUMERIC(8,2) NULL,
                unit          VARCHAR(20)  NULL,
                created_at    TIMESTAMPTZ  NOT NULL DEFAULT NOW(),
                updated_at    TIMESTAMPTZ  NOT NULL DEFAULT NOW(),
                CONSTRAINT pk_bar_inventory PRIMARY KEY (id),
                CONSTRAINT fk_bar_inventory_user_id
                    FOREIGN KEY (user_id)       REFERENCES users       (id) ON DELETE CASCADE,
                CONSTRAINT fk_bar_inventory_ingredient_id
                    FOREIGN KEY (ingredient_id) REFERENCES ingredients (id) ON DELETE CASCADE,
                CONSTRAINT uq_bar_inventory_user_id_ingredient_id
                    UNIQUE (user_id, ingredient_id),
                CONSTRAINT chk_bar_inventory_quantity
                    CHECK (quantity IS NULL OR quantity >= 0)
            );
        ");
    }

    public function down(): void
    {
        DB::unprepared(/** @lang PostgreSQL */ "
            DROP TABLE IF EXISTS bar_inventory;
        ");
    }
};
