<?php

namespace App\Services\Payroll;

use App\Models\Employee;
use App\Models\PayrollRun;

class PayrollValidator
{
    /**
     * Employment Act s.19(3): an employer may not deduct more than two-thirds
     * of an employee's wages in any one period.
     */
    public function checkTwoThirdsRule(PayrollRun $run): array
    {
        $breaches = [];

        foreach ($run->payslips()->with('employee')->get() as $payslip) {
            $gross = (float) $payslip->gross_pay;

            if ($gross <= 0) {
                continue;
            }

            $maxDeductible = $gross * (2 / 3);

            if ((float) $payslip->total_deductions > $maxDeductible) {
                $breaches[] = [
                    'employee_id' => $payslip->employee_id,
                    'employee_number' => $payslip->employee->employee_number,
                    'name' => $payslip->employee->full_name,
                    'gross_pay' => $gross,
                    'total_deductions' => (float) $payslip->total_deductions,
                    'max_deductible' => round($maxDeductible, 2),
                    'excess' => round((float) $payslip->total_deductions - $maxDeductible, 2),
                ];
            }
        }

        return $breaches;
    }

    public function checkNegativeNetPay(PayrollRun $run): array
    {
        return $run->payslips()
            ->with('employee')
            ->where('net_pay', '<', 0)
            ->get()
            ->map(fn ($p) => [
                'employee_id' => $p->employee_id,
                'employee_number' => $p->employee->employee_number,
                'name' => $p->employee->full_name,
                'net_pay' => (float) $p->net_pay,
            ])
            ->all();
    }

    /**
     * Employees missing statutory identifiers or payment details. These block
     * a clean payroll run because the statutory schedules and payment files
     * cannot be generated without them.
     */
    public function checkMissingDetails(): array
    {
        return Employee::whereIn('status', Employee::PAYABLE_STATUSES)
            ->where(function ($q) {
                $q->whereNull('kra_pin')
                    ->orWhereNull('nssf_number')
                    ->orWhereNull('sha_number')
                    ->orWhereDoesntHave('bankDetails');
            })
            ->get()
            ->map(fn ($e) => [
                'employee_id' => $e->id,
                'employee_number' => $e->employee_number,
                'name' => $e->full_name,
                'missing' => array_values(array_filter([
                    $e->kra_pin ? null : 'KRA PIN',
                    $e->nssf_number ? null : 'NSSF number',
                    $e->sha_number ? null : 'SHA number',
                    $e->bankDetails()->exists() ? null : 'payment details',
                ])),
            ])
            ->all();
    }

    public function validateForApproval(PayrollRun $run): array
    {
        return [
            'two_thirds_breaches' => $this->checkTwoThirdsRule($run),
            'negative_net_pay' => $this->checkNegativeNetPay($run),
        ];
    }

    public function canApprove(PayrollRun $run): bool
    {
        $issues = $this->validateForApproval($run);

        return empty($issues['two_thirds_breaches']) && empty($issues['negative_net_pay']);
    }
}
