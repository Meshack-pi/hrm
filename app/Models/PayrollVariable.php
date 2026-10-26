<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PayrollVariable extends Model
{
    protected $fillable = ['payroll_run_id', 'employee_id', 'pay_component_id', 'amount', 'description'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2'];
    }

    public function payrollRun()
    {
        return $this->belongsTo(PayrollRun::class);
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
