<?php

namespace App\Console\Commands;

use App\Services\OpenFootball\OpenFootballImportService;
use Illuminate\Console\Command;

class ImportOpenFootballData extends Command
{
    protected $signature = 'football:import-open-data
                            {--file= : Path to OpenFootball JSON file}
                            {--league= : League name stored in the database}
                            {--season= : Season identifier, e.g. 2024-25}
                            {--dry-run : Validate and report without modifying the database}';

    protected $description =
        'Import historical football results from an OpenFootball JSON dataset';

    public function handle(
        OpenFootballImportService $importer
    ): int {
        $file = $this->option('file');
        $league = $this->option('league');
        $season = $this->option('season');
        $dryRun = (bool) $this->option('dry-run');

        if (!$file || !$league || !$season) {
            $this->error(
                'Options --file, --league and --season are required.'
            );

            return self::FAILURE;
        }

        try {
            $stats = $importer->import(
                $file,
                $league,
                $season,
                $dryRun
            );
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info('OpenFootball historical import');
        $this->line("Dataset: {$stats['path']}");
        $this->line("League: {$stats['league']}");
        $this->line("Season: {$stats['season']}");
        $this->line("Records: {$stats['records']}");

        if ($dryRun) {
            $this->warn(
                'DRY RUN: database will not be modified.'
            );
        }

        $this->newLine();

        $this->table(
            ['Metric', 'Count'],
            [
                ['Valid finished matches', $stats['valid']],
                ['Missing FT result', $stats['missing_ft']],
                ['Invalid records', $stats['invalid']],
                ['With HT result', $stats['with_ht']],
                ['Missing HT result', $stats['missing_ht']],
                ['Would create / created', $stats['created']],
                ['Would update / updated', $stats['updated']],
            ]
        );

        $this->info(
            $dryRun
                ? 'Dry run completed. No database changes made.'
                : 'OpenFootball import completed.'
        );

        return self::SUCCESS;
    }
}