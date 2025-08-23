<?php

namespace Database\Factories;

use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

class TeamFactory extends Factory
{
    protected $model = Team::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->word() . ' FC',
            'external_id' => $this->faker->unique()->numberBetween(1000, 9999),
            'country' => $this->faker->countryCode(),
            'league' => $this->faker->randomElement(['PL', 'PD', 'BL1', 'SA', 'FL1']),
            'logo_url' => $this->faker->imageUrl(100, 100, 'sports'),
            'founded_year' => $this->faker->numberBetween(1900, 2020),
            'venue' => $this->faker->company() . ' Stadium',
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}