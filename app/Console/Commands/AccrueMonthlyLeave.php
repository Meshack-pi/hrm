<?php

namespace App\Console\Commands;

use App\Services\Leave\LeaveAccrualService;
use Illuminate\Console\Command;

class AccrueMonthlyLeave extends Command
{
    protected $signature = 'leave:accrue {--year=} {--month=}';

    protected $description = 'Accrue monthly leave entitlement for all active employees';

    public function handle(LeaveAccrualService $accrual): int
    {
        $year = (int) ($this->option('year') ?: date('Y'));
        $month = (int) ($this->option('month') ?: date('n'));

        $this->info("Accruing leave for {$year}-{$month}...");

        $count = $accrual->accrueForMonth($year, $month);

        $this->info("Accrued leave on {$count} balance record(s).");

        return self::SUCCESS;
    }
}
