<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\Parlay;
use App\Models\Pick;
use Illuminate\Database\Eloquent\Factories\Factory;

class PickFactory extends Factory
{
    protected $model = Pick::class;

    public function definition(): array
    {
        $pickType  = $this->faker->randomElement(['total_goals', 'home_score', 'away_score', 'result']);
        $condition = $this->getConditionForType($pickType);
        $target    = $this->getTargetForType($pickType, $condition);

        return [
            'parlay_id'           => Parlay::factory(),
            'event_id'            => Event::factory(),
            'pick_type'           => $pickType,
            'condition'           => $condition,
            'target_value'        => $target,
            'current_progress'    => 0.00,
            'progress_percentage' => 0.00,
            'status'              => 'pending',
            'odd'                 => $this->faker->randomFloat(2, 1.20, 3.50),
        ];
    }

    private function getConditionForType(string $type): string
    {
        return match ($type) {
            'total_goals', 'home_score', 'away_score' => $this->faker->randomElement(['over', 'under']),
            'result'                                   => $this->faker->randomElement(['home', 'away', 'draw']),
            default                                    => 'over',
        };
    }

    private function getTargetForType(string $type, string $condition): float
    {
        return match ($type) {
            'total_goals' => $this->faker->randomElement([1.5, 2.5, 3.5, 4.5]),
            'home_score', 'away_score' => $this->faker->randomElement([0.5, 1.5, 2.5]),
            'result'      => 1.0,
            default       => 1.5,
        };
    }
}

