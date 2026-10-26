<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LeaveType extends Model
{
    protected $fillable = [
        'name', 'code', 'days_per_year', 'accrual', 'is_paid', 'pay_percentage',
        'requires_document', 'min_service_months', 'gender_restriction',
        'eligible_employment_types', 'max_carry_forward', 'carry_forward_expiry_months',
        'counts_working_days_only', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'days_per_year' => 'decimal:2',
            'pay_percentage' => 'decimal:2',
            'max_carry_forward' => 'decimal:2',
            'is_paid' => 'boolean',
            'requires_document' => 'boolean',
            'counts_working_days_only' => 'boolean',
            'is_active' => 'boolean',
            'eligible_employment_types' => 'array',
        ];
    }

    public function balances()
    {
        return $this->hasMany(LeaveBalance::class);
    }
}
