<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payslip extends Model
{
    protected $fillable = [
        'payroll_run_id', 'employee_id', 'basic_salary', 'gross_pay', 'taxable_pay',
        'nssf_employee', 'nssf_employer', 'shif', 'ahl_employee', 'ahl_employer', 'nita_employer',
        'paye_before_relief', 'personal_relief', 'insurance_relief', 'paye',
        'total_other_deductions', 'total_deductions', 'net_pay',
        'days_worked', 'unpaid_leave_days',
        'payment_status', 'payment_method', 'payment_reference', 'paid_at', 'failure_reason',
        'employee_snapshot',
    ];

    protected function casts(): array
    {
        return [
            'basic_salary' => 'decimal:2',
            'gross_pay' => 'decimal:2',
            'taxable_pay' => 'decimal:2',
            'nssf_employee' => 'decimal:2',
            'nssf_employer' => 'decimal:2',
            'shif' => 'decimal:2',
            'ahl_employee' => 'decimal:2',
            'ahl_employer' => 'decimal:2',
            'nita_employer' => 'decimal:2',
            'paye_before_relief' => 'decimal:2',
            'personal_relief' => 'decimal:2',
            'insurance_relief' => 'decimal:2',
            'paye' => 'decimal:2',
            'total_other_deductions' => 'decimal:2',
            'total_deductions' => 'decimal:2',
            'net_pay' => 'decimal:2',
            'days_worked' => 'decimal:2',
            'unpaid_leave_days' => 'decimal:2',
            'paid_at' => 'datetime',
            'employee_snapshot' => 'encrypted:array',
        ];
    }

    /** Holds the bank/M-Pesa number paid at the time; read it server-side, never serialise it. */
    protected $hidden = ['employee_snapshot'];

    public const VISIBLE_RUN_STATUSES = ['approved', 'paid', 'partially_paid'];

    public function payrollRun()
    {
        return $this->belongsTo(PayrollRun::class);
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function lines()
    {
        return $this->hasMany(PayslipLine::class);
    }
}
