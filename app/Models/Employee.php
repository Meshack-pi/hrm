<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Employee extends Model
{
    use HasUuids, LogsActivity, SoftDeletes;

    /** Statuses that still draw pay. Paid leave is handled per leave type, not by dropping the employee. */
    public const PAYABLE_STATUSES = ['active', 'on_leave'];

    protected $fillable = [
        'employee_number', 'first_name', 'middle_name', 'last_name',
        'national_id', 'passport_number', 'kra_pin', 'nssf_number', 'sha_number',
        'date_of_birth', 'gender', 'marital_status',
        'phone', 'personal_email', 'work_email', 'physical_address', 'county',
        'farm_id', 'department_id', 'job_title_id', 'manager_id',
        'employment_type', 'hire_date', 'probation_end_date', 'contract_end_date',
        'status', 'has_disability_exemption', 'disability_certificate_expiry', 'photo_path',
    ];

    /**
     * The integer id stays the primary key; only the public uuid column is generated.
     *
     * @return array<int, string>
     */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'hire_date' => 'date',
            'probation_end_date' => 'date',
            'contract_end_date' => 'date',
            'disability_certificate_expiry' => 'date',
            'has_disability_exemption' => 'boolean',
        ];
    }

    public function getFullNameAttribute(): string
    {
        return implode(' ', array_filter([$this->first_name, $this->middle_name, $this->last_name], fn ($p) => filled($p)));
    }

    public function farm()
    {
        return $this->belongsTo(Farm::class);
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function jobTitle()
    {
        return $this->belongsTo(JobTitle::class);
    }

    public function manager()
    {
        return $this->belongsTo(Employee::class, 'manager_id');
    }

    public function directReports()
    {
        return $this->hasMany(Employee::class, 'manager_id');
    }

    public function user()
    {
        return $this->hasOne(User::class);
    }

    public function nextOfKin()
    {
        return $this->hasMany(EmployeeNextOfKin::class);
    }

    public function documents()
    {
        return $this->hasMany(EmployeeDocument::class);
    }

    public function bankDetails()
    {
        return $this->hasMany(EmployeeBankDetail::class);
    }

    public function primaryBankDetail()
    {
        return $this->hasOne(EmployeeBankDetail::class)->where('is_primary', true);
    }

    public function salaries()
    {
        return $this->hasMany(EmployeeSalary::class);
    }

    public function currentSalary()
    {
        return $this->hasOne(EmployeeSalary::class)
            ->where('effective_from', '<=', now())
            ->where(fn ($q) => $q->whereNull('effective_to')->orWhere('effective_to', '>=', now()))
            ->orderByDesc('effective_from');
    }

    public function payComponents()
    {
        return $this->hasMany(EmployeePayComponent::class);
    }

    public function reliefs()
    {
        return $this->hasMany(EmployeeRelief::class);
    }

    public function payslips()
    {
        return $this->hasMany(Payslip::class);
    }

    public function leaveBalances()
    {
        return $this->hasMany(LeaveBalance::class);
    }

    public function leaveRequests()
    {
        return $this->hasMany(LeaveRequest::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['first_name', 'last_name', 'status', 'department_id', 'farm_id', 'employment_type'])
            ->logOnlyDirty();
    }
}
