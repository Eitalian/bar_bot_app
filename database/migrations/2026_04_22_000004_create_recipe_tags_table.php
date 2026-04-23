<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recipe_tags', function (Blueprint $table) {
            $table->id();
            $table->string('recipe_id');
            $table->foreign('recipe_id')->references('id')->on('recipes')->cascadeOnDelete();
            $table->string('tag');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recipe_tags');
    }
};
