<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\EmployeeSalary;
use App\Models\Farm;
use App\Models\PayrollRun;
use App\Models\User;
use App\Services\Payroll\PayrollCalculator;
use Database\Seeders\LeaveTypesSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\StatutoryRatesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class ReportsTest extends TestCase
{
    use RefreshDatabase;

    private Farm $kos;

    private Farm $sug;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolesAndPermissionsSeeder::class, StatutoryRatesSeeder::class, LeaveTypesSeeder::class]);

        $this->kos = Farm::create(['name' => 'Kosachei Farm', 'code' => 'KOS']);
        $this->sug = Farm::create(['name' => 'Sugoi Farm', 'code' => 'SUG']);
    }

    private function employee(Farm $farm, float $basic, string $hireDate = '2024-01-01', string $status = 'active'): Employee
    {
        static $n = 0;
        $n++;

        $e = Employee::create([
            'employee_number' => sprintf('EMP-%04d', $n), 'first_name' => "Worker{$n}", 'last_name' => 'Test',
            'farm_id' => $farm->id, 'employment_type' => 'permanent', 'hire_date' => $hireDate, 'status' => $status,
        ]);
        EmployeeSalary::create(['employee_id' => $e->id, 'basic_salary' => $basic, 'effective_from' => $hireDate]);

        return $e;
    }

    private function user(string $role): User
    {
        $u = User::factory()->create();
        $u->assignRole($role);

        return $u;
    }

    private function calculatedRun(int $month = 9): PayrollRun
    {
        $run = PayrollRun::create(['period_month' => $month, 'period_year' => 2026]);
        $this->actingAs($this->user('Payroll Officer'));
        app(PayrollCalculator::class)->calculateRun($run);

        return $run->fresh();
    }

    public function test_index_redirects_to_first_section_the_user_may_open(): void
    {
        $this->actingAs($this->user('Super Admin'))->get(route('reports.index'))
            ->assertRedirect(route('reports.headcount'));

        // Payroll officers have no headcount access, so they land on payroll.
        $this->actingAs($this->user('Payroll Officer'))->get(route('reports.index'))
            ->assertRedirect(route('reports.payroll'));

        $this->actingAs($this->user('Employee'))->get(route('reports.index'))->assertForbidden();
    }

    public function test_sections_are_shared_per_role(): void
    {
        $this->actingAs($this->user('Payroll Officer'))->get(route('dashboard'))
            ->assertInertia(fn (AssertableInertia $p) => $p
                ->where('reportSections.0.key', 'payroll')
                ->where('reportSections.1.key', 'statutory')
                ->has('reportSections', 2)
            );

        $this->actingAs($this->user('Super Admin'))->get(route('dashboard'))
            ->assertInertia(fn (AssertableInertia $p) => $p->has('reportSections', 5));

        $this->actingAs($this->user('Employee'))->get(route('dashboard'))
            ->assertInertia(fn (AssertableInertia $p) => $p->has('reportSections', 0));
    }

    public function test_headcount_is_broken_down_and_filterable_by_farm(): void
    {
        $this->employee($this->kos, 20000);
        $this->employee($this->kos, 30000);
        $this->employee($this->sug, 100000);
        $admin = $this->user('Super Admin');

        $this->actingAs($admin)->get(route('reports.headcount'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p
                ->component('Reports/Headcount')
                ->where('summary.total', 3)
                ->has('byFarm', 2)
                ->where('byFarm.0.farm', 'Kosachei Farm')
                ->where('byFarm.0.permanent', 2)
            );

        $this->actingAs($admin)->get(route('reports.headcount', ['farm_id' => $this->sug->id]))
            ->assertInertia(fn (AssertableInertia $p) => $p->where('summary.total', 1)->has('byFarm', 1));
    }

    public function test_payroll_report_compares_with_previous_month(): void
    {
        $this->employee($this->kos, 20000);
        $this->employee($this->sug, 100000);
        $this->calculatedRun(8);
        $this->employee($this->sug, 30000);
        $run = $this->calculatedRun(9);

        $this->actingAs($this->user('Super Admin'))
            ->get(route('reports.payroll', ['run_id' => $run->id]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p
                ->component('Reports/Payroll')
                ->where('totals.headcount', 3)
                ->where('totals.gross', 150000)
                ->where('previous.headcount', 2)
                ->where('previous.gross', 120000)
                ->has('byFarm', 2)
                ->where('byFarm.0.name', 'Sugoi Farm')
                ->where('byFarm.0.gross', 130000)
                ->where('paymentStatus.0.status', 'pending')
            );
    }

    public function test_statutory_report_totals_remittances(): void
    {
        $this->employee($this->kos, 20000);
        $this->employee($this->kos, 30000);
        $this->employee($this->sug, 100000);
        $run = $this->calculatedRun();

        $this->actingAs($this->user('Payroll Officer'))
            ->get(route('reports.statutory', ['run_id' => $run->id]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p
                ->component('Reports/Statutory')
                // NSSF on 20k + 30k + 100k = 1,200 + 1,800 + 6,000 on each side.
                ->where('remittances.1.label', 'NSSF')
                ->where('remittances.1.employee', 9000)
                ->where('remittances.1.employer', 9000)
                ->where('dueDate', '2026-10-09')
                ->has('byFarm', 2)
            );
    }

    public function test_audit_log_lists_changes_with_before_and_after(): void
    {
        $e = $this->employee($this->kos, 20000);
        $e->update(['status' => 'suspended']);

        $this->actingAs($this->user('Super Admin'))
            ->get(route('reports.audit', ['subject' => 'Employee', 'event' => 'updated']))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p
                ->component('Reports/Audit')
                ->where('entries.total', 1)
                ->where('entries.data.0.subject', 'Employee')
                ->where('entries.data.0.changes.0.field', 'status')
                ->where('entries.data.0.changes.0.old', 'active')
                ->where('entries.data.0.changes.0.new', 'suspended')
            );
    }

    public function test_section_permissions(): void
    {
        $hr = $this->user('HR Manager');
        $payroll = $this->user('Payroll Officer');
        $manager = $this->user('Line Manager');

        $this->actingAs($payroll)->get(route('reports.headcount'))->assertForbidden();
        $this->actingAs($payroll)->get(route('reports.audit'))->assertForbidden();
        $this->actingAs($payroll)->get(route('reports.payroll'))->assertOk();

        $this->actingAs($hr)->get(route('reports.audit'))->assertOk();

        foreach (['headcount', 'payroll', 'statutory', 'leave', 'audit'] as $s) {
            $this->actingAs($manager)->get(route("reports.{$s}"))->assertForbidden();
        }
    }

    public function test_exports_download_and_respect_permissions(): void
    {
        $this->employee($this->kos, 20000);
        $run = $this->calculatedRun();
        $admin = $this->user('Super Admin');

        $this->actingAs($admin)->get(route('reports.export.employees', ['format' => 'csv']))
            ->assertOk()->assertDownload('employees-'.now()->format('Ymd').'.csv');

        $this->actingAs($admin)->get(route('reports.export.payroll-register', ['run' => $run->id]))
            ->assertOk()->assertDownload('payroll-register-2026-09.xlsx');

        $this->actingAs($this->user('HR Manager'))
            ->get(route('reports.export.payroll-register', ['run' => $run->id]))
            ->assertForbidden();
    }

    public function test_payroll_skips_hires_after_the_period_and_pays_staff_on_leave(): void
    {
        $existing = $this->employee($this->kos, 20000);
        $onLeave = $this->employee($this->kos, 30000, '2024-01-01', 'on_leave');
        $futureHire = $this->employee($this->kos, 25000, '2026-10-15');

        $run = $this->calculatedRun();
        $paidIds = $run->payslips()->pluck('employee_id')->all();

        $this->assertContains($existing->id, $paidIds);
        $this->assertContains($onLeave->id, $paidIds, 'An employee on leave must still be paid.');
        $this->assertNotContains($futureHire->id, $paidIds, 'Someone hired after September has no September pay.');
        $this->assertSame(0, $run->payslips()->where('net_pay', '<', 0)->count());
    }
}
