<?php

namespace App\Services\Payroll;

use App\Models\StatutoryRate;

class ShifCalculator
{
    public function calculate(float $grossPay, string $date): float
    {
        $rate = StatutoryRate::active($date)->forCode('SHIF')->first();

        if (! $rate) {
            return 0;
        }

        $amount = round($grossPay * (float) $rate->employee_rate, 2);

        if ($rate->min_amount) {
            $amount = max($amount, (float) $rate->min_amount);
        }

        return $amount;
    }
}
