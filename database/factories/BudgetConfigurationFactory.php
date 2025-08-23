<?php

namespace Database\Factories;

use App\Models\BudgetConfiguration;
use App\Constants\BettingConstants;
use Illuminate\Database\Eloquent\Factories\Factory;

class BudgetConfigurationFactory extends Factory
{
    protected $model = BudgetConfiguration::class;

    public function definition(): array
    {
        $initialBudget = $this->faker->randomFloat(2, 100, 5000);
        
        return [
            'name' => 'Test Budget ' . $this->faker->word(),
            'initial_budget' => $initialBudget,
            'available_balance' => $initialBudget * $this->faker->numberBetween(50, 100) / 100, // 50-100% of initial
            'strategy_type' => $this->faker->randomElement([
                BettingConstants::STRATEGY_FIXED,
                BettingConstants::STRATEGY_MARTINGALE,
                BettingConstants::STRATEGY_MANSANIELLO,
                BettingConstants::STRATEGY_FIBONACCI
            ]),
            'base_bet_amount' => $this->faker->randomFloat(2, 5, 50),
            'risk_multiplier' => $this->faker->randomFloat(1, 1.5, 3.0),
            'target_profit_percentage' => $this->faker->randomFloat(1, 10, 50),
            'max_daily_loss' => $this->faker->randomFloat(2, 50, 200),
            'min_confidence_threshold' => $this->faker->randomFloat(1, 60, 85),
            'is_active' => $this->faker->boolean(80), // 80% chance of being active
            'last_bet_at' => $this->faker->optional()->dateTimeThisMonth(),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    public function martingale(): static
    {
        return $this->state(fn (array $attributes) => [
            'strategy_type' => BettingConstants::STRATEGY_MARTINGALE,
            'risk_multiplier' => 2.0,
        ]);
    }

    public function mansaniello(): static
    {
        return $this->state(fn (array $attributes) => [
            'strategy_type' => BettingConstants::STRATEGY_MANSANIELLO,
            'risk_multiplier' => 1.5,
        ]);
    }

    public function fixed(): static
    {
        return $this->state(fn (array $attributes) => [
            'strategy_type' => BettingConstants::STRATEGY_FIXED,
            'risk_multiplier' => 1.0,
        ]);
    }
}