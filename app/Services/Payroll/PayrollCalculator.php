<?php

namespace App\Services\Payroll;

use App\Models\Employee;
use App\Models\PayrollRun;
use App\Models\Payslip;
use App\Models\StatutoryRate;

class PayrollCalculator
{
    public function __construct(
        private NssfCalculator $nssf,
        private ShifCalculator $shif,
        private AhlCalculator $ahl,
        private PayeCalculator $paye,
    ) {}

    public function calculateRun(PayrollRun $run): void
    {
        $date = sprintf('%04d-%02d-01', $run->period_year, $run->period_month);
        $periodEnd = date('Y-m-t', strtotime($date));

        // Someone hired after the period has no salary in force for it; including them
        // produced a zero-basic payslip with SHIF's 300 minimum and negative net pay.
        $employees = Employee::whereIn('status', Employee::PAYABLE_STATUSES)
            ->where('hire_date', '<=', $periodEnd)
            ->with([
                'salaries' => fn ($q) => $q->where('effective_from', '<=', $date)
                    ->where(fn ($q) => $q->whereNull('effective_to')->orWhere('effective_to', '>=', $date))
                    ->orderByDesc('effective_from'),
                'payComponents' => fn ($q) => $q->where('effective_from', '<=', $date)
                    ->where(fn ($q) => $q->whereNull('effective_to')->orWhere('effective_to', '>=', $date)),
                'payComponents.payComponent',
                'reliefs' => fn ($q) => $q->where('effective_from', '<=', $date)
                    ->where(fn ($q) => $q->whereNull('effective_to')->orWhere('effective_to', '>=', $date)),
                'bankDetails' => fn ($q) => $q->where('is_primary', true),
            ])
            ->get();

        $variables = $run->variables()->with('payComponent')->get()->groupBy('employee_id');

        $totalGross = 0;
        $totalDeductions = 0;
        $totalNet = 0;
        $totalEmployerCost = 0;

        foreach ($employees as $employee) {
            $result = $this->calculateForEmployee($employee, $run, $date, $variables->get($employee->id, collect()));

            $totalGross += $result['gross_pay'];
            $totalDeductions += $result['total_deductions'];
            $totalNet += $result['net_pay'];
            $totalEmployerCost += $result['employer_cost'];
        }

        $run->update([
            'total_gross' => round($totalGross, 2),
            'total_deductions' => round($totalDeductions, 2),
            'total_net' => round($totalNet, 2),
            'total_employer_cost' => round($totalEmployerCost, 2),
            'status' => 'calculated',
            'calculated_by' => auth()->id(),
            'calculated_at' => now(),
        ]);
    }

    public function calculateForEmployee(Employee $employee, PayrollRun $run, string $date, $variables): array
    {
        $salary = $employee->salaries->first();
        $basicSalary = $salary ? (float) $salary->basic_salary : 0;

        // Recurring earnings
        $recurringEarnings = 0;
        $recurringDeductions = 0;
        $earningLines = [];
        $deductionLines = [];

        foreach ($employee->payComponents as $epc) {
            $component = $epc->payComponent;
            if (! $component || ! $component->is_active) {
                continue;
            }

            $amount = $component->calculation === 'percentage_of_basic'
                ? round($basicSalary * (float) $epc->amount_or_rate / 100, 2)
                : (float) $epc->amount_or_rate;

            if ($component->type === 'earning') {
                $recurringEarnings += $amount;
                $earningLines[] = ['component' => $component, 'amount' => $amount];
            } else {
                $recurringDeductions += $amount;
                $deductionLines[] = ['component' => $component, 'amount' => $amount];
            }
        }

        // One-off variable items
        $variableEarnings = 0;
        $variableDeductions = 0;

        foreach ($variables as $variable) {
            $component = $variable->payComponent;
            $amount = (float) $variable->amount;

            if ($component->type === 'earning') {
                $variableEarnings += $amount;
                $earningLines[] = ['component' => $component, 'amount' => $amount];
            } else {
                $variableDeductions += $amount;
                $deductionLines[] = ['component' => $component, 'amount' => $amount];
            }
        }

        // Unpaid leave deduction
        $unpaidDays = $this->getUnpaidLeaveDays($employee, $run);
        $workingDaysInMonth = 22; // Standard
        $unpaidDeduction = $unpaidDays > 0 ? round($basicSalary / $workingDaysInMonth * $unpaidDays, 2) : 0;

        $grossPay = round($basicSalary + $recurringEarnings + $variableEarnings - $unpaidDeduction, 2);

        // Statutory deductions
        $pensionablePay = $grossPay;
        $nssf = $this->nssf->calculate($pensionablePay, $date);
        $shifAmount = $this->shif->calculate($grossPay, $date);
        $ahlResult = $this->ahl->calculate($grossPay, $date);

        // Pension cap for tax deduction
        $pensionCap = StatutoryRate::active($date)->forCode('PENSION_CAP')->first();
        $maxPensionDeduction = $pensionCap ? (float) $pensionCap->max_amount : 30000;

        $additionalPension = $employee->reliefs
            ->where('type', 'pension')
            ->sum('monthly_premium_or_amount');

        $totalPension = $nssf['employee'] + $additionalPension;
        $deductiblePension = min($totalPension, $maxPensionDeduction);

        // Insurance premiums for relief
        $insurancePremiums = $employee->reliefs
            ->where('type', 'insurance')
            ->sum('monthly_premium_or_amount');

        // Taxable pay
        $taxablePay = $grossPay - $nssf['employee'] - $shifAmount - $ahlResult['employee'];

        // Additional pension deduction beyond NSSF (capped total)
        $extraPensionDeduction = min($additionalPension, $maxPensionDeduction - $nssf['employee']);
        if ($extraPensionDeduction > 0) {
            $taxablePay -= $extraPensionDeduction;
        }

        $taxablePay = max(0, round($taxablePay, 2));

        $payeResult = $this->paye->calculate(
            $taxablePay,
            $date,
            $insurancePremiums,
            $employee->has_disability_exemption
        );

        $otherDeductions = $recurringDeductions + $variableDeductions;
        $totalStatutoryDeductions = $nssf['employee'] + $shifAmount + $ahlResult['employee'] + $payeResult['paye'];
        $totalDeductions = $totalStatutoryDeductions + $otherDeductions;

        $netPay = round($grossPay - $totalDeductions, 2);

        // Employer costs
        $nitaRate = StatutoryRate::active($date)->forCode('NITA')->first();
        $nitaAmount = $nitaRate ? (float) $nitaRate->fixed_amount : 50;
        $employerCost = round($grossPay + $nssf['employer'] + $ahlResult['employer'] + $nitaAmount, 2);

        // Build snapshot
        $bankDetail = $employee->bankDetails->first();
        $snapshot = [
            'name' => $employee->full_name,
            'employee_number' => $employee->employee_number,
            'kra_pin' => $employee->kra_pin,
            'nssf_number' => $employee->nssf_number,
            'sha_number' => $employee->sha_number,
            'payment_method' => $bankDetail?->payment_method,
            'bank_name' => $bankDetail?->bank_name,
            'account_number' => $bankDetail?->account_number,
            'mpesa_number' => $bankDetail?->mpesa_number,
        ];

        // Create or update payslip
        $payslip = Payslip::updateOrCreate(
            ['payroll_run_id' => $run->id, 'employee_id' => $employee->id],
            [
                'basic_salary' => $basicSalary,
                'gross_pay' => $grossPay,
                'taxable_pay' => $taxablePay,
                'nssf_employee' => $nssf['employee'],
                'nssf_employer' => $nssf['employer'],
                'shif' => $shifAmount,
                'ahl_employee' => $ahlResult['employee'],
                'ahl_employer' => $ahlResult['employer'],
                'nita_employer' => $nitaAmount,
                'paye_before_relief' => $payeResult['paye_before_relief'],
                'personal_relief' => $payeResult['personal_relief'],
                'insurance_relief' => $payeResult['insurance_relief'],
                'paye' => $payeResult['paye'],
                'total_other_deductions' => round($otherDeductions, 2),
                'total_deductions' => round($totalDeductions, 2),
                'net_pay' => $netPay,
                'unpaid_leave_days' => $unpaidDays,
                'employee_snapshot' => $snapshot,
            ]
        );

        // Payslip lines
        $payslip->lines()->delete();

        // Earning lines
        $payslip->lines()->create([
            'name' => 'Basic Salary',
            'type' => 'earning',
            'amount' => $basicSalary,
            'is_statutory' => false,
        ]);

        foreach ($earningLines as $line) {
            $payslip->lines()->create([
                'pay_component_id' => $line['component']->id,
                'name' => $line['component']->name,
                'type' => 'earning',
                'amount' => $line['amount'],
                'is_statutory' => $line['component']->is_statutory,
            ]);
        }

        if ($unpaidDeduction > 0) {
            $payslip->lines()->create([
                'name' => 'Unpaid Leave Deduction',
                'type' => 'deduction',
                'amount' => $unpaidDeduction,
                'is_statutory' => false,
            ]);
        }

        // Statutory deduction lines
        $statutoryLines = [
            ['name' => 'NSSF', 'amount' => $nssf['employee']],
            ['name' => 'SHIF', 'amount' => $shifAmount],
            ['name' => 'Housing Levy', 'amount' => $ahlResult['employee']],
            ['name' => 'PAYE', 'amount' => $payeResult['paye']],
        ];

        foreach ($statutoryLines as $line) {
            $payslip->lines()->create([
                'name' => $line['name'],
                'type' => 'deduction',
                'amount' => $line['amount'],
                'is_statutory' => true,
            ]);
        }

        // Other deduction lines
        foreach ($deductionLines as $line) {
            $payslip->lines()->create([
                'pay_component_id' => $line['component']->id,
                'name' => $line['component']->name,
                'type' => 'deduction',
                'amount' => $line['amount'],
                'is_statutory' => $line['component']->is_statutory,
            ]);
        }

        return [
            'gross_pay' => $grossPay,
            'total_deductions' => $totalDeductions,
            'net_pay' => $netPay,
            'employer_cost' => $employerCost,
        ];
    }

    private function getUnpaidLeaveDays(Employee $employee, PayrollRun $run): float
    {
        $startOfMonth = sprintf('%04d-%02d-01', $run->period_year, $run->period_month);
        $endOfMonth = date('Y-m-t', strtotime($startOfMonth));

        return (float) $employee->leaveRequests()
            ->where('status', 'approved')
            ->whereHas('leaveType', fn ($q) => $q->where('is_paid', false))
            ->where('start_date', '<=', $endOfMonth)
            ->where('end_date', '>=', $startOfMonth)
            ->sum('days');
    }
}
