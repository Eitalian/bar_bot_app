<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(/** @lang PostgreSQL */ "
            CREATE TYPE user_role_type AS ENUM ('guest', 'bartender', 'owner');
            ALTER TABLE users ADD COLUMN role user_role_type NOT NULL DEFAULT 'guest';
        ");
    }

    public function down(): void
    {
        DB::unprepared(/** @lang PostgreSQL */ "
            ALTER TABLE users DROP COLUMN role;
            DROP TYPE IF EXISTS user_role_type;
        ");
    }
};
