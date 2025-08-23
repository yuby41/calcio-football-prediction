<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\PredictionService;
use App\Models\FootballMatch;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;

class PredictionServiceTest extends TestCase
{
    use RefreshDatabase;
    
    protected PredictionService $predictionService;
    
    protected function setUp(): void
    {
        parent::setUp();
        $this->predictionService = app(PredictionService::class);
    }
    
    /** @test */
    public function it_can_create_fallback_prediction_when_ml_fails()
    {
        // Arrange: Create test teams and match
        $homeTeam = Team::factory()->create([
            'name' => 'Test Home Team',
            'external_id' => 1001
        ]);
        
        $awayTeam = Team::factory()->create([
            'name' => 'Test Away Team', 
            'external_id' => 1002
        ]);
        
        $match = FootballMatch::factory()->create([
            'home_team_id' => $homeTeam->id,
            'away_team_id' => $awayTeam->id,
            'status' => 'scheduled'
        ]);
        
        // Act: Call the prediction service
        $prediction = $this->predictionService->createFallbackPrediction($match);
        
        // Assert: Verify prediction structure
        $this->assertIsArray($prediction);
        $this->assertArrayHasKey('predicted_outcome', $prediction);
        $this->assertArrayHasKey('confidence_score', $prediction);
        $this->assertArrayHasKey('both_teams_score_probability', $prediction);
        $this->assertArrayHasKey('over_2_5_probability', $prediction);
        
        // Assert: Verify prediction values are within valid ranges
        $this->assertContains($prediction['predicted_outcome'], ['home_win', 'away_win', 'draw']);
        $this->assertGreaterThanOrEqual(0, $prediction['confidence_score']);
        $this->assertLessThanOrEqual(100, $prediction['confidence_score']);
        $this->assertGreaterThanOrEqual(0, $prediction['both_teams_score_probability']);
        $this->assertLessThanOrEqual(1, $prediction['both_teams_score_probability']);
    }
    
    /** @test */
    public function it_sanitizes_team_ids_for_ml_prediction()
    {
        // Arrange: Create match with potentially unsafe IDs
        $homeTeam = Team::factory()->create(['external_id' => 123]);
        $awayTeam = Team::factory()->create(['external_id' => 456]);
        
        $match = FootballMatch::factory()->create([
            'home_team_id' => $homeTeam->id,
            'away_team_id' => $awayTeam->id
        ]);
        
        // Act & Assert: This should not throw any exceptions
        // The method should sanitize IDs to integers internally
        $result = $this->predictionService->predictWithEnhancedML($match, '/fake/ml/path');
        
        // The method should handle the fake path gracefully and return fallback
        $this->assertIsArray($result);
    }
    
    /** @test */
    public function it_handles_missing_team_statistics_gracefully()
    {
        // Arrange: Create teams without statistics
        $homeTeam = Team::factory()->create();
        $awayTeam = Team::factory()->create();
        
        $match = FootballMatch::factory()->create([
            'home_team_id' => $homeTeam->id,
            'away_team_id' => $awayTeam->id
        ]);
        
        // Act: Generate prediction without team stats
        $prediction = $this->predictionService->createFallbackPrediction($match);
        
        // Assert: Should still generate valid prediction
        $this->assertIsArray($prediction);
        $this->assertArrayHasKey('predicted_outcome', $prediction);
        
        // Should use default confidence when stats are missing
        $this->assertGreaterThanOrEqual(40, $prediction['confidence_score']);
        $this->assertLessThanOrEqual(60, $prediction['confidence_score']);
    }
    
    /** @test */
    public function it_validates_prediction_data_structure()
    {
        $homeTeam = Team::factory()->create();
        $awayTeam = Team::factory()->create();
        
        $match = FootballMatch::factory()->create([
            'home_team_id' => $homeTeam->id,
            'away_team_id' => $awayTeam->id
        ]);
        
        $prediction = $this->predictionService->createFallbackPrediction($match);
        
        // Test all required fields exist
        $requiredFields = [
            'predicted_outcome',
            'confidence_score', 
            'both_teams_score_probability',
            'over_2_5_probability',
            'first_half_over_0_5_probability'
        ];
        
        foreach ($requiredFields as $field) {
            $this->assertArrayHasKey($field, $prediction, "Missing required field: {$field}");
        }
        
        // Test data types
        $this->assertIsString($prediction['predicted_outcome']);
        $this->assertIsNumeric($prediction['confidence_score']);
        $this->assertIsNumeric($prediction['both_teams_score_probability']);
        $this->assertIsNumeric($prediction['over_2_5_probability']);
    }
    
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}