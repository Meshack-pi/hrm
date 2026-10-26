<?php

namespace Database\Seeders;

use App\Models\StatutoryRate;
use App\Models\TaxBand;
use Illuminate\Database\Seeder;

class StatutoryRatesSeeder extends Seeder
{
    public function run(): void
    {
        $effectiveFrom = '2026-02-01';

        $rates = [
            [
                'code' => 'NSSF',
                'name' => 'NSSF Contribution',
                'employee_rate' => 0.06,
                'employer_rate' => 0.06,
                'lower_limit' => 9000,    // LEL
                'upper_limit' => 108000,  // UEL
                'max_amount' => 6480,
            ],
            [
                'code' => 'SHIF',
                'name' => 'Social Health Insurance Fund',
                'employee_rate' => 0.0275,
                'min_amount' => 300,
            ],
            [
                'code' => 'AHL',
                'name' => 'Affordable Housing Levy',
                'employee_rate' => 0.015,
                'employer_rate' => 0.015,
            ],
            [
                'code' => 'NITA',
                'name' => 'National Industrial Training Authority',
                'fixed_amount' => 50,
            ],
            [
                'code' => 'PERSONAL_RELIEF',
                'name' => 'Personal Relief',
                'fixed_amount' => 2400,
            ],
            [
                'code' => 'INSURANCE_RELIEF',
                'name' => 'Insurance Relief',
                'employee_rate' => 0.15,
                'max_amount' => 5000,
            ],
            [
                'code' => 'PENSION_CAP',
                'name' => 'Pension Deductible Cap',
                'max_amount' => 30000,
            ],
            [
                'code' => 'DISABILITY_EXEMPTION',
                'name' => 'Disability Exemption',
                'max_amount' => 150000,
            ],
        ];

        foreach ($rates as $rate) {
            StatutoryRate::updateOrCreate(
                ['code' => $rate['code'], 'effective_from' => $effectiveFrom],
                array_merge($rate, ['effective_from' => $effectiveFrom])
            );
        }

        // `lower` is the threshold at which the band starts, not threshold+1, so that
        // (min(pay, upper) - lower) yields the exact amount taxed in each band.
        $taxBands = [
            ['lower' => 0, 'upper' => 24000, 'rate' => 0.10],
            ['lower' => 24000, 'upper' => 32333, 'rate' => 0.25],
            ['lower' => 32333, 'upper' => 500000, 'rate' => 0.30],
            ['lower' => 500000, 'upper' => 800000, 'rate' => 0.325],
            ['lower' => 800000, 'upper' => null, 'rate' => 0.35],
        ];

        foreach ($taxBands as $band) {
            TaxBand::updateOrCreate(
                ['lower' => $band['lower'], 'effective_from' => $effectiveFrom],
                array_merge($band, ['effective_from' => $effectiveFrom])
            );
        }
    }
}
