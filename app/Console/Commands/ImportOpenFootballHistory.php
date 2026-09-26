<?php

namespace App\Console\Commands;

use App\Services\OpenFootball\OpenFootballImportService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class ImportOpenFootballHistory extends Command
{
    protected $signature = 'football:import-open-history
                            {--competition=serie-a : Competition configuration}
                            {--from=2020-21 : First season}
                            {--to=2024-25 : Last season}
                            {--dry-run : Validate without modifying the database}
                            {--no-download : Use existing local datasets only}';

    protected $description =
        'Download and import multiple historical OpenFootball seasons';

    private const COMPETITIONS = [
        'serie-a' => [
            'league' => 'Serie A',
            'dataset' => 'it.1',
        ],
    ];

    public function handle(
        OpenFootballImportService $importer
    ): int {
        $competition = (string) $this->option('competition');
        $from = (string) $this->option('from');
        $to = (string) $this->option('to');
        $dryRun = (bool) $this->option('dry-run');
        $noDownload = (bool) $this->option('no-download');

        $config = self::COMPETITIONS[$competition] ?? null;

        if ($config === null) {
            $this->error(
                "Unsupported competition: {$competition}"
            );

            return self::FAILURE;
        }

        try {
            $seasons = $this->buildSeasonRange($from, $to);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info('OpenFootball historical batch import');
        $this->line("Competition: {$config['league']}");
        $this->line("Dataset: {$config['dataset']}");
        $this->line(
            'Seasons: ' . implode(', ', $seasons)
        );

        if ($dryRun) {
            $this->warn(
                'DRY RUN: database will not be modified.'
            );
        }

        $totals = [
            'seasons' => 0,
            'records' => 0,
            'valid' => 0,
            'missing_ft' => 0,
            'invalid' => 0,
            'with_ht' => 0,
            'missing_ht' => 0,
            'created' => 0,
            'updated' => 0,
            'errors' => 0,
        ];

        $rows = [];

        foreach ($seasons as $season) {
            $this->newLine();
            $this->line("<info>Processing {$season}</info>");

            $relativePath =
                "storage/app/datasets/openfootball/" .
                "{$config['dataset']}-{$season}.json";

            try {
                if (!$noDownload) {
                    $this->downloadDataset(
                        $config['dataset'],
                        $season,
                        $relativePath
                    );
                } elseif (!is_file(base_path($relativePath))) {
                    throw new RuntimeException(
                        "Local dataset not found: {$relativePath}"
                    );
                }

                $stats = $importer->import(
                    $relativePath,
                    $config['league'],
                    $season,
                    $dryRun
                );

                $totals['seasons']++;
                $totals['records'] += $stats['records'];
                $totals['valid'] += $stats['valid'];
                $totals['missing_ft'] += $stats['missing_ft'];
                $totals['invalid'] += $stats['invalid'];
                $totals['with_ht'] += $stats['with_ht'];
                $totals['missing_ht'] += $stats['missing_ht'];
                $totals['created'] += $stats['created'];
                $totals['updated'] += $stats['updated'];

                $rows[] = [
                    $season,
                    $stats['records'],
                    $stats['valid'],
                    $stats['missing_ft'],
                    $stats['with_ht'],
                    $stats['created'],
                    $stats['updated'],
                    'OK',
                ];
            } catch (\Throwable $e) {
                $totals['errors']++;

                $rows[] = [
                    $season,
                    '-',
                    '-',
                    '-',
                    '-',
                    '-',
                    '-',
                    'ERROR',
                ];

                $this->error(
                    "{$season}: {$e->getMessage()}"
                );
            }
        }

        $this->newLine();

        $this->table(
            [
                'Season',
                'Records',
                'Valid FT',
                'Missing FT',
                'With HT',
                'Create',
                'Update',
                'Status',
            ],
            $rows
        );

        $this->newLine();

        $this->table(
            ['Metric', 'Total'],
            [
                ['Seasons processed', $totals['seasons']],
                ['Source records', $totals['records']],
                ['Valid finished matches', $totals['valid']],
                ['Missing FT', $totals['missing_ft']],
                ['Invalid records', $totals['invalid']],
                ['With HT', $totals['with_ht']],
                ['Missing HT', $totals['missing_ht']],
                ['Would create / created', $totals['created']],
                ['Would update / updated', $totals['updated']],
                ['Errors', $totals['errors']],
            ]
        );

        if ($totals['errors'] > 0) {
            $this->error(
                'Batch completed with errors.'
            );

            return self::FAILURE;
        }

        $this->info(
            $dryRun
                ? 'Batch dry run completed successfully.'
                : 'Historical batch import completed successfully.'
        );

        return self::SUCCESS;
    }

    private function downloadDataset(
        string $dataset,
        string $season,
        string $relativePath
    ): void {
        $url =
            "https://raw.githubusercontent.com/" .
            "openfootball/football.json/master/" .
            "{$season}/{$dataset}.json";

        $path = base_path($relativePath);
        $directory = dirname($path);

        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $response = Http::timeout(30)
            ->retry(2, 500)
            ->get($url);

        if (!$response->successful()) {
            throw new RuntimeException(
                "Download failed ({$response->status()}): {$url}"
            );
        }

        if (file_put_contents($path, $response->body()) === false) {
            throw new RuntimeException(
                "Unable to write dataset: {$path}"
            );
        }
    }

    private function buildSeasonRange(
        string $from,
        string $to
    ): array {
        if (
            !preg_match('/^(\d{4})-(\d{2})$/', $from, $fromMatch) ||
            !preg_match('/^(\d{4})-(\d{2})$/', $to, $toMatch)
        ) {
            throw new RuntimeException(
                'Seasons must use YYYY-YY format, e.g. 2020-21.'
            );
        }

        $start = (int) $fromMatch[1];
        $end = (int) $toMatch[1];

        if ($start > $end) {
            throw new RuntimeException(
                '--from must not be later than --to.'
            );
        }

        if (($end - $start) > 30) {
            throw new RuntimeException(
                'Season range is unexpectedly large.'
            );
        }

        $seasons = [];

        for ($year = $start; $year <= $end; $year++) {
            $next = ($year + 1) % 100;

            $seasons[] = sprintf(
                '%04d-%02d',
                $year,
                $next
            );
        }

        return $seasons;
    }
}