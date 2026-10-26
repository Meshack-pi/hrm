<?php

namespace App\Console\Commands;

use App\Services\Holidays\PublicHolidaySync;
use Illuminate\Console\Command;

class SyncPublicHolidays extends Command
{
    protected $signature = 'holidays:sync {--year=* : Year(s) to sync; defaults to this year and next}';

    protected $description = 'Sync Kenyan public holidays from the public holiday API';

    public function handle(PublicHolidaySync $sync): int
    {
        $years = $this->option('year') ?: [(int) date('Y'), (int) date('Y') + 1];

        $results = $sync->sync($years);

        $this->table(
            ['Year', 'Added', 'Updated', 'Removed', 'Sunday substitutes', 'Error'],
            array_map(fn ($r) => [$r['year'], $r['added'], $r['updated'], $r['removed'], $r['substitutes'], $r['error'] ?? '—'], $results)
        );

        $failed = array_filter($results, fn ($r) => $r['error']);

        return count($failed) === count($results) ? self::FAILURE : self::SUCCESS;
    }
}
