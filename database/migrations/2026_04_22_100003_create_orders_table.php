<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(/** @lang PostgreSQL */ "
            CREATE TYPE order_status_type AS ENUM ('pending', 'accepted', 'cancelled');

            CREATE TABLE orders (
                id         BIGINT            GENERATED ALWAYS AS IDENTITY,
                session_id BIGINT            NOT NULL,
                user_id    BIGINT            NOT NULL,
                recipe_id  TEXT              NOT NULL,
                quantity   SMALLINT          NULL,
                status     order_status_type NOT NULL DEFAULT 'pending',
                created_at TIMESTAMPTZ       NOT NULL DEFAULT NOW(),
                updated_at TIMESTAMPTZ       NOT NULL DEFAULT NOW(),
                CONSTRAINT pk_orders PRIMARY KEY (id),
                CONSTRAINT fk_orders_session_id
                    FOREIGN KEY (session_id) REFERENCES bar_sessions (id) ON DELETE CASCADE,
                CONSTRAINT fk_orders_user_id
                    FOREIGN KEY (user_id)    REFERENCES users        (id) ON DELETE CASCADE,
                CONSTRAINT fk_orders_recipe_id
                    FOREIGN KEY (recipe_id)  REFERENCES recipes      (id) ON DELETE CASCADE
            );
        ");
    }

    public function down(): void
    {
        DB::unprepared(/** @lang PostgreSQL */ "
            DROP TABLE IF EXISTS orders;
            DROP TYPE  IF EXISTS order_status_type;
        ");
    }
};
