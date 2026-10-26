<?php

namespace App\Services\Payroll;

use App\Models\StatutoryRate;

class AhlCalculator
{
    public function calculate(float $grossPay, string $date): array
    {
        $rate = StatutoryRate::active($date)->forCode('AHL')->first();

        if (! $rate) {
            return ['employee' => 0, 'employer' => 0];
        }

        return [
            'employee' => round($grossPay * (float) $rate->employee_rate, 2),
            'employer' => round($grossPay * (float) $rate->employer_rate, 2),
        ];
    }
}
