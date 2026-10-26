<?php

namespace Database\Seeders;

use App\Models\LeaveType;
use Illuminate\Database\Seeder;

class LeaveTypesSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            [
                'name' => 'Annual Leave',
                'code' => 'ANNUAL',
                'days_per_year' => 21,
                'accrual' => 'monthly',
                'is_paid' => true,
                'pay_percentage' => 100,
                'min_service_months' => 12,
                'max_carry_forward' => 5,
                'carry_forward_expiry_months' => 12,
            ],
            [
                'name' => 'Sick Leave (Full Pay)',
                'code' => 'SICK_FULL',
                'days_per_year' => 7,
                'accrual' => 'upfront',
                'is_paid' => true,
                'pay_percentage' => 100,
                'requires_document' => true,
                'min_service_months' => 2,
            ],
            [
                'name' => 'Sick Leave (Half Pay)',
                'code' => 'SICK_HALF',
                'days_per_year' => 7,
                'accrual' => 'upfront',
                'is_paid' => true,
                'pay_percentage' => 50,
                'requires_document' => true,
                'min_service_months' => 2,
            ],
            [
                'name' => 'Maternity Leave',
                'code' => 'MATERNITY',
                'days_per_year' => 90,
                'accrual' => 'upfront',
                'is_paid' => true,
                'pay_percentage' => 100,
                'gender_restriction' => 'female',
                'counts_working_days_only' => false,
            ],
            [
                'name' => 'Paternity Leave',
                'code' => 'PATERNITY',
                'days_per_year' => 14,
                'accrual' => 'upfront',
                'is_paid' => true,
                'pay_percentage' => 100,
                'gender_restriction' => 'male',
                'counts_working_days_only' => false,
            ],
            [
                'name' => 'Pre-Adoptive Leave',
                'code' => 'PRE_ADOPTIVE',
                'days_per_year' => 30,
                'accrual' => 'upfront',
                'is_paid' => true,
                'pay_percentage' => 100,
                'counts_working_days_only' => false,
            ],
            [
                'name' => 'Compassionate Leave',
                'code' => 'COMPASSIONATE',
                'days_per_year' => 5,
                'accrual' => 'upfront',
                'is_paid' => true,
                'pay_percentage' => 100,
            ],
            [
                'name' => 'Unpaid Leave',
                'code' => 'UNPAID',
                'days_per_year' => null,
                'accrual' => 'upfront',
                'is_paid' => false,
                'pay_percentage' => 0,
            ],
        ];

        foreach ($types as $type) {
            LeaveType::updateOrCreate(['code' => $type['code']], $type);
        }
    }
}
