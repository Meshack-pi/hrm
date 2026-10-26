<?php

namespace Tests\Feature\Payroll;

use App\Models\Employee;
use App\Models\PayrollRun;
use App\Models\Payslip;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class PayrollBreakdownTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function makeUser(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function makePayslip(PayrollRun $run, string $number = 'EMP-0001'): Payslip
    {
        $employee = Employee::create([
            'employee_number' => $number, 'first_name' => 'Jane', 'last_name' => 'Wanjiru',
            'employment_type' => 'permanent', 'hire_date' => '2024-01-01', 'status' => 'active',
        ]);

        return Payslip::create([
            'payroll_run_id' => $run->id, 'employee_id' => $employee->id,
            'basic_salary' => 20000, 'gross_pay' => 20000, 'taxable_pay' => 17950,
            'nssf_employee' => 1200, 'shif' => 550, 'ahl_employee' => 300,
            'nssf_employer' => 1200, 'ahl_employer' => 300, 'nita_employer' => 50,
            'total_deductions' => 2050, 'net_pay' => 17950,
        ]);
    }

    /**
     * @param  array<string, string>  $query
     */
    private function partialBreakdown(User $user, PayrollRun $run, array $query): TestResponse
    {
        $version = $this->actingAs($user)->get(route('payroll.runs.show', $run))->viewData('page')['version'];

        return $this->actingAs($user)->get(route('payroll.runs.show', [$run, ...$query]), [
            'X-Inertia' => 'true',
            'X-Inertia-Version' => $version,
            'X-Inertia-Partial-Component' => 'Payroll/Runs/Show',
            'X-Inertia-Partial-Data' => 'breakdown',
        ]);
    }

    public function test_breakdown_loads_only_when_requested_with_take_home_and_cost_to_company(): void
    {
        $run = PayrollRun::create(['period_month' => 6, 'period_year' => 2026, 'status' => 'calculated']);
        $slip = $this->makePayslip($run);

        $this->actingAs($this->makeUser('Payroll Officer'))
            ->get(route('payroll.runs.show', [$run, 'payslip' => $slip->id]))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('canViewBreakdown', true)
                ->missing('breakdown')
                ->reloadOnly('breakdown', fn (AssertableInertia $reload) => $reload
                    ->where('breakdown.payslipId', $slip->id)
                    ->where('breakdown.employee.name', 'Jane Wanjiru')
                    ->where('breakdown.net', 17950)
                    ->where('breakdown.totalDeductions', 2050)
                    ->where('breakdown.employerTotal', 1550)
                    ->where('breakdown.costToCompany', 21550)
                    ->missing('breakdown.company')
                )
            );
    }

    public function test_payslip_from_another_run_is_not_found(): void
    {
        $run = PayrollRun::create(['period_month' => 6, 'period_year' => 2026]);
        $otherRun = PayrollRun::create(['period_month' => 5, 'period_year' => 2026]);
        $foreign = $this->makePayslip($otherRun);

        $this->partialBreakdown($this->makeUser('Payroll Officer'), $run, ['payslip' => $foreign->id])
            ->assertNotFound();
    }

    public function test_payroll_user_without_salary_access_cannot_load_breakdown(): void
    {
        $run = PayrollRun::create(['period_month' => 6, 'period_year' => 2026, 'status' => 'calculated']);
        $slip = $this->makePayslip($run);

        $approverOnly = User::factory()->create();
        $approverOnly->givePermissionTo('payroll.approve');

        $this->partialBreakdown($approverOnly, $run, ['payslip' => $slip->id])->assertForbidden();
    }
}
