<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class CreateTestPredictions extends Command
{
    protected $signature = 'predictions:create-test {--count=5}';
    
    protected $description = 'Create test matches with predictions for budget recommendations';

    public function handle()
    {
        $count = $this->option('count');
        
        try {
            $this->info("Creando {$count} partidos con predicciones de prueba...");
            
            // Crear equipos si no existen
            $this->createTestTeams();
            
            // Crear partidos próximos con predicciones
            $this->createMatchesWithPredictions($count);
            
            $this->info("\n✅ Partidos con predicciones creados exitosamente!");
            $this->line("Visita: http://localhost:8000/budget/1/recommendations");

            return Command::SUCCESS;
            
        } catch (\Exception $e) {
            $this->error("Error: " . $e->getMessage());
            return Command::FAILURE;
        }
    }

    private function createTestTeams()
    {
        $teams = [
            ['name' => 'Arsenal', 'external_id' => 'pred_arsenal', 'short_name' => 'Arsenal'],
            ['name' => 'Chelsea', 'external_id' => 'pred_chelsea', 'short_name' => 'Chelsea'],
            ['name' => 'Liverpool', 'external_id' => 'pred_liverpool', 'short_name' => 'Liverpool'],
            ['name' => 'Manchester City', 'external_id' => 'pred_mancity', 'short_name' => 'Man City'],
            ['name' => 'Tottenham', 'external_id' => 'pred_tottenham', 'short_name' => 'Spurs'],
            ['name' => 'Manchester United', 'external_id' => 'pred_manunited', 'short_name' => 'Man United'],
        ];

        foreach ($teams as $teamData) {
            DB::table('teams')->insertOrIgnore([
                'name' => $teamData['name'],
                'external_id' => $teamData['external_id'],
                'short_name' => $teamData['short_name'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function createMatchesWithPredictions($count)
    {
        $teams = DB::table('teams')->where('external_id', 'like', 'pred_%')->get();
        if ($teams->count() < 4) {
            throw new \Exception("No hay suficientes equipos disponibles");
        }

        for ($i = 0; $i < $count; $i++) {
            $homeTeam = $teams[rand(0, $teams->count() - 1)];
            $awayTeam = $teams[rand(0, $teams->count() - 1)];
            
            // Asegurar equipos diferentes
            while ($awayTeam->id == $homeTeam->id) {
                $awayTeam = $teams[rand(0, $teams->count() - 1)];
            }
            
            // Crear partido próximo
            $matchDate = Carbon::now()->addDays(rand(1, 7))->setHour(rand(15, 21))->setMinute(rand(0, 1) * 30);
            
            $matchId = DB::table('matches')->insertGetId([
                'external_id' => 'pred_match_' . $i . '_' . time(),
                'home_team_id' => $homeTeam->id,
                'away_team_id' => $awayTeam->id,
                'match_date' => $matchDate->format('Y-m-d H:i:s'),
                'status' => 'scheduled',
                'league' => 'Premier League',
                'season' => '2024-25',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Crear predicción realista
            $this->createPredictionForMatch($matchId, $homeTeam, $awayTeam);
            
            $this->line("✓ Partido: {$homeTeam->name} vs {$awayTeam->name} - {$matchDate->format('d/m/Y H:i')}");
        }
    }

    private function createPredictionForMatch($matchId, $homeTeam, $awayTeam)
    {
        // Generar probabilidades realistas
        // Ventaja local típica
        $homeAdvantage = rand(10, 25) / 100; // 10-25% ventaja local
        
        // Probabilidades base aleatorias pero realistas
        $baseHomeWin = rand(30, 50) / 100;
        $baseDraw = rand(20, 35) / 100;
        $baseAwayWin = 1 - $baseHomeWin - $baseDraw;
        
        // Aplicar ventaja local
        $homeWinProb = min($baseHomeWin + $homeAdvantage, 0.8);
        $drawProb = $baseDraw;
        $awayWinProb = max(1 - $homeWinProb - $drawProb, 0.1);
        
        // Normalizar para que sumen 1
        $total = $homeWinProb + $drawProb + $awayWinProb;
        $homeWinProb /= $total;
        $drawProb /= $total;
        $awayWinProb /= $total;
        
        // Predicciones de goles
        $homeGoals = rand(80, 280) / 100; // 0.8 - 2.8 goles
        $awayGoals = rand(60, 220) / 100; // 0.6 - 2.2 goles
        $totalGoals = $homeGoals + $awayGoals;
        
        // Probabilidades de goles
        $over25Prob = $totalGoals > 2.5 ? rand(55, 85) / 100 : rand(15, 45) / 100;
        $under25Prob = 1 - $over25Prob;
        
        // Both teams score
        $bothTeamsScoreProb = ($homeGoals > 0.8 && $awayGoals > 0.8) ? rand(60, 80) / 100 : rand(25, 55) / 100;
        
        // Outcome predicho (el más probable)
        $predictedOutcome = 'draw';
        if ($homeWinProb > $drawProb && $homeWinProb > $awayWinProb) {
            $predictedOutcome = 'home_win';
        } elseif ($awayWinProb > $drawProb && $awayWinProb > $homeWinProb) {
            $predictedOutcome = 'away_win';
        }
        
        // Confianza general (basada en la diferencia entre probabilidades)
        $maxProb = max($homeWinProb, $drawProb, $awayWinProb);
        $confidenceScore = $maxProb + (rand(0, 20) / 100); // Añadir algo de variación
        $confidenceScore = min($confidenceScore, 0.95);
        
        DB::table('match_predictions')->insert([
            'match_id' => $matchId,
            'home_goals_prediction' => round($homeGoals, 2),
            'away_goals_prediction' => round($awayGoals, 2),
            'home_win_probability' => round($homeWinProb, 4),
            'draw_probability' => round($drawProb, 4),
            'away_win_probability' => round($awayWinProb, 4),
            'both_teams_score_probability' => round($bothTeamsScoreProb, 4),
            'over_2_5_probability' => round($over25Prob, 4),
            'under_2_5_probability' => round($under25Prob, 4),
            'predicted_outcome' => $predictedOutcome,
            'confidence_score' => round($confidenceScore, 4),
            'model_version' => 'test_v1.0',
            'features_used' => json_encode(['team_form', 'head_to_head', 'home_advantage']),
            'predicted_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}