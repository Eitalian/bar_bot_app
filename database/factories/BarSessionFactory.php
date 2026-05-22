<?php

namespace Database\Factories;

use App\Models\Bar;
use App\Models\BarSession;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BarSession>
 */
final class BarSessionFactory extends Factory
{
    protected $model = BarSession::class;

    public function definition(): array
    {
        return [
            'bar_id'     => app(Bar::class)->id,
            'started_at' => now(),
            'ended_at'   => null,
        ];
    }

    public function closed(): self
    {
        return $this->state(fn () => ['ended_at' => now()]);
    }
}
