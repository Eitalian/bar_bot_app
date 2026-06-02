<?php

namespace Database\Factories;

use App\Models\Ingredient;
use App\Models\Inventory;
use Illuminate\Database\Eloquent\Factories\Factory;

final class InventoryFactory extends Factory
{
    protected $model = Inventory::class;

    public function definition(): array
    {
        return [
            'bar_id' => 1,
            'ingredient_id' => Ingredient::factory(),
            'quantity' => $this->faker->optional()->randomFloat(2, 0.1, 1000),
            'unit' => $this->faker->optional()->randomElement(['мл', 'г', 'шт', 'бутылка']),
        ];
    }
}
