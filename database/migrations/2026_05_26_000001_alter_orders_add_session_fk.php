<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(/** @lang PostgreSQL */ "
            -- Phase 3 пересоздала bar_sessions (SMALLINT PK) через DROP CASCADE,
            -- который снёс fk_orders_session_id из заглушки orders.
            -- Восстанавливаем FK; тип колонки приводим к SMALLINT для совместимости.
            ALTER TABLE orders
                ALTER COLUMN session_id TYPE SMALLINT USING session_id::SMALLINT;

            ALTER TABLE orders
                ADD CONSTRAINT fk_orders_session_id
                    FOREIGN KEY (session_id) REFERENCES bar_sessions (id) ON DELETE CASCADE;

            CREATE INDEX idx_orders_session_id ON orders (session_id);
            CREATE INDEX idx_orders_user_id    ON orders (user_id);
        ");
    }

    public function down(): void
    {
        DB::unprepared(/** @lang PostgreSQL */ "
            DROP INDEX IF EXISTS idx_orders_user_id;
            DROP INDEX IF EXISTS idx_orders_session_id;
            ALTER TABLE orders DROP CONSTRAINT IF EXISTS fk_orders_session_id;
            ALTER TABLE orders ALTER COLUMN session_id TYPE BIGINT;
        ");
    }
};
