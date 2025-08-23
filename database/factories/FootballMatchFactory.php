<?php

namespace Database\Factories;

use App\Models\FootballMatch;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;
use Carbon\Carbon;

class FootballMatchFactory extends Factory
{
    protected $model = FootballMatch::class;

    public function definition(): array
    {
        return [
            'external_id' => $this->faker->unique()->numberBetween(100000, 999999),
            'home_team_id' => Team::factory(),
            'away_team_id' => Team::factory(),
            'match_date' => $this->faker->dateTimeBetween('now', '+30 days'),
            'status' => $this->faker->randomElement(['scheduled', 'live', 'finished']),
            'league' => $this->faker->randomElement(['PL', 'PD', 'BL1', 'SA', 'FL1']),
            'home_goals' => null,
            'away_goals' => null,
            'season' => '2024',
            'round' => $this->faker->numberBetween(1, 38),
            'venue' => $this->faker->company() . ' Stadium',
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    public function scheduled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'scheduled',
            'home_goals' => null,
            'away_goals' => null,
        ]);
    }

    public function finished(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'finished',
            'home_goals' => $this->faker->numberBetween(0, 5),
            'away_goals' => $this->faker->numberBetween(0, 5),
        ]);
    }

    public function live(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'live',
            'home_goals' => $this->faker->numberBetween(0, 3),
            'away_goals' => $this->faker->numberBetween(0, 3),
        ]);
    }
}