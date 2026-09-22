<?php

namespace Database\Factories;

use App\Models\Event;
use Illuminate\Database\Eloquent\Factories\Factory;

class EventFactory extends Factory
{
    protected $model = Event::class;

    private array $teams = [
        'Real Madrid', 'Barcelona', 'Manchester City', 'Liverpool',
        'Bayern Munich', 'PSG', 'Juventus', 'AC Milan', 'Arsenal',
        'Chelsea', 'Atletico Madrid', 'Borussia Dortmund',
    ];

    private array $leagues = [
        'UEFA Champions League', 'Premier League', 'La Liga',
        'Bundesliga', 'Serie A', 'Ligue 1',
    ];

    public function definition(): array
    {
        $homeTeam = $this->faker->unique()->randomElement($this->teams);
        $awayTeam = $this->faker->randomElement(array_diff($this->teams, [$homeTeam]));

        return [
            'external_id'     => $this->faker->unique()->numerify('API-#####'),
            'sport'           => 'soccer',
            'league'          => $this->faker->randomElement($this->leagues),
            'home_team'       => $homeTeam,
            'away_team'       => $awayTeam,
            'home_score'      => 0,
            'away_score'      => 0,
            'status'          => 'scheduled',
            'elapsed_minutes' => null,
            'starts_at'       => now()->addHours($this->faker->numberBetween(1, 48)),
        ];
    }

    /** Estado: partido en vivo con marcador aleatorio */
    public function live(): static
    {
        return $this->state(fn (array $attributes) => [
            'home_score'      => $this->faker->numberBetween(0, 4),
            'away_score'      => $this->faker->numberBetween(0, 4),
            'status'          => 'live',
            'elapsed_minutes' => $this->faker->numberBetween(10, 85),
            'starts_at'       => now()->subMinutes($this->faker->numberBetween(10, 85)),
        ]);
    }

    /** Estado: partido finalizado */
    public function finished(): static
    {
        return $this->state(fn (array $attributes) => [
            'home_score' => $this->faker->numberBetween(0, 5),
            'away_score' => $this->faker->numberBetween(0, 5),
            'status'     => 'finished',
            'starts_at'  => now()->subHours($this->faker->numberBetween(2, 24)),
        ]);
    }
}

