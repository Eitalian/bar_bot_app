<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class UserFactory extends Factory
{
    protected $model = User::class;

    public function definition(): array
    {
        return [
            'telegram_id' => $this->faker->unique()->numberBetween(100000, 999999999),
            'first_name' => $this->faker->firstName(),
            'username' => $this->faker->optional()->userName(),
        ];
    }
}
