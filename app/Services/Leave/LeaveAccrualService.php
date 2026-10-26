<?php

namespace App\Services\Leave;

use App\Models\Employee;
use App\Models\LeaveBalance;
use App\Models\LeaveType;

class LeaveAccrualService
{
    public function accrueForMonth(int $year, int $month): int
    {
        $accrualTypes = LeaveType::where('is_active', true)
            ->where('accrual', 'monthly')
            ->whereNotNull('days_per_year')
            ->get();

        if ($accrualTypes->isEmpty()) {
            return 0;
        }

        $asOf = sprintf('%04d-%02d-01', $year, $month);
        $processed = 0;

        $employees = Employee::where('status', 'active')
            ->where('hire_date', '<=', $asOf)
            ->get();

        foreach ($employees as $employee) {
            foreach ($accrualTypes as $type) {
                if (! $this->isEligible($employee, $type, $asOf)) {
                    continue;
                }

                $monthlyRate = round((float) $type->days_per_year / 12, 4);

                $balance = LeaveBalance::firstOrCreate(
                    [
                        'employee_id' => $employee->id,
                        'leave_type_id' => $type->id,
                        'year' => $year,
                    ],
                    ['entitled' => $type->days_per_year]
                );

                $balance->increment('accrued', $monthlyRate);
                $processed++;
            }
        }

        return $processed;
    }

    public function isEligible(Employee $employee, LeaveType $type, string $asOf): bool
    {
        if ($type->gender_restriction && $employee->gender !== $type->gender_restriction) {
            return false;
        }

        $eligibleTypes = $type->eligible_employment_types;
        if (! empty($eligibleTypes) && ! in_array($employee->employment_type, $eligibleTypes, true)) {
            return false;
        }

        if ($type->min_service_months > 0) {
            $monthsOfService = $employee->hire_date->diffInMonths($asOf);
            if ($monthsOfService < $type->min_service_months) {
                return false;
            }
        }

        return true;
    }

    public function carryForward(int $fromYear): int
    {
        $toYear = $fromYear + 1;
        $carried = 0;

        $balances = LeaveBalance::with('leaveType')
            ->where('year', $fromYear)
            ->get();

        foreach ($balances as $balance) {
            $type = $balance->leaveType;

            if (! $type || $type->max_carry_forward <= 0) {
                continue;
            }

            $remaining = $balance->balance;

            if ($remaining <= 0) {
                continue;
            }

            $amount = min($remaining, (float) $type->max_carry_forward);

            LeaveBalance::updateOrCreate(
                [
                    'employee_id' => $balance->employee_id,
                    'leave_type_id' => $balance->leave_type_id,
                    'year' => $toYear,
                ],
                ['entitled' => $type->days_per_year, 'carried_forward' => $amount]
            );

            $carried++;
        }

        return $carried;
    }
}
