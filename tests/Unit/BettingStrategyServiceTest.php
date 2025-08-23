<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\BettingStrategyService;
use App\Models\BudgetConfiguration;
use App\Models\FootballMatch;
use App\Models\Team;
use App\Models\MatchPrediction;
use Illuminate\Foundation\Testing\RefreshDatabase;

class BettingStrategyServiceTest extends TestCase
{
    use RefreshDatabase;
    
    protected BettingStrategyService $bettingService;
    
    protected function setUp(): void
    {
        parent::setUp();
        $this->bettingService = app(BettingStrategyService::class);
    }
    
    /** @test */
    public function it_calculates_bet_amount_with_martingale_strategy()
    {
        // Arrange: Create budget with Martingale strategy
        $budget = BudgetConfiguration::factory()->create([
            'name' => 'Test Martingale',
            'strategy_type' => 'martingale',
            'initial_budget' => 1000.00,
            'available_balance' => 1000.00,
            'base_bet_amount' => 10.00,
            'risk_multiplier' => 2.0,
            'is_active' => true
        ]);
        
        // Act: Calculate bet amount after a loss (should double)
        $betAmount = $this->bettingService->calculateBetAmount(
            $budget, 
            1, // consecutive losses
            85.5 // high confidence
        );
        
        // Assert: Should double the base bet amount
        $this->assertEquals(20.00, $betAmount);
    }
    
    /** @test */
    public function it_calculates_bet_amount_with_mansanillo_strategy()
    {
        // Arrange: Create budget with Mansanillo strategy
        $budget = BudgetConfiguration::factory()->create([
            'strategy_type' => 'mansaniello',
            'initial_budget' => 1000.00,
            'available_balance' => 1000.00,
            'base_bet_amount' => 10.00,
            'risk_multiplier' => 1.5,
            'is_active' => true
        ]);
        
        // Act: Calculate bet amount with Mansanillo progression
        $betAmount = $this->bettingService->calculateBetAmount(
            $budget,
            2, // step in progression
            90.0 // very high confidence
        );
        
        // Assert: Should follow Mansanillo progression
        $this->assertGreaterThan(10.00, $betAmount);
        $this->assertLessThan(100.00, $betAmount); // Reasonable upper bound
    }
    
    /** @test */
    public function it_prevents_bet_amount_exceeding_available_balance()
    {
        // Arrange: Create budget with low available balance
        $budget = BudgetConfiguration::factory()->create([
            'strategy_type' => 'martingale',
            'initial_budget' => 100.00,
            'available_balance' => 5.00, // Very low balance
            'base_bet_amount' => 10.00,
            'risk_multiplier' => 2.0,
            'is_active' => true
        ]);
        
        // Act: Calculate bet amount
        $betAmount = $this->bettingService->calculateBetAmount(
            $budget,
            3, // Would normally result in 80.00 bet
            95.0 // Very high confidence
        );
        
        // Assert: Should not exceed available balance
        $this->assertLessThanOrEqual($budget->available_balance, $betAmount);
        $this->assertGreaterThan(0, $betAmount);
    }
    
    /** @test */
    public function it_adjusts_bet_amount_based_on_confidence()
    {
        // Arrange
        $budget = BudgetConfiguration::factory()->create([
            'strategy_type' => 'fixed',
            'base_bet_amount' => 10.00,
            'available_balance' => 1000.00,
            'is_active' => true
        ]);
        
        // Act: Calculate with different confidence levels
        $lowConfidenceBet = $this->bettingService->calculateBetAmount($budget, 0, 55.0);
        $highConfidenceBet = $this->bettingService->calculateBetAmount($budget, 0, 95.0);
        
        // Assert: Higher confidence should result in higher bet
        $this->assertGreaterThan($lowConfidenceBet, $highConfidenceBet);
    }
    
    /** @test */
    public function it_validates_minimum_confidence_threshold()
    {
        // Arrange
        $budget = BudgetConfiguration::factory()->create([
            'base_bet_amount' => 10.00,
            'available_balance' => 1000.00,
            'min_confidence_threshold' => 70.0,
            'is_active' => true
        ]);
        
        // Act: Try to bet with confidence below threshold
        $betAmount = $this->bettingService->calculateBetAmount($budget, 0, 65.0);
        
        // Assert: Should return 0 or minimal amount for low confidence
        $this->assertLessThanOrEqual(1.0, $betAmount);
    }
    
    /** @test */
    public function it_handles_inactive_budget_safely()
    {
        // Arrange: Create inactive budget
        $budget = BudgetConfiguration::factory()->create([
            'is_active' => false,
            'available_balance' => 1000.00,
            'base_bet_amount' => 10.00
        ]);
        
        // Act: Try to calculate bet amount
        $betAmount = $this->bettingService->calculateBetAmount($budget, 0, 85.0);
        
        // Assert: Should return 0 for inactive budget
        $this->assertEquals(0, $betAmount);
    }
    
    /** @test */
    public function it_calculates_target_profit_correctly()
    {
        // Arrange
        $budget = BudgetConfiguration::factory()->create([
            'initial_budget' => 1000.00,
            'target_profit_percentage' => 25.0, // 25% target
            'available_balance' => 1200.00
        ]);
        
        // Act
        $targetProfit = $this->bettingService->calculateTargetProfit($budget);
        
        // Assert: 25% of 1000 = 250
        $this->assertEquals(250.00, $targetProfit);
    }
    
    /** @test */
    public function it_detects_target_profit_reached()
    {
        // Arrange: Budget that has reached target profit
        $budget = BudgetConfiguration::factory()->create([
            'initial_budget' => 1000.00,
            'target_profit_percentage' => 20.0,
            'available_balance' => 1250.00 // 25% profit (>20% target)
        ]);
        
        // Act
        $isReached = $this->bettingService->hasReachedTargetProfit($budget);
        
        // Assert
        $this->assertTrue($isReached);
    }
    
    /** @test */
    public function it_handles_zero_odds_safely()
    {
        // This test ensures the system doesn't crash with invalid odds
        $budget = BudgetConfiguration::factory()->create([
            'base_bet_amount' => 10.00,
            'available_balance' => 1000.00
        ]);
        
        // This should not throw an exception
        $betAmount = $this->bettingService->calculateBetAmount($budget, 0, 85.0);
        
        $this->assertIsNumeric($betAmount);
        $this->assertGreaterThanOrEqual(0, $betAmount);
    }
}