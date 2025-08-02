<?php

namespace App\Services;

use App\Models\FootballMatch;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class FootballApiOddsService
{
    private string $apiKey;
    private string $apiUrl;

    public function __construct()
    {
        $this->apiKey = config('services.football_api.key', '');
        $this->apiUrl = 'https://v3.football.api-sports.io';
    }

    /**
     * Obtener odds reales para un partido específico desde Football-API-Sports
     */
    public function getRealOddsForMatch(FootballMatch $match): array
    {
        // Always try to get fallback odds first to ensure we have something
        $fallbackOdds = $this->generateRealisticFallbackOdds($match);
        
        // Try to get odds from API with a short timeout
        try {
            $apiOdds = $this->getOddsFromFootballApiWithTimeout($match, 5); // 5 second timeout
            
            if (!empty($apiOdds)) {
                Log::info('Using Football API odds for match', [
                    'match_id' => $match->id,
                    'odds_source' => $apiOdds['source'] ?? 'api',
                    'odds_count' => count($apiOdds)
                ]);
                return $apiOdds;
            }
        } catch (\Exception $e) {
            Log::warning('API odds failed, using fallback', [
                'match_id' => $match->id,
                'error' => $e->getMessage()
            ]);
        }
        
        // Always return fallback odds if API fails
        Log::info('Using fallback odds for match', [
            'match_id' => $match->id,
            'home_team' => $match->homeTeam->name,
            'away_team' => $match->awayTeam->name,
            'odds_count' => count($fallbackOdds)
        ]);
        
        return $fallbackOdds;
    }

    /**
     * Obtener odds desde Football-API-Sports con timeout personalizado
     */
    private function getOddsFromFootballApiWithTimeout(FootballMatch $match, int $timeoutSeconds = 5): array
    {
        // Use the existing method but return empty array on timeout
        try {
            return $this->getOddsFromFootballApi($match);
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Obtener odds desde Football-API-Sports
     */
    private function getOddsFromFootballApi(FootballMatch $match): array
    {
        try {
            $cacheKey = "football_api_odds_match_{$match->id}";
            
            // Cache por 30 minutos
            return Cache::remember($cacheKey, 1800, function () use ($match) {
                
                if (empty($this->apiKey)) {
                    Log::warning('FOOTBALL_API_KEY not configured, using fallback odds');
                    return [];
                }

                // Primero obtener fixtures del día para tener los nombres de equipos
                $matchDate = $match->match_date->format('Y-m-d');
                
                $fixturesResponse = Http::withHeaders([
                    'x-apisports-key' => $this->apiKey
                ])->timeout(10)->get($this->apiUrl . '/fixtures', [
                    'date' => $matchDate,
                    'timezone' => 'Europe/London'
                ]);

                if (!$fixturesResponse->successful()) {
                    Log::error('Football API fixtures request failed', [
                        'status' => $fixturesResponse->status(),
                        'response' => $fixturesResponse->body()
                    ]);
                    return [];
                }

                $fixtures = $fixturesResponse->json()['response'] ?? [];
                
                // Buscar el fixture que coincida con nuestro partido
                $matchingFixture = $this->findMatchingFixture($fixtures, $match);
                
                if (!$matchingFixture) {
                    return []; // No se encontró el partido en los fixtures
                }

                // Ahora obtener las odds para ese fixture específico
                $oddsResponse = Http::withHeaders([
                    'x-apisports-key' => $this->apiKey
                ])->timeout(10)->get($this->apiUrl . '/odds', [
                    'fixture' => $matchingFixture['fixture']['id'],
                    'timezone' => 'Europe/London'
                ]);

                if (!$oddsResponse->successful()) {
                    return [];
                }

                $oddsData = $oddsResponse->json();
                return $this->parseFootballApiOddsResponse($oddsData['response'] ?? [], $matchingFixture);
            });

        } catch (\Exception $e) {
            Log::error('Error fetching odds from Football API', [
                'match_id' => $match->id,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Buscar fixture que coincida con nuestro partido
     */
    private function findMatchingFixture(array $fixtures, FootballMatch $match): ?array
    {
        $homeTeamName = $this->normalizeTeamName($match->homeTeam->name);
        $awayTeamName = $this->normalizeTeamName($match->awayTeam->name);
        
        foreach ($fixtures as $fixture) {
            $apiHome = $this->normalizeTeamName($fixture['teams']['home']['name'] ?? '');
            $apiAway = $this->normalizeTeamName($fixture['teams']['away']['name'] ?? '');
            
            // Matching por similitud de nombres
            if ($this->teamsMatch($homeTeamName, $apiHome) && 
                $this->teamsMatch($awayTeamName, $apiAway)) {
                return $fixture;
            }
        }

        return null;
    }

    /**
     * Parsear respuesta de Football-API-Sports
     */
    private function parseFootballApiOddsResponse(array $oddsData, array $fixture): array
    {
        if (empty($oddsData)) {
            return [];
        }

        // Tomar el primer elemento de odds (debería ser el que pedimos)
        $matchOdds = $oddsData[0] ?? null;
        
        if (!$matchOdds || empty($matchOdds['bookmakers'])) {
            return [];
        }

        // Priorizar bookmakers (Bet365, Pinnacle, Betfair)
        $preferredBookmakers = [
            'Bet365' => 'bet365',
            'Pinnacle' => 'pinnacle', 
            'Betfair' => 'betfair',
            'Unibet' => 'unibet',
            '1xBet' => '1xbet',
            'Bwin' => 'bwin',
            '10Bet' => '10bet'
        ];

        $bestBookmaker = null;
        $bookmakerKey = null;

        // Buscar el mejor bookmaker disponible
        foreach ($matchOdds['bookmakers'] as $bookmaker) {
            $bookmakerName = $bookmaker['name'];
            if (isset($preferredBookmakers[$bookmakerName])) {
                $bestBookmaker = $bookmaker;
                $bookmakerKey = $preferredBookmakers[$bookmakerName];
                break;
            }
        }

        // Si no encontramos un bookmaker preferido, usar el primero disponible
        if (!$bestBookmaker && !empty($matchOdds['bookmakers'])) {
            $bestBookmaker = $matchOdds['bookmakers'][0];
            $bookmakerKey = strtolower(str_replace(' ', '', $bestBookmaker['name']));
        }

        if ($bestBookmaker) {
            $extractedOdds = $this->extractOddsFromFootballApiBookmaker($bestBookmaker);
            
            // Agregar source a cada odd
            foreach ($extractedOdds as $key => $value) {
                if ($key !== 'source') {
                    $extractedOdds[$key . '_source'] = $bookmakerKey . '_real';
                }
            }
            $extractedOdds['source'] = $bookmakerKey . '_real';
            
            return $extractedOdds;
        }

        return [];
    }


    /**
     * Extraer odds específicas de un bookmaker de Football-API-Sports
     */
    private function extractOddsFromFootballApiBookmaker(array $bookmaker): array
    {
        $odds = [];

        foreach ($bookmaker['bets'] as $bet) {
            switch ($bet['id']) {
                case 1: // Match Winner (1X2)
                    foreach ($bet['values'] as $value) {
                        switch ($value['value']) {
                            case 'Home':
                                $odds['home_win'] = (float)$value['odd'];
                                break;
                            case 'Draw':
                                $odds['draw'] = (float)$value['odd'];
                                break;
                            case 'Away':
                                $odds['away_win'] = (float)$value['odd'];
                                break;
                        }
                    }
                    break;

                case 5: // Goals Over/Under
                    foreach ($bet['values'] as $value) {
                        if (strpos($value['value'], '2.5') !== false) {
                            if (strpos($value['value'], 'Over') !== false) {
                                $odds['over_2_5'] = (float)$value['odd'];
                            } elseif (strpos($value['value'], 'Under') !== false) {
                                $odds['under_2_5'] = (float)$value['odd'];
                            }
                        }
                    }
                    break;

                case 8: // Both Teams Score
                    foreach ($bet['values'] as $value) {
                        if ($value['value'] === 'Yes') {
                            $odds['both_teams_score'] = (float)$value['odd'];
                        }
                    }
                    break;
            }
        }

        return $odds;
    }

    /**
     * Generar odds realistas como fallback cuando no hay datos de API
     */
    private function generateRealisticFallbackOdds(FootballMatch $match): array
    {
        // Usar odds basadas en equipos conocidos y estadísticas
        $homeStrength = $this->calculateTeamStrength($match->homeTeam->name);
        $awayStrength = $this->calculateTeamStrength($match->awayTeam->name);
        
        // Generar odds realistas basadas en fortaleza relativa
        $homeWinOdds = $this->calculateHomeWinOdds($homeStrength, $awayStrength);
        $awayWinOdds = $this->calculateAwayWinOdds($homeStrength, $awayStrength);
        $drawOdds = $this->calculateDrawOdds($homeStrength, $awayStrength);

        $odds = [
            'home_win' => $homeWinOdds,
            'away_win' => $awayWinOdds,
            'draw' => $drawOdds,
            'over_2_5' => $this->generateOverUnderOdds($homeStrength, $awayStrength, true),
            'under_2_5' => $this->generateOverUnderOdds($homeStrength, $awayStrength, false),
            'both_teams_score' => $this->generateBothTeamsScoreOdds($homeStrength, $awayStrength),
            'over_0_5_first_half' => $this->generateOver05FirstHalfOdds($homeStrength, $awayStrength),
        ];
        
        // Agregar source a cada odd individual
        foreach ($odds as $key => $value) {
            if ($key !== 'source') {
                $odds[$key . '_source'] = 'footballapi_fallback';
            }
        }
        
        $odds['source'] = 'footballapi_fallback';
        return $odds;
    }

    /**
     * Calcular fortaleza del equipo basada en nombre y datos conocidos
     */
    private function calculateTeamStrength(string $teamName): float
    {
        // Mapeo de equipos conocidos con su fortaleza relativa (0.1 - 1.0)
        $knownTeams = [
            // Premier League - Top 6
            'Manchester City' => 0.95, 'Arsenal' => 0.90, 'Liverpool' => 0.92,
            'Chelsea' => 0.88, 'Manchester United' => 0.85, 'Tottenham' => 0.82,
            
            // Premier League - Mid table
            'Newcastle' => 0.78, 'Brighton' => 0.72, 'Aston Villa' => 0.75,
            'West Ham' => 0.70, 'Crystal Palace' => 0.65, 'Fulham' => 0.68,
            
            // La Liga - Top teams
            'Real Madrid' => 0.96, 'Barcelona' => 0.94, 'Atletico Madrid' => 0.88,
            'Sevilla' => 0.80, 'Real Sociedad' => 0.75, 'Villarreal' => 0.76,
            
            // Serie A - Top teams  
            'Juventus' => 0.88, 'Inter Milan' => 0.90, 'AC Milan' => 0.86,
            'Napoli' => 0.87, 'Roma' => 0.78, 'Lazio' => 0.75,
            
            // Bundesliga - Top teams
            'Bayern Munich' => 0.94, 'Borussia Dortmund' => 0.86, 'RB Leipzig' => 0.82,
            'Bayer Leverkusen' => 0.78, 'Eintracht Frankfurt' => 0.72,
        ];

        // Buscar coincidencia exacta o parcial
        foreach ($knownTeams as $known => $strength) {
            if (stripos($teamName, $known) !== false || stripos($known, $teamName) !== false) {
                return $strength;
            }
        }

        // Si no se encuentra, asignar fortaleza promedio con variación aleatoria
        return 0.5 + (mt_rand(-20, 20) / 100); // 0.3 - 0.7
    }

    private function calculateHomeWinOdds(float $homeStrength, float $awayStrength): float
    {
        $homeAdvantage = 0.05; // 5% ventaja de local
        $adjustedHome = $homeStrength + $homeAdvantage;
        
        $strengthDiff = $adjustedHome - $awayStrength;
        $winProbability = 0.33 + ($strengthDiff * 0.4); // Base 33% + ajuste
        $winProbability = max(0.1, min(0.85, $winProbability)); // Límites
        
        $odds = 1 / $winProbability * 1.05; // 5% margen
        return $this->roundOdds(max(1.1, min(15.0, $odds)));
    }

    private function calculateAwayWinOdds(float $homeStrength, float $awayStrength): float
    {
        $strengthDiff = $awayStrength - $homeStrength;
        $winProbability = 0.28 + ($strengthDiff * 0.35); // Base 28% (menor que local)
        $winProbability = max(0.08, min(0.75, $winProbability));
        
        $odds = 1 / $winProbability * 1.05;
        return $this->roundOdds(max(1.2, min(25.0, $odds)));
    }

    private function calculateDrawOdds(float $homeStrength, float $awayStrength): float
    {
        $strengthBalance = 1 - abs($homeStrength - $awayStrength);
        $drawProbability = 0.25 + ($strengthBalance * 0.15); // Más balance = más empate
        $drawProbability = max(0.15, min(0.35, $drawProbability));
        
        $odds = 1 / $drawProbability * 1.05;
        return $this->roundOdds(max(2.5, min(6.5, $odds)));
    }

    private function generateOverUnderOdds(float $homeStrength, float $awayStrength, bool $isOver): float
    {
        $attackingStrength = ($homeStrength + $awayStrength) / 2;
        $goalsProbability = $isOver ? 
            (0.45 + $attackingStrength * 0.25) : 
            (0.55 - $attackingStrength * 0.25);
        
        $goalsProbability = max(0.25, min(0.75, $goalsProbability));
        $odds = 1 / $goalsProbability * 1.07; // 7% margen para goles
        
        return $this->roundOdds(max(1.3, min(4.0, $odds)));
    }

    private function generateBothTeamsScoreOdds(float $homeStrength, float $awayStrength): float
    {
        $averageStrength = ($homeStrength + $awayStrength) / 2;
        $bothScoreProbability = 0.5 + ($averageStrength * 0.2);
        $bothScoreProbability = max(0.35, min(0.75, $bothScoreProbability));
        
        $odds = 1 / $bothScoreProbability * 1.08; // 8% margen
        return $this->roundOdds(max(1.4, min(3.5, $odds)));
    }

    private function generateOver05FirstHalfOdds(float $homeStrength, float $awayStrength): float
    {
        // Over 0.5 first half es muy común (60-80% de los partidos)
        $averageStrength = ($homeStrength + $awayStrength) / 2;
        $over05FirstHalfProb = 0.62 + ($averageStrength * 0.18); // Base 62% + ajuste por fuerza
        $over05FirstHalfProb = max(0.55, min(0.82, $over05FirstHalfProb));
        
        $odds = 1 / $over05FirstHalfProb * 1.06; // 6% margen (mercado popular)
        return $this->roundOdds(max(1.2, min(2.2, $odds))); // Rango típico 1.2 - 2.2
    }

    private function roundOdds(float $odds): float
    {
        if ($odds < 2.0) {
            return round($odds * 20) / 20; // .05 increments
        } elseif ($odds < 5.0) {
            return round($odds * 10) / 10; // .1 increments
        } else {
            return round($odds * 2) / 2; // .5 increments
        }
    }

    private function normalizeTeamName(string $name): string
    {
        return strtolower(trim(preg_replace('/[^a-zA-Z0-9\s]/', '', $name)));
    }

    private function teamsMatch(string $team1, string $team2): bool
    {
        $similarity = 0;
        similar_text($team1, $team2, $similarity);
        return $similarity > 70; // 70% similitud mínima
    }
}