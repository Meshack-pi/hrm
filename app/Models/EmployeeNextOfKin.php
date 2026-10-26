<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmployeeNextOfKin extends Model
{
    protected $table = 'employee_next_of_kin';

    protected $fillable = ['employee_id', 'name', 'relationship', 'phone', 'is_emergency_contact'];

    protected function casts(): array
    {
        return ['is_emergency_contact' => 'boolean'];
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}
