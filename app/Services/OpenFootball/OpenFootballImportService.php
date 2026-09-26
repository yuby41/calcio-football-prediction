<?php

namespace App\Services\OpenFootball;

use App\Models\FootballMatch;
use App\Models\Team;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class OpenFootballImportService
{
    public function import(
        string $file,
        string $league,
        string $season,
        bool $dryRun = false
    ): array {
        $path = $this->resolvePath($file);

        if (!is_file($path)) {
            throw new RuntimeException(
                "Dataset not found: {$path}"
            );
        }

        try {
            $data = json_decode(
                file_get_contents($path),
                true,
                512,
                JSON_THROW_ON_ERROR
            );
        } catch (\Throwable $e) {
            throw new RuntimeException(
                'Invalid JSON dataset: ' . $e->getMessage(),
                0,
                $e
            );
        }

        $matches = $data['matches'] ?? null;

        if (!is_array($matches)) {
            throw new RuntimeException(
                'Invalid OpenFootball dataset: matches array missing.'
            );
        }

        $stats = [
            'records' => count($matches),
            'valid' => 0,
            'missing_ft' => 0,
            'invalid' => 0,
            'created' => 0,
            'updated' => 0,
            'with_ht' => 0,
            'missing_ht' => 0,
        ];

        foreach ($matches as $match) {
            $normalized = $this->normalizeMatch(
                $match,
                $league,
                $season
            );

            if ($normalized === null) {
                $stats['missing_ft']++;
                continue;
            }

            if ($normalized === false) {
                $stats['invalid']++;
                continue;
            }

            $stats['valid']++;

            if (
                $normalized['home_goals_first_half'] !== null &&
                $normalized['away_goals_first_half'] !== null
            ) {
                $stats['with_ht']++;
            } else {
                $stats['missing_ht']++;
            }

            $exists = FootballMatch::where(
                'external_id',
                $normalized['external_id']
            )->exists();

            if ($exists) {
                $stats['updated']++;
            } else {
                $stats['created']++;
            }

            if ($dryRun) {
                continue;
            }

            DB::transaction(function () use ($normalized) {
                $homeTeam = $this->resolveTeam(
                    $normalized['home_team_name'],
                    $normalized['league']
                );

                $awayTeam = $this->resolveTeam(
                    $normalized['away_team_name'],
                    $normalized['league']
                );

                FootballMatch::updateOrCreate(
                    [
                        'external_id' =>
                            $normalized['external_id'],
                    ],
                    [
                        'home_team_id' => $homeTeam->id,
                        'away_team_id' => $awayTeam->id,
                        'match_date' =>
                            $normalized['match_date'],
                        'home_goals' =>
                            $normalized['home_goals'],
                        'away_goals' =>
                            $normalized['away_goals'],
                        'home_goals_first_half' =>
                            $normalized[
                                'home_goals_first_half'
                            ],
                        'away_goals_first_half' =>
                            $normalized[
                                'away_goals_first_half'
                            ],
                        'status' => 'finished',
                        'match_status' => 'finished',
                        'league' => $normalized['league'],
                        'season' => $normalized['season'],
                        'round' => $normalized['round'],
                    ]
                );
            });
        }

        return [
            'path' => $path,
            'league' => $league,
            'season' => $season,
            'dry_run' => $dryRun,
            ...$stats,
        ];
    }

    private function resolvePath(string $file): string
    {
        if (str_starts_with($file, '/')) {
            return $file;
        }

        return base_path($file);
    }

    private function normalizeMatch(
        array $match,
        string $league,
        string $season
    ): array|false|null {
        $date = $match['date'] ?? null;
        $time = $match['time'] ?? '00:00';
        $home = trim((string) ($match['team1'] ?? ''));
        $away = trim((string) ($match['team2'] ?? ''));
        $round = $match['round'] ?? null;

        if (!$date || !$home || !$away) {
            return false;
        }

        $ft = $match['score']['ft'] ?? null;

        if (
            !is_array($ft) ||
            count($ft) !== 2 ||
            $ft[0] === null ||
            $ft[1] === null
        ) {
            return null;
        }

        try {
            $matchDate = Carbon::createFromFormat(
                'Y-m-d H:i',
                "{$date} {$time}"
            );
        } catch (\Throwable) {
            return false;
        }

        $ht = $match['score']['ht'] ?? null;

        $homeHt = null;
        $awayHt = null;

        if (
            is_array($ht) &&
            count($ht) === 2 &&
            $ht[0] !== null &&
            $ht[1] !== null
        ) {
            $homeHt = (int) $ht[0];
            $awayHt = (int) $ht[1];
        }

        return [
            'external_id' => $this->matchExternalId(
                $league,
                $season,
                $date,
                $home,
                $away
            ),
            'match_date' => $matchDate,
            'home_team_name' => $home,
            'away_team_name' => $away,
            'home_goals' => (int) $ft[0],
            'away_goals' => (int) $ft[1],
            'home_goals_first_half' => $homeHt,
            'away_goals_first_half' => $awayHt,
            'league' => $league,
            'season' => $season,
            'round' => $round,
        ];
    }

    private function resolveTeam(
        string $name,
        string $league
    ): Team {
        $externalId = 'openfootball:' . sha1(
            mb_strtolower(trim($name))
        );

        return Team::firstOrCreate(
            [
                'external_id' => $externalId,
            ],
            [
                'name' => $name,
                'short_name' =>
                    strtoupper(substr(
                        preg_replace(
                            '/[^A-Za-z0-9]/',
                            '',
                            Str::ascii($name)
                        ),
                        0,
                        3
                    )) ?: 'TBD',
                'league' => $league,
                'is_active' => true,
            ]
        );
    }

    private function matchExternalId(
        string $league,
        string $season,
        string $date,
        string $home,
        string $away
    ): string {
        $identity = implode('|', [
            mb_strtolower(trim($league)),
            mb_strtolower(trim($season)),
            $date,
            mb_strtolower(trim($home)),
            mb_strtolower(trim($away)),
        ]);

        return 'openfootball:' . sha1($identity);
    }
}