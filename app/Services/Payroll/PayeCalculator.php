<?php

namespace App\Services\Payroll;

use App\Models\StatutoryRate;
use App\Models\TaxBand;

class PayeCalculator
{
    public function calculate(
        float $taxablePay,
        string $date,
        float $insurancePremiums = 0,
        bool $hasDisabilityExemption = false
    ): array {
        $personalRelief = $this->getPersonalRelief($date);
        $insuranceRelief = $this->getInsuranceRelief($insurancePremiums, $date);

        if ($hasDisabilityExemption) {
            $exemption = StatutoryRate::active($date)->forCode('DISABILITY_EXEMPTION')->first();
            $exemptAmount = $exemption ? (float) $exemption->max_amount : 150000;
            $taxablePay = max(0, $taxablePay - $exemptAmount);
        }

        $payeBeforeRelief = $this->applyTaxBands($taxablePay, $date);

        $paye = max(0, round($payeBeforeRelief - $personalRelief - $insuranceRelief, 2));

        return [
            'paye_before_relief' => round($payeBeforeRelief, 2),
            'personal_relief' => round($personalRelief, 2),
            'insurance_relief' => round($insuranceRelief, 2),
            'paye' => $paye,
        ];
    }

    private function applyTaxBands(float $taxablePay, string $date): float
    {
        $bands = TaxBand::active($date)->orderBy('lower')->get();
        $tax = 0;

        foreach ($bands as $band) {
            $lower = (float) $band->lower;
            $upper = $band->upper ? (float) $band->upper : PHP_FLOAT_MAX;
            $rate = (float) $band->rate;

            if ($taxablePay <= $lower) {
                break;
            }

            $taxableInBand = min($taxablePay, $upper) - $lower;
            $tax += $taxableInBand * $rate;
        }

        return round($tax, 2);
    }

    private function getPersonalRelief(string $date): float
    {
        $rate = StatutoryRate::active($date)->forCode('PERSONAL_RELIEF')->first();

        return $rate ? (float) $rate->fixed_amount : 0;
    }

    private function getInsuranceRelief(float $premiums, string $date): float
    {
        if ($premiums <= 0) {
            return 0;
        }

        $rate = StatutoryRate::active($date)->forCode('INSURANCE_RELIEF')->first();

        if (! $rate) {
            return 0;
        }

        $relief = round($premiums * (float) $rate->employee_rate, 2);

        return $rate->max_amount ? min($relief, (float) $rate->max_amount) : $relief;
    }
}
