<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(/** @lang PostgreSQL */ "
            CREATE EXTENSION IF NOT EXISTS pg_uuidv7;
        ");
    }

    public function down(): void
    {
        DB::unprepared(/** @lang PostgreSQL */ "
            DROP EXTENSION IF EXISTS pg_uuidv7;
        ");
    }
};
