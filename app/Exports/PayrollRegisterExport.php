<?php

namespace App\Exports;

use App\Models\PayrollRun;
use App\Models\Payslip;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;

class PayrollRegisterExport implements FromQuery, ShouldAutoSize, WithHeadings, WithMapping, WithTitle
{
    public function __construct(private PayrollRun $run, private ?int $farmId = null) {}

    public function query(): Builder
    {
        return Payslip::query()
            ->with('employee.farm:id,name')
            ->where('payroll_run_id', $this->run->id)
            ->when($this->farmId, fn ($q) => $q->whereHas('employee', fn ($e) => $e->where('farm_id', $this->farmId)))
            ->orderBy('employee_id');
    }

    public function title(): string
    {
        return sprintf('Payroll %04d-%02d', $this->run->period_year, $this->run->period_month);
    }

    public function headings(): array
    {
        return [
            'Employee No', 'Name', 'Farm', 'KRA PIN', 'Basic', 'Gross Pay', 'Taxable Pay',
            'NSSF (EE)', 'SHIF', 'AHL (EE)', 'PAYE', 'Other Deductions', 'Total Deductions', 'Net Pay',
            'NSSF (ER)', 'AHL (ER)', 'NITA', 'Payment Status',
        ];
    }

    public function map(mixed $p): array
    {
        // Name and KRA PIN come from the snapshot so the register matches what was paid,
        // even if the employee record has changed since.
        $s = $p->employee_snapshot ?? [];

        return [
            $s['employee_number'] ?? $p->employee?->employee_number,
            $s['name'] ?? $p->employee?->full_name,
            $p->employee?->farm?->name,
            $s['kra_pin'] ?? $p->employee?->kra_pin,
            $p->basic_salary, $p->gross_pay, $p->taxable_pay,
            $p->nssf_employee, $p->shif, $p->ahl_employee, $p->paye,
            $p->total_other_deductions, $p->total_deductions, $p->net_pay,
            $p->nssf_employer, $p->ahl_employer, $p->nita_employer,
            $p->payment_status,
        ];
    }
}
