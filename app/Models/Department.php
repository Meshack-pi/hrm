<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Department extends Model
{
    use LogsActivity;

    protected $fillable = ['name', 'code', 'head_employee_id'];

    public function head()
    {
        return $this->belongsTo(Employee::class, 'head_employee_id');
    }

    public function jobTitles()
    {
        return $this->hasMany(JobTitle::class);
    }

    public function employees()
    {
        return $this->hasMany(Employee::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['name', 'code', 'head_employee_id'])->logOnlyDirty();
    }
}
