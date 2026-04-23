<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recipe_ingredients', function (Blueprint $table) {
            $table->id();
            $table->string('recipe_id');
            $table->foreign('recipe_id')->references('id')->on('recipes')->cascadeOnDelete();
            $table->string('ingredient_id');
            $table->foreign('ingredient_id')->references('id')->on('ingredients')->cascadeOnDelete();
            $table->string('amount')->nullable();
            $table->string('unit')->nullable();
            $table->string('note')->nullable();
            $table->integer('sort_order')->default(0);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recipe_ingredients');
    }
};
