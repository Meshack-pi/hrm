<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmployeeRelief extends Model
{
    protected $fillable = [
        'employee_id', 'type', 'monthly_premium_or_amount', 'provider',
        'policy_number', 'effective_from', 'effective_to',
    ];

    protected function casts(): array
    {
        return [
            'monthly_premium_or_amount' => 'decimal:2',
            'effective_from' => 'date',
            'effective_to' => 'date',
        ];
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}
