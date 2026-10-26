<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmployeePayComponent extends Model
{
    protected $fillable = ['employee_id', 'pay_component_id', 'amount_or_rate', 'effective_from', 'effective_to'];

    protected function casts(): array
    {
        return [
            'amount_or_rate' => 'decimal:2',
            'effective_from' => 'date',
            'effective_to' => 'date',
        ];
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function payComponent()
    {
        return $this->belongsTo(PayComponent::class);
    }
}
