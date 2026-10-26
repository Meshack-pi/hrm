<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class PayrollRun extends Model
{
    use LogsActivity;

    protected $fillable = [
        'period_month', 'period_year', 'status', 'total_gross', 'total_deductions',
        'total_net', 'total_employer_cost', 'calculated_by', 'calculated_at',
        'approved_by', 'approved_at', 'paid_marked_by', 'paid_at', 'locked_at', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'total_gross' => 'decimal:2',
            'total_deductions' => 'decimal:2',
            'total_net' => 'decimal:2',
            'total_employer_cost' => 'decimal:2',
            'calculated_at' => 'datetime',
            'approved_at' => 'datetime',
            'paid_at' => 'datetime',
            'locked_at' => 'datetime',
        ];
    }

    public function getPeriodLabelAttribute(): string
    {
        return date('F Y', mktime(0, 0, 0, $this->period_month, 1, $this->period_year));
    }

    public function payslips()
    {
        return $this->hasMany(Payslip::class);
    }

    public function variables()
    {
        return $this->hasMany(PayrollVariable::class);
    }

    public function calculatedByUser()
    {
        return $this->belongsTo(User::class, 'calculated_by');
    }

    public function approvedByUser()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function isLocked(): bool
    {
        return $this->locked_at !== null;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'approved_by', 'approved_at', 'paid_at'])
            ->logOnlyDirty();
    }
}
