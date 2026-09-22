<?php

namespace Database\Factories;

use App\Models\Parlay;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ParlayFactory extends Factory
{
    protected $model = Parlay::class;

    private array $names = [
        'Champions Tuesday', 'Weekend Special', 'Safe Bets',
        'La Liga Night', 'Premier League Triple', 'Goal Fest',
        'Double Chance', 'Big Saturday', 'El Clásico Special',
    ];

    public function definition(): array
    {
        $stake = $this->faker->randomFloat(2, 5, 200);

        return [
            'user_id'          => User::factory(),
            'name'             => $this->faker->randomElement($this->names),
            'stake'            => $stake,
            'potential_payout' => round($stake * $this->faker->randomFloat(2, 1.5, 8.0), 2),
            'status'           => 'pending',
            'settled_at'       => null,
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'active',
        ]);
    }

    public function won(): static
    {
        return $this->state(fn (array $attributes) => [
            'status'     => 'won',
            'settled_at' => now(),
        ]);
    }

    public function lost(): static
    {
        return $this->state(fn (array $attributes) => [
            'status'     => 'lost',
            'settled_at' => now(),
        ]);
    }
}

