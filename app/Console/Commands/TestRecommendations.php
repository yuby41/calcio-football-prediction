<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\BudgetConfiguration;
use App\Services\BettingRecommendationService;

class TestRecommendations extends Command
{
    protected $signature = 'test:recommendations {budget_id}';
    
    protected $description = 'Test recommendations service';

    public function handle()
    {
        $budgetId = $this->argument('budget_id');
        
        try {
            $budget = BudgetConfiguration::findOrFail($budgetId);
            $this->info("Probando recomendaciones para: {$budget->name}");
            
            $service = app(BettingRecommendationService::class);
            $recommendations = $service->getRecommendationsForBudget($budget);
            
            $this->info("Recomendaciones encontradas: " . count($recommendations));
            
            foreach ($recommendations as $index => $rec) {
                $match = $rec['match'];
                $this->line("Partido {$index}: {$match->homeTeam->name} vs {$match->awayTeam->name}");
                $this->line("  Recomendaciones: " . count($rec['recommendations']));
            }

            return Command::SUCCESS;
            
        } catch (\Exception $e) {
            $this->error("Error: " . $e->getMessage());
            $this->error("Trace: " . $e->getTraceAsString());
            return Command::FAILURE;
        }
    }
}