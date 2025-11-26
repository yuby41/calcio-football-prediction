<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class CheckApiQuota extends Command
{
    protected $signature = 'api:check-quota';
    protected $description = 'Check API-Sports quota and remaining requests';

    public function handle(): int
    {
        $this->info('🔍 CHECKING API-SPORTS QUOTA');
        $this->newLine();

        $apiKey = config('services.football_api.key') ?: env('FOOTBALL_API_KEY');
        $baseUrl = config('services.football_api.base_url') ?: env('FOOTBALL_API_BASE_URL', 'https://v3.football.api-sports.io');

        if (!$apiKey) {
            $this->error('❌ API key not configured');
            return 1;
        }

        $this->line("API Key: " . substr($apiKey, 0, 10) . "..." . substr($apiKey, -4));
        $this->line("Base URL: {$baseUrl}");
        $this->newLine();

        try {
            // Make a simple request to check quota
            $response = Http::withHeaders([
                'x-apisports-key' => $apiKey,
                'Accept' => 'application/json'
            ])->get($baseUrl . '/status');

            if ($response->successful()) {
                $data = $response->json();
                
                if (isset($data['response'])) {
                    $status = $data['response'];
                    
                    $this->info('✅ API CONNECTION SUCCESSFUL');
                    $this->newLine();
                    
                    $this->table(['Metric', 'Value'], [
                        ['Account Type', $status['subscription']['plan'] ?? 'Unknown'],
                        ['Total Requests', number_format($status['requests']['limit_day'] ?? 0)],
                        ['Used Today', number_format($status['requests']['current'] ?? 0)],
                        ['Remaining Today', number_format(($status['requests']['limit_day'] ?? 0) - ($status['requests']['current'] ?? 0))],
                        ['Reset Time', isset($status['timezone']) ? 'Midnight ' . $status['timezone'] : 'Unknown'],
                    ]);
                    
                    $remaining = ($status['requests']['limit_day'] ?? 0) - ($status['requests']['current'] ?? 0);
                    
                    $this->newLine();
                    
                    if ($remaining > 1000) {
                        $this->info("🚀 EXCELLENT: {$remaining} requests available - ready for major migration!");
                    } elseif ($remaining > 100) {
                        $this->info("✅ GOOD: {$remaining} requests available - can migrate moderate amount");
                    } elseif ($remaining > 10) {
                        $this->warn("⚠️ LIMITED: {$remaining} requests available - small migrations only");
                    } else {
                        $this->error("❌ CRITICAL: {$remaining} requests available - wait for reset");
                    }
                    
                    // Calculate what we can migrate
                    $this->newLine();
                    $this->info('📊 MIGRATION CAPACITY:');
                    $canMigrate = floor($remaining / 2); // 2 requests per match approximately
                    $this->line("Can migrate approximately: {$canMigrate} matches");
                    
                    if ($canMigrate > 0) {
                        $this->info("💡 RECOMMENDED COMMAND:");
                        $this->line("php artisan data:migrate-quota --limit={$canMigrate}");
                    }
                    
                } else {
                    $this->warn('⚠️ Unexpected API response format');
                    $this->line('Response: ' . json_encode($data, JSON_PRETTY_PRINT));
                }
                
            } else {
                $this->error('❌ API request failed');
                $this->line('Status: ' . $response->status());
                $this->line('Response: ' . $response->body());
            }
            
        } catch (\Exception $e) {
            $this->error('❌ Error checking API quota: ' . $e->getMessage());
            return 1;
        }

        return 0;
    }
}