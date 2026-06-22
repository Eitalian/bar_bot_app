<?php

namespace Database\Factories;

use App\Models\Rating;
use App\Models\Recipe;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Rating>
 */
final class RatingFactory extends Factory
{
    protected $model = Rating::class;

    public function definition(): array
    {
        return [
            'user_id'   => User::factory(),
            'recipe_id' => Recipe::factory(),
            'score'     => $this->faker->numberBetween(1, 5),
        ];
    }
}
