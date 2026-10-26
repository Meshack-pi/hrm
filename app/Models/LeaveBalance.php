<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LeaveBalance extends Model
{
    protected $fillable = [
        'employee_id', 'leave_type_id', 'year', 'entitled', 'accrued',
        'carried_forward', 'taken', 'pending',
    ];

    protected function casts(): array
    {
        return [
            'entitled' => 'decimal:2',
            'accrued' => 'decimal:2',
            'carried_forward' => 'decimal:2',
            'taken' => 'decimal:2',
            'pending' => 'decimal:2',
        ];
    }

    public function getBalanceAttribute(): float
    {
        return round($this->accrued + $this->carried_forward - $this->taken - $this->pending, 2);
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function leaveType()
    {
        return $this->belongsTo(LeaveType::class);
    }
}
