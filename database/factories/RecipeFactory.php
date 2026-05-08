<?php

namespace Database\Factories;

use App\Models\Recipe;
use Illuminate\Database\Eloquent\Factories\Factory;

final class RecipeFactory extends Factory
{
    protected $model = Recipe::class;

    public function definition(): array
    {
        return [
            'id'           => $this->faker->uuid(),
            'name_ru'      => $this->faker->words(2, true),
            'name_en'      => $this->faker->words(2, true),
            'description'  => $this->faker->optional()->sentence(),
            'instructions' => $this->faker->optional()->paragraph(),
            'glass'        => $this->faker->randomElement([
                'rocks', 'highball', 'cocktail', 'coupe', 'shot', 'margarita',
            ]),
            'abv'          => $this->faker->randomFloat(1, 0, 100),
            'volume'       => $this->faker->numberBetween(30, 500),
            'icon'         => null,
            'photo'        => null,
            'taste_tags'   => null,
        ];
    }

    public function nonAlcoholic(): static
    {
        return $this->state(['abv' => 0.0]);
    }
}
