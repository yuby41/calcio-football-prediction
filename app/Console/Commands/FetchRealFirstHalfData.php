<?php

namespace App\Console\Commands;

use App\Models\FootballMatch;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FetchRealFirstHalfData extends Command
{
    protected $signature = 'matches:fetch-first-half-data {--limit=50 : Number of matches to process} {--days=30 : Number of days back to fetch}';
    protected $description = 'Fetch real first half goals data from Football API for finished matches';

    public function handle()
    {
        $limit = (int) $this->option('limit');
        $days = (int) $this->option('days');
        
        $this->info("🏈 Obteniendo datos reales de primer tiempo de la API...");
        $this->info("   Límite: {$limit} partidos");
        $this->info("   Últimos {$days} días");
        $this->line("");

        // Get finished matches without first half data
        $matches = FootballMatch::where('status', 'finished')
            ->whereNotNull('external_id')
            ->whereNull('home_goals_first_half')
            ->where('match_date', '>=', now()->subDays($days))
            ->orderBy('match_date', 'desc')
            ->limit($limit)
            ->get();

        if ($matches->isEmpty()) {
            $this->info("✅ No hay partidos sin datos de primer tiempo");
            return Command::SUCCESS;
        }

        $this->info("📊 Procesando {$matches->count()} partidos...");
        
        $processed = 0;
        $updated = 0;
        $errors = 0;
        
        $progressBar = $this->output->createProgressBar($matches->count());
        $progressBar->start();

        foreach ($matches as $match) {
            try {
                $firstHalfData = $this->fetchFirstHalfData($match->external_id);
                
                if ($firstHalfData) {
                    $match->update([
                        'home_goals_first_half' => $firstHalfData['home'],
                        'away_goals_first_half' => $firstHalfData['away']
                    ]);
                    $updated++;
                }
                
                $processed++;
                
                // Rate limiting - 10 requests per minute
                if ($processed % 10 === 0) {
                    sleep(6); // Wait 6 seconds every 10 requests
                }
                
            } catch (\Exception $e) {
                $errors++;
                Log::error('Error fetching first half data', [
                    'match_id' => $match->id,
                    'external_id' => $match->external_id,
                    'error' => $e->getMessage()
                ]);
            }
            
            $progressBar->advance();
        }
        
        $progressBar->finish();
        $this->line("");
        
        $this->info("✅ Procesamiento completado:");
        $this->line("   • Partidos procesados: {$processed}");
        $this->line("   • Partidos actualizados: {$updated}");
        $this->line("   • Errores: {$errors}");
        
        if ($updated > 0) {
            $this->info("🎯 Generando predicciones de primer tiempo con datos reales...");
            $this->call('predictions:generate-real-first-half');
        }
        
        return Command::SUCCESS;
    }
    
    private function fetchFirstHalfData(string $externalId): ?array
    {
        $apiKey = config('services.football_api.key');
        $baseUrl = config('services.football_api.base_url');
        
        if (!$apiKey || !$baseUrl) {
            throw new \Exception('API configuration missing');
        }
        
        $response = Http::withHeaders([
            'x-apisports-key' => $apiKey,
            'Accept' => 'application/json',
        ])->timeout(10)->get("{$baseUrl}/fixtures", [
            'id' => $externalId
        ]);
        
        if (!$response->successful()) {
            Log::warning('API request failed', [
                'external_id' => $externalId,
                'status' => $response->status(),
                'response' => $response->body()
            ]);
            return null;
        }
        
        $data = $response->json();
        
        if (!isset($data['response'][0]['score']['halftime'])) {
            return null;
        }
        
        $halftime = $data['response'][0]['score']['halftime'];
        
        // Verify we have valid halftime data
        if ($halftime['home'] !== null && $halftime['away'] !== null) {
            return [
                'home' => (int) $halftime['home'],
                'away' => (int) $halftime['away']
            ];
        }
        
        return null;
    }
}