<?php

namespace Database\Seeders;

use App\Models\PayComponent;
use Illuminate\Database\Seeder;

class PayComponentsSeeder extends Seeder
{
    public function run(): void
    {
        $components = [
            ['name' => 'House Allowance', 'code' => 'HOUSE', 'type' => 'earning', 'is_taxable' => true],
            ['name' => 'Transport Allowance', 'code' => 'TRANSPORT', 'type' => 'earning', 'is_taxable' => true],
            ['name' => 'Overtime', 'code' => 'OVERTIME', 'type' => 'earning', 'is_taxable' => true],
            ['name' => 'Harvest Bonus', 'code' => 'BONUS', 'type' => 'earning', 'is_taxable' => true],
            ['name' => 'Arrears', 'code' => 'ARREARS', 'type' => 'earning', 'is_taxable' => true],
            ['name' => 'HELB Loan', 'code' => 'HELB', 'type' => 'deduction', 'is_taxable' => false],
            ['name' => 'SACCO Contribution', 'code' => 'SACCO', 'type' => 'deduction', 'is_taxable' => false],
            ['name' => 'Staff Loan', 'code' => 'LOAN', 'type' => 'deduction', 'is_taxable' => false],
            ['name' => 'Salary Advance', 'code' => 'ADVANCE', 'type' => 'deduction', 'is_taxable' => false],
            ['name' => 'Union Dues', 'code' => 'UNION', 'type' => 'deduction', 'is_taxable' => false],
        ];

        foreach ($components as $c) {
            PayComponent::updateOrCreate(['code' => $c['code']], $c + ['calculation' => 'fixed', 'is_active' => true]);
        }
    }
}
