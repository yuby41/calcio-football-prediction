<?php

namespace Tests\Feature;

use App\Models\BudgetConfiguration;
use App\Models\Bet;
use App\Models\Match;
use App\Models\Team;
use App\Services\BettingStrategyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BudgetManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_budget_configuration()
    {
        $response = $this->post('/budget', [
            'name' => 'Test Budget',
            'strategy' => 'mansaniello',
            'initial_budget' => 100.00,
            'max_bet_percentage' => 5.0,
            'min_confidence' => 65.0,
            'strategy_parameters' => [
                'base_amount' => 2.00,
                'max_sequence' => 10,
            ],
        ]);

        $response->assertRedirect('/budget');
        $this->assertDatabaseHas('budget_configurations', [
            'name' => 'Test Budget',
            'strategy' => 'mansaniello',
            'initial_budget' => 100.00,
        ]);
    }

    public function test_mansaniello_bet_calculation()
    {
        $config = BudgetConfiguration::factory()->create([
            'strategy' => 'mansaniello',
            'current_budget' => 100.00,
            'strategy_parameters' => [
                'base_amount' => 2.00,
                'max_sequence' => 10,
            ],
        ]);

        $service = new BettingStrategyService();
        $betAmount = $service->calculateBetAmount($config, 70.0, 5.00);

        $this->assertGreaterThan(0, $betAmount);
        $this->assertLessThanOrEqual(5.00, $betAmount); // Max bet limit
    }

    public function test_budget_metrics_calculation()
    {
        $config = BudgetConfiguration::factory()->create([
            'initial_budget' => 100.00,
            'current_budget' => 120.00,
        ]);

        // Create some test bets
        Bet::factory()->create([
            'budget_configuration_id' => $config->id,
            'status' => 'won',
            'amount' => 10.00,
            'actual_profit' => 15.00,
        ]);

        Bet::factory()->create([
            'budget_configuration_id' => $config->id,
            'status' => 'lost',
            'amount' => 5.00,
            'actual_profit' => -5.00,
        ]);

        $config->refresh();

        $this->assertEquals(20.00, $config->getNetProfitAttribute());
        $this->assertEquals(50.0, $config->getWinRateAttribute());
        $this->assertEquals(20.0, $config->getROIAttribute());
    }

    public function test_budget_show_page_displays_correctly()
    {
        $config = BudgetConfiguration::factory()->create([
            'name' => 'Test Configuration',
            'strategy' => 'mansaniello',
        ]);

        $response = $this->get("/budget/{$config->id}");

        $response->assertOk();
        $response->assertSee('Test Configuration');
        $response->assertSee('Mansaniello');
        $response->assertSee('Budget Actual');
    }

    public function test_budget_list_page_shows_all_configurations()
    {
        BudgetConfiguration::factory()->count(3)->create();

        $response = $this->get('/budget');

        $response->assertOk();
        $response->assertSee('Gestión de Budget');
        $response->assertViewHas('budgets');
    }

    public function test_chart_data_endpoint_returns_valid_json()
    {
        $config = BudgetConfiguration::factory()->create();

        $response = $this->get("/budget/{$config->id}/chart-data");

        $response->assertOk();
        $response->assertJsonStructure([
            'dates',
            'balances',
        ]);
    }

    public function test_risk_management_limits_are_enforced()
    {
        $config = BudgetConfiguration::factory()->create([
            'current_budget' => 100.00,
            'max_bet_percentage' => 5.0, // 5% max
            'min_confidence' => 70.0,
        ]);

        $service = new BettingStrategyService();

        // Test max bet limit
        $betAmount = $service->calculateBetAmount($config, 90.0, 10.00);
        $this->assertLessThanOrEqual(5.00, $betAmount); // 5% of 100

        // Test min confidence
        $lowConfidenceBet = $service->calculateBetAmount($config, 60.0, 10.00);
        $this->assertEquals(0, $lowConfidenceBet); // Below min confidence
    }
}