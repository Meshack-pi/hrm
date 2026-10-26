<?php

namespace App\Exports;

use App\Models\Employee;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class EmployeesExport implements FromQuery, ShouldAutoSize, WithHeadings, WithMapping
{
    public function __construct(private ?int $farmId = null) {}

    public function query(): Builder
    {
        return Employee::query()
            ->with(['farm:id,name', 'department:id,name', 'jobTitle:id,name'])
            ->when($this->farmId, fn ($q) => $q->where('farm_id', $this->farmId))
            ->orderBy('employee_number');
    }

    public function headings(): array
    {
        return [
            'Employee No', 'First Name', 'Middle Name', 'Last Name', 'Farm', 'Department', 'Job Title',
            'Employment Type', 'Status', 'Hire Date', 'Phone', 'KRA PIN', 'NSSF No', 'SHA No',
        ];
    }

    public function map(mixed $e): array
    {
        return [
            $e->employee_number, $e->first_name, $e->middle_name, $e->last_name,
            $e->farm?->name, $e->department?->name, $e->jobTitle?->name,
            $e->employment_type, $e->status, $e->hire_date?->toDateString(), $e->phone,
            $e->kra_pin, $e->nssf_number, $e->sha_number,
        ];
    }
}
