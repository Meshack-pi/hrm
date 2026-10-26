<?php

namespace App\Console\Commands;

use App\Services\Leave\LeaveAccrualService;
use Illuminate\Console\Command;

class YearEndLeaveCarryForward extends Command
{
    protected $signature = 'leave:carry-forward {--year=}';

    protected $description = 'Carry forward unused leave balances into the following year';

    public function handle(LeaveAccrualService $accrual): int
    {
        $year = (int) ($this->option('year') ?: date('Y'));

        $this->info("Carrying forward leave balances from {$year} to ".($year + 1).'...');

        $count = $accrual->carryForward($year);

        $this->info("Carried forward {$count} balance record(s).");

        return self::SUCCESS;
    }
}
