<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recipes', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('name_ru');
            $table->string('name_en')->nullable();
            $table->text('description')->nullable();
            $table->text('instructions')->nullable();
            $table->string('glass')->nullable();
            $table->decimal('abv', 5, 2)->nullable();
            $table->integer('volume')->nullable();
            $table->string('icon')->nullable();
            $table->string('photo')->nullable();
            $table->json('taste_tags')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recipes');
    }
};
