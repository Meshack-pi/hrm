<?php

namespace Tests\Feature\Payroll;

use App\Models\Employee;
use App\Models\PayrollRun;
use App\Models\Payslip;
use App\Services\Payroll\PayrollValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TwoThirdsRuleTest extends TestCase
{
    use RefreshDatabase;

    private PayrollValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new PayrollValidator;
    }

    private function makeEmployee(): Employee
    {
        return Employee::create([
            'employee_number' => 'EMP-0001',
            'first_name' => 'Jane',
            'last_name' => 'Wanjiru',
            'employment_type' => 'permanent',
            'hire_date' => '2024-01-01',
            'status' => 'active',
        ]);
    }

    private function makeRun(): PayrollRun
    {
        return PayrollRun::create([
            'period_month' => 6,
            'period_year' => 2026,
            'status' => 'calculated',
        ]);
    }

    private function makePayslip(PayrollRun $run, Employee $employee, float $gross, float $deductions): Payslip
    {
        return Payslip::create([
            'payroll_run_id' => $run->id,
            'employee_id' => $employee->id,
            'basic_salary' => $gross,
            'gross_pay' => $gross,
            'taxable_pay' => $gross,
            'total_deductions' => $deductions,
            'net_pay' => $gross - $deductions,
        ]);
    }

    public function test_deductions_within_two_thirds_pass(): void
    {
        $run = $this->makeRun();
        // 60% of gross deducted — under the two-thirds ceiling.
        $this->makePayslip($run, $this->makeEmployee(), 30000, 18000);

        $this->assertSame([], $this->validator->checkTwoThirdsRule($run));
        $this->assertTrue($this->validator->canApprove($run));
    }

    public function test_deductions_exceeding_two_thirds_are_flagged(): void
    {
        $run = $this->makeRun();
        // 70% of gross deducted — breaches the ceiling of 20,000.
        $this->makePayslip($run, $this->makeEmployee(), 30000, 21000);

        $breaches = $this->validator->checkTwoThirdsRule($run);

        $this->assertCount(1, $breaches);
        $this->assertEquals(20000.00, $breaches[0]['max_deductible']);
        $this->assertEquals(1000.00, $breaches[0]['excess']);
        $this->assertFalse($this->validator->canApprove($run));
    }

    public function test_deductions_exactly_two_thirds_pass(): void
    {
        $run = $this->makeRun();
        $this->makePayslip($run, $this->makeEmployee(), 30000, 20000);

        $this->assertSame([], $this->validator->checkTwoThirdsRule($run));
    }

    public function test_negative_net_pay_is_flagged(): void
    {
        $run = $this->makeRun();
        $employee = $this->makeEmployee();

        Payslip::create([
            'payroll_run_id' => $run->id,
            'employee_id' => $employee->id,
            'basic_salary' => 30000,
            'gross_pay' => 30000,
            'taxable_pay' => 30000,
            'total_deductions' => 35000,
            'net_pay' => -5000,
        ]);

        $flagged = $this->validator->checkNegativeNetPay($run);

        $this->assertCount(1, $flagged);
        $this->assertEquals(-5000.00, $flagged[0]['net_pay']);
        $this->assertFalse($this->validator->canApprove($run));
    }

    public function test_zero_gross_pay_is_skipped(): void
    {
        $run = $this->makeRun();
        $this->makePayslip($run, $this->makeEmployee(), 0, 0);

        $this->assertSame([], $this->validator->checkTwoThirdsRule($run));
    }
}
