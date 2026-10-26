<?php

namespace App\Services\Payroll;

use App\Models\StatutoryRate;

class NssfCalculator
{
    public function calculate(float $pensionablePay, string $date): array
    {
        $rate = StatutoryRate::active($date)->forCode('NSSF')->first();

        if (! $rate) {
            return ['employee' => 0, 'employer' => 0];
        }

        $lel = (float) $rate->lower_limit;  // 9,000
        $uel = (float) $rate->upper_limit;  // 108,000
        $employeeRate = (float) $rate->employee_rate;
        $employerRate = (float) $rate->employer_rate;

        // Tier I: rate × min(pay, LEL)
        $tierI = $employeeRate * min($pensionablePay, $lel);

        // Tier II: rate × max(0, min(pay, UEL) - LEL)
        $tierII = $employeeRate * max(0, min($pensionablePay, $uel) - $lel);

        $employee = round($tierI + $tierII, 2);
        $employer = round($employerRate * min($pensionablePay, $uel), 2);

        if ($rate->max_amount) {
            $employee = min($employee, (float) $rate->max_amount);
            $employer = min($employer, (float) $rate->max_amount);
        }

        return [
            'employee' => $employee,
            'employer' => $employer,
        ];
    }
}
