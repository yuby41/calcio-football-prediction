<?php

namespace App\Console\Commands;

use App\Models\FootballMatch;
use App\Services\FootballApiService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class ImportHistoricalMatches extends Command
{
    protected $signature = 'football:import-history
                            {--from= : Start date YYYY-MM-DD}
                            {--to= : End date YYYY-MM-DD}
                            {--league= : Optional league code}
                            {--dry-run : Fetch and inspect without writing to database}';

    protected $description =
        'Import real historical football fixtures from API-Sports';

    public function __construct(
        private FootballApiService $footballApiService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $from = $this->option('from');
        $to = $this->option('to');
        $league = $this->option('league');
        $dryRun = (bool) $this->option('dry-run');

        if (!$from || !$to) {
            $this->error(
                'Both --from and --to are required.'
            );

            return Command::INVALID;
        }

        try {
            $fromDate = Carbon::createFromFormat('Y-m-d', $from)
                ->startOfDay();

            $toDate = Carbon::createFromFormat('Y-m-d', $to)
                ->startOfDay();
        } catch (\Throwable $e) {
            $this->error(
                'Dates must use YYYY-MM-DD format.'
            );

            return Command::INVALID;
        }

        if ($fromDate->gt($toDate)) {
            $this->error(
                '--from must be earlier than or equal to --to.'
            );

            return Command::INVALID;
        }

        if ($toDate->isFuture()) {
            $this->error(
                'Historical import cannot include future dates.'
            );

            return Command::INVALID;
        }

        $this->info('Historical fixture import');
        $this->line("Range: {$from} -> {$to}");
        $this->line(
            'League: ' . ($league ?: 'all available leagues')
        );

        if ($dryRun) {
            $this->warn('DRY RUN: database will not be modified.');
        }

        try {
            $matches = $this->footballApiService
                ->fetchMatchesByDateRange(
                    $from,
                    $to,
                    $league ?: null
                );
        } catch (\Throwable $e) {
            $this->error(
                'Historical import failed: ' .
                $e->getMessage()
            );

            return Command::FAILURE;
        }

        $total = count($matches);

        $this->info("Fixtures received: {$total}");

        if ($total === 0) {
            $this->warn('No fixtures returned by API.');

            return Command::SUCCESS;
        }

        $finished = 0;
        $created = 0;
        $updated = 0;
        $skipped = 0;

        foreach ($matches as $matchData) {
            if (($matchData['status'] ?? null) !== 'finished') {
                $skipped++;
                continue;
            }

            if (
                $matchData['home_goals'] === null ||
                $matchData['away_goals'] === null
            ) {
                $skipped++;
                continue;
            }

            $finished++;

            $exists = FootballMatch::where(
                'external_id',
                $matchData['external_id']
            )->exists();

            if ($dryRun) {
                $exists ? $updated++ : $created++;
                continue;
            }

            $this->footballApiService
                ->syncSingleMatch($matchData);

            $exists ? $updated++ : $created++;
        }

        $this->newLine();
        $this->info('Import summary');
        $this->line("API fixtures: {$total}");
        $this->line("Valid finished: {$finished}");
        $this->line("Would/create: {$created}");
        $this->line("Would/update: {$updated}");
        $this->line("Skipped: {$skipped}");

        if ($dryRun) {
            $this->warn(
                'Dry run completed. No database records changed.'
            );
        } else {
            $this->info('Historical import completed.');
        }

        return Command::SUCCESS;
    }
}
