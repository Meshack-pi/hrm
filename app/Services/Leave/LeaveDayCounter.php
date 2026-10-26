<?php

namespace App\Services\Leave;

use App\Models\LeaveType;
use App\Models\PublicHoliday;
use Carbon\CarbonPeriod;

class LeaveDayCounter
{
    public function count(string $startDate, string $endDate, LeaveType $leaveType): float
    {
        $period = CarbonPeriod::create($startDate, $endDate);

        if (! $leaveType->counts_working_days_only) {
            return $period->count();
        }

        $holidays = PublicHoliday::active()->whereBetween('date', [$startDate, $endDate])
            ->pluck('date')
            ->map(fn ($d) => $d->toDateString())
            ->flip();

        $days = 0;

        foreach ($period as $date) {
            if ($date->isWeekend()) {
                continue;
            }

            if ($holidays->has($date->toDateString())) {
                continue;
            }

            $days++;
        }

        return (float) $days;
    }
}
