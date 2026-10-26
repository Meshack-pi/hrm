<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class LeaveRequest extends Model
{
    use LogsActivity;

    protected $fillable = [
        'employee_id', 'leave_type_id', 'start_date', 'end_date', 'days',
        'reason', 'attachment_path', 'relief_employee_id',
        'status', 'approver_id', 'approved_at', 'rejection_reason',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'days' => 'decimal:2',
            'approved_at' => 'datetime',
        ];
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function leaveType()
    {
        return $this->belongsTo(LeaveType::class);
    }

    public function reliefEmployee()
    {
        return $this->belongsTo(Employee::class, 'relief_employee_id');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approver_id');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'approver_id', 'approved_at'])
            ->logOnlyDirty();
    }
}
