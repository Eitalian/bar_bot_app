<?php

namespace Database\Factories;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class UserFactory extends Factory
{
    protected $model = User::class;

    public function definition(): array
    {
        return [
            'telegram_id' => $this->faker->unique()->numberBetween(100000, 999999999),
            'first_name'  => $this->faker->firstName(),
            'username'    => $this->faker->optional()->userName(),
            'role'        => UserRole::Guest,
        ];
    }

    public function bartender(): static
    {
        return $this->state(['role' => UserRole::Bartender]);
    }

    public function owner(): static
    {
        return $this->state(['role' => UserRole::Owner]);
    }
}
