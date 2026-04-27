<?php

namespace Database\Factories;

use App\Models\Ingredient;
use App\Models\Inventory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class InventoryFactory extends Factory
{
    protected $model = Inventory::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'ingredient_id' => Ingredient::factory(),
            'quantity' => $this->faker->optional()->randomFloat(2, 0.1, 1000),
            'unit' => $this->faker->optional()->randomElement(['мл', 'г', 'шт', 'бутылка']),
        ];
    }
}
