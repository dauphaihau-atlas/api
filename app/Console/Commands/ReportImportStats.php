<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Core\Domain\Enums\ImportStatus;
use App\Infrastructure\Persistence\Eloquent\Models\UserImportModel;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ReportImportStats extends Command
{
    protected $signature = 'app:report-import-stats
                            {--days=30 : Report on imports from the last N days}';

    protected $description = 'Print a summary of user import activity';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $since = now()->subDays($days);

        $rows = UserImportModel::query()
            ->where('created_at', '>=', $since)
            ->select([
                'status',
                DB::raw('COUNT(*) as total'),
                DB::raw('SUM(total_rows) as total_rows'),
                DB::raw('SUM(processed_rows) as processed_rows'),
                DB::raw('SUM(created_count) as created_count'),
                DB::raw('SUM(updated_count) as updated_count'),
            ])
            ->groupBy('status')
            ->get();

        if ($rows->isEmpty()) {
            $this->info("No imports found in the last {$days} day(s).");

            return self::SUCCESS;
        }

        // Compute average duration per status group in PHP (avoids DB-specific date functions).
        $avgDurations = UserImportModel::query()
            ->where('created_at', '>=', $since)
            ->whereNotNull('started_at')
            ->whereNotNull('completed_at')
            ->get(['status', 'started_at', 'completed_at'])
            ->groupBy(fn ($m) => $m->status->value)
            ->map(fn ($group) => round(
                $group->avg(fn ($m) => $m->completed_at->diffInSeconds($m->started_at)),
                1,
            ));

        $this->info("Import stats — last {$days} day(s)");
        $this->newLine();

        $this->table(
            ['Status', 'Imports', 'Total Rows', 'Processed', 'Created', 'Updated', 'Avg Duration'],
            $rows->map(fn ($row) => [
                $row->status->value,
                $row->total,
                $row->total_rows ?? 0,
                $row->processed_rows ?? 0,
                $row->created_count ?? 0,
                $row->updated_count ?? 0,
                isset($avgDurations[$row->status->value]) ? $avgDurations[$row->status->value].'s' : 'n/a',
            ])->toArray(),
        );

        $totals = UserImportModel::query()
            ->where('created_at', '>=', $since)
            ->selectRaw('COUNT(*) as imports, SUM(total_rows) as rows, SUM(created_count) as created, SUM(updated_count) as updated')
            ->first();

        $completed = $rows->firstWhere('status', ImportStatus::Completed->value);
        $failed = $rows->firstWhere('status', ImportStatus::Failed->value);
        $total = $rows->sum('total');
        $successRate = $total > 0 && $completed
            ? round(($completed->total / $total) * 100, 1)
            : 0.0;

        $this->newLine();
        $this->line(sprintf('  Total imports  : %d', $totals->imports ?? 0));
        $this->line(sprintf('  Total rows     : %d', $totals->rows ?? 0));
        $this->line(sprintf('  Users created  : %d', $totals->created ?? 0));
        $this->line(sprintf('  Users updated  : %d', $totals->updated ?? 0));
        $this->line(sprintf('  Success rate   : %s%%', $successRate));

        if ($failed && $failed->total > 0) {
            $this->newLine();
            $this->warn(sprintf('  %d import(s) failed — run app:retry-failed-imports to recover.', $failed->total));
        }

        return self::SUCCESS;
    }
}
