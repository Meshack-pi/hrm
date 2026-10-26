<?php

namespace App\Services\Payroll;

use App\Models\Payslip;

/**
 * Shapes a stored payslip into what the printable payslip shows. Figures come
 * from the payslip and its lines exactly as calculated — nothing is recomputed.
 */
class PayslipDocument
{
    public const UNPAID_LEAVE_LINE = 'Unpaid Leave Deduction';

    private const STATUTORY_ORDER = ['PAYE', 'NSSF', 'SHIF', 'Housing Levy'];

    public function build(Payslip $payslip): array
    {
        $payslip->loadMissing(['lines', 'payrollRun', 'employee.farm', 'employee.department', 'employee.jobTitle']);

        $run = $payslip->payrollRun;
        $employee = $payslip->employee;
        $snapshot = $payslip->employee_snapshot ?? [];
        $lines = $payslip->lines;

        $earnings = $lines->where('type', 'earning')
            ->map(fn ($l) => ['name' => $l->name, 'amount' => (float) $l->amount])->values();

        $unpaidLeave = (float) $lines->where('name', self::UNPAID_LEAVE_LINE)->sum('amount');

        $statutory = $lines->where('type', 'deduction')->where('is_statutory', true)
            ->sortBy(fn ($l) => array_search($l->name, self::STATUTORY_ORDER) === false ? 99 : array_search($l->name, self::STATUTORY_ORDER))
            ->map(fn ($l) => ['name' => $this->statutoryLabel($l->name), 'amount' => (float) $l->amount])->values();

        $other = $lines->where('type', 'deduction')->where('is_statutory', false)
            ->where('name', '!=', self::UNPAID_LEAVE_LINE)
            ->map(fn ($l) => ['name' => $l->name, 'amount' => (float) $l->amount])->values();

        return [
            'company' => [
                'name' => 'Yegen Farms',
                'logo' => public_path('images/logo.png'),
            ],
            'period' => $run->period_label,
            'periodStart' => sprintf('%04d-%02d-01', $run->period_year, $run->period_month),
            'runStatus' => $run->status,
            'employee' => [
                'name' => $snapshot['name'] ?? $employee?->full_name,
                'number' => $snapshot['employee_number'] ?? $employee?->employee_number,
                'kraPin' => $snapshot['kra_pin'] ?? $employee?->kra_pin,
                'nssf' => $snapshot['nssf_number'] ?? $employee?->nssf_number,
                'sha' => $snapshot['sha_number'] ?? $employee?->sha_number,
                'farm' => $employee?->farm?->name,
                'department' => $employee?->department?->name,
                'jobTitle' => $employee?->jobTitle?->name,
            ],
            'payment' => [
                'method' => ($snapshot['payment_method'] ?? null) === 'mpesa' ? 'M-Pesa' : (isset($snapshot['payment_method']) ? 'Bank transfer' : null),
                'bank' => $snapshot['bank_name'] ?? null,
                'account' => $this->mask(($snapshot['payment_method'] ?? null) === 'mpesa' ? ($snapshot['mpesa_number'] ?? null) : ($snapshot['account_number'] ?? null)),
            ],
            'earnings' => $earnings,
            'unpaidLeave' => ['days' => (float) $payslip->unpaid_leave_days, 'amount' => $unpaidLeave],
            'gross' => (float) $payslip->gross_pay,
            'statutory' => $statutory,
            'statutoryTotal' => round($statutory->sum('amount'), 2),
            'other' => $other,
            'otherTotal' => round($other->sum('amount'), 2),
            'totalDeductions' => (float) $payslip->total_deductions,
            'net' => (float) $payslip->net_pay,
            'tax' => [
                'gross' => (float) $payslip->gross_pay,
                'nssf' => (float) $payslip->nssf_employee,
                'shif' => (float) $payslip->shif,
                'ahl' => (float) $payslip->ahl_employee,
                'taxable' => (float) $payslip->taxable_pay,
                'payeBeforeRelief' => (float) $payslip->paye_before_relief,
                'personalRelief' => (float) $payslip->personal_relief,
                'insuranceRelief' => (float) $payslip->insurance_relief,
                'paye' => (float) $payslip->paye,
            ],
            'employer' => [
                ['name' => 'NSSF (employer share)', 'amount' => (float) $payslip->nssf_employer],
                ['name' => 'Housing Levy (employer share)', 'amount' => (float) $payslip->ahl_employer],
                ['name' => 'NITA levy', 'amount' => (float) $payslip->nita_employer],
            ],
            'generatedAt' => now(),
        ];
    }

    public function filename(Payslip $payslip): string
    {
        $payslip->loadMissing('payrollRun', 'employee');

        return sprintf('payslip-%s-%04d-%02d.pdf',
            strtolower($payslip->employee?->employee_number ?? $payslip->employee_id),
            $payslip->payrollRun->period_year, $payslip->payrollRun->period_month);
    }

    /** Totals for this employee from January up to and including this payslip's month, approved runs only. */
    private function statutoryLabel(string $name): string
    {
        return match ($name) {
            'PAYE' => 'PAYE (income tax)',
            'NSSF' => 'NSSF pension',
            'SHIF' => 'SHIF (health insurance)',
            'Housing Levy' => 'Affordable Housing Levy',
            default => $name,
        };
    }

    private function mask(?string $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        return str_repeat('•', max(strlen($value) - 4, 0)).substr($value, -4);
    }
}
