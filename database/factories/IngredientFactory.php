<?php

namespace Database\Factories;

use App\Models\Ingredient;
use Illuminate\Database\Eloquent\Factories\Factory;

class IngredientFactory extends Factory
{
    protected $model = Ingredient::class;

    public function definition(): array
    {
        return [
            'id' => $this->faker->unique()->slug(2),
            'name_ru' => $this->faker->word(),
            'name_en' => $this->faker->word(),
            'category' => $this->faker->optional()->word(),
        ];
    }
}
