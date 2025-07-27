<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use PDO;

class UpdateStatisticsSQL extends Command
{
    protected $signature = 'statistics:update-sql';
    
    protected $description = 'Update prediction statistics using direct SQL (bypasses mbstring issue)';

    public function handle()
    {
        $this->info('Updating prediction statistics with SQL...');
        
        try {
            $pdo = new PDO('mysql:host=192.168.56.56;dbname=calcio', 'homestead', 'secret');
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            // Update match outcome statistics
            $this->updateMatchOutcome($pdo);
            
            // Update both teams score statistics  
            $this->updateBothTeamsScore($pdo);
            
            // Update over/under statistics
            $this->updateOverUnder($pdo);
            
            $this->info('✓ Statistics updated successfully!');
            $this->showCurrentStats($pdo);
            
        } catch (\Exception $e) {
            $this->error('Failed to update statistics: ' . $e->getMessage());
            return Command::FAILURE;
        }
        
        return Command::SUCCESS;
    }
    
    private function updateMatchOutcome(PDO $pdo)
    {
        // Get match outcome statistics
        $stmt = $pdo->query("
            SELECT 
                COUNT(*) as total_predictions,
                SUM(CASE WHEN mp.is_correct = 1 THEN 1 ELSE 0 END) as correct_predictions,
                ROUND(AVG(CASE WHEN mp.is_correct IS NOT NULL THEN mp.is_correct END) * 100, 2) as accuracy_percentage
            FROM match_predictions mp
            JOIN matches m ON mp.match_id = m.id
            WHERE m.status = 'finished' 
                AND mp.is_correct IS NOT NULL
        ");
        
        $result = $stmt->fetch();
        
        // Update the statistics table
        $updateStmt = $pdo->prepare("
            UPDATE prediction_statistics 
            SET 
                total_predictions = ?,
                correct_predictions = ?,
                accuracy_percentage = ?,
                last_updated = CURDATE(),
                updated_at = NOW()
            WHERE prediction_type = 'match_outcome'
        ");
        
        $updateStmt->execute([
            $result['total_predictions'],
            $result['correct_predictions'], 
            $result['accuracy_percentage']
        ]);
        
        $this->line("  Match outcome: {$result['correct_predictions']}/{$result['total_predictions']} ({$result['accuracy_percentage']}%)");
    }
    
    private function updateBothTeamsScore(PDO $pdo)
    {
        // Get both teams score statistics
        $stmt = $pdo->query("
            SELECT 
                COUNT(*) as total_predictions,
                SUM(CASE WHEN mp.both_teams_score_correct = 1 THEN 1 ELSE 0 END) as correct_predictions,
                ROUND(AVG(CASE WHEN mp.both_teams_score_correct IS NOT NULL THEN mp.both_teams_score_correct END) * 100, 2) as accuracy_percentage
            FROM match_predictions mp
            JOIN matches m ON mp.match_id = m.id
            WHERE m.status = 'finished' 
                AND mp.both_teams_score_correct IS NOT NULL
        ");
        
        $result = $stmt->fetch();
        
        // Update the statistics table
        $updateStmt = $pdo->prepare("
            UPDATE prediction_statistics 
            SET 
                total_predictions = ?,
                correct_predictions = ?,
                accuracy_percentage = ?,
                last_updated = CURDATE(),
                updated_at = NOW()
            WHERE prediction_type = 'both_teams_score'
        ");
        
        $updateStmt->execute([
            $result['total_predictions'],
            $result['correct_predictions'], 
            $result['accuracy_percentage']
        ]);
        
        $this->line("  Both teams score: {$result['correct_predictions']}/{$result['total_predictions']} ({$result['accuracy_percentage']}%)");
    }
    
    private function updateOverUnder(PDO $pdo)
    {
        // Get over/under statistics
        $stmt = $pdo->query("
            SELECT 
                COUNT(*) as total_predictions,
                SUM(CASE WHEN mp.over_under_correct = 1 THEN 1 ELSE 0 END) as correct_predictions,
                ROUND(AVG(CASE WHEN mp.over_under_correct IS NOT NULL THEN mp.over_under_correct END) * 100, 2) as accuracy_percentage
            FROM match_predictions mp
            JOIN matches m ON mp.match_id = m.id
            WHERE m.status = 'finished' 
                AND mp.over_under_correct IS NOT NULL
        ");
        
        $result = $stmt->fetch();
        
        // Update the statistics table
        $updateStmt = $pdo->prepare("
            UPDATE prediction_statistics 
            SET 
                total_predictions = ?,
                correct_predictions = ?,
                accuracy_percentage = ?,
                last_updated = CURDATE(),
                updated_at = NOW()
            WHERE prediction_type = 'over_under_2_5'
        ");
        
        $updateStmt->execute([
            $result['total_predictions'],
            $result['correct_predictions'], 
            $result['accuracy_percentage']
        ]);
        
        $this->line("  Over/Under 2.5: {$result['correct_predictions']}/{$result['total_predictions']} ({$result['accuracy_percentage']}%)");
    }
    
    private function showCurrentStats(PDO $pdo)
    {
        $this->info('Current statistics:');
        
        $stmt = $pdo->query("
            SELECT 
                prediction_type,
                total_predictions,
                correct_predictions,
                accuracy_percentage
            FROM prediction_statistics 
            ORDER BY prediction_type
        ");
        
        while ($row = $stmt->fetch()) {
            $this->line("  {$row['prediction_type']}: {$row['correct_predictions']}/{$row['total_predictions']} ({$row['accuracy_percentage']}%)");
        }
    }
}