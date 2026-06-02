<?php

namespace Database\Factories;

use App\Enums\OrderStatus;
use App\Models\BarSession;
use App\Models\Order;
use App\Models\Recipe;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
final class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition(): array
    {
        return [
            'session_id' => BarSession::factory(),
            'user_id'    => User::factory(),
            'recipe_id'  => Recipe::factory(),
            'quantity'   => null,
            'status'     => OrderStatus::Pending,
        ];
    }

    public function accepted(): self
    {
        return $this->state(fn () => [
            'status'   => OrderStatus::Accepted,
            'quantity' => 2,
        ]);
    }

    public function cancelled(): self
    {
        return $this->state(fn () => [
            'status' => OrderStatus::Cancelled,
        ]);
    }
}
