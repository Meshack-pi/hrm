<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Farm;
use App\Models\LeaveRequest;
use App\Models\PayrollRun;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Runs the real demo seeder, then checks the dashboard reports what is actually
 * in the database for each role.
 */
class DashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->seed(DemoDataSeeder::class);
    }

    private function as(string $email)
    {
        return $this->actingAs(User::where('email', "{$email}@yegenfarms.co.ke")->firstOrFail());
    }

    public function test_admin_dashboard_matches_seeded_data(): void
    {
        $this->as('admin')->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p
                ->component('Dashboard')
                ->where('stats.employees.total', Employee::count())
                ->where('stats.employees.active', Employee::where('status', 'active')->count())
                ->where('stats.employees.onLeave', Employee::where('status', 'on_leave')->count())
                ->where('stats.employees.scope', 'company')
                ->where('stats.farms.active', Farm::where('is_active', true)->count())
                ->where('stats.pendingLeave', LeaveRequest::where('status', 'pending')->count())
                ->where('stats.users.total', User::count())
                ->where('payroll.run.status', 'calculated')
                ->where('payroll.run.payslips', PayrollRun::latest('id')->first()->payslips()->count())
                ->where('payroll.run.net', (float) PayrollRun::latest('id')->first()->total_net)
                ->has('pendingApprovals', min(5, LeaveRequest::where('status', 'pending')->count()))
                ->where('me', null)
            );
    }

    public function test_finance_is_told_the_run_awaits_their_approval(): void
    {
        $this->as('finance')->get(route('dashboard'))
            ->assertInertia(fn (AssertableInertia $p) => $p
                ->where('payroll.awaitingMyApproval', true)
                ->where('payroll.awaitingOtherApprover', false)
            );
    }

    public function test_payroll_officer_who_calculated_cannot_be_prompted_to_approve(): void
    {
        $this->as('payroll')->get(route('dashboard'))
            ->assertInertia(fn (AssertableInertia $p) => $p
                ->where('payroll.awaitingMyApproval', false)
                ->where('payroll.awaitingOtherApprover', true)
                ->where('stats.users', null)
            );
    }

    public function test_line_manager_sees_only_their_team(): void
    {
        $manager = User::where('email', 'manager@yegenfarms.co.ke')->first();
        $teamSize = Employee::where('manager_id', $manager->employee_id)->count();

        $this->assertGreaterThan(0, $teamSize);

        $this->as('manager')->get(route('dashboard'))
            ->assertInertia(fn (AssertableInertia $p) => $p
                ->where('stats.employees.total', $teamSize)
                ->where('stats.employees.scope', 'team')
                ->where('stats.farms', null)
                ->where('payroll', null)
                ->where('contractsEnding', null)
            );
    }

    public function test_employee_sees_only_their_own_leave_and_approved_payslip(): void
    {
        $user = User::where('email', 'employee@yegenfarms.co.ke')->first();
        $paidRun = PayrollRun::where('status', 'paid')->orderByDesc('period_year')->orderByDesc('period_month')->first();
        $payslip = $paidRun->payslips()->where('employee_id', $user->employee_id)->first();

        $this->as('employee')->get(route('dashboard'))
            ->assertInertia(fn (AssertableInertia $p) => $p
                ->where('stats.employees', null)
                ->where('stats.pendingLeave', null)
                ->where('payroll', null)
                ->where('pendingApprovals', null)
                ->where('onLeaveToday', null)
                // The newer run is only calculated, so the paid one is the latest visible payslip.
                ->where('me.latestPayslip.period', $paidRun->period_label)
                ->where('me.latestPayslip.net', (float) $payslip->net_pay)
                ->where('me.latestPayslip.paymentStatus', 'paid')
                ->has('me.leaveBalances')
            );
    }

    public function test_admin_charts_reflect_payroll_runs_and_headcount(): void
    {
        $runs = PayrollRun::where('status', '!=', 'draft')->orderByDesc('period_year')->orderByDesc('period_month')->limit(12)->get();
        $currentStaff = Employee::where('status', '!=', 'inactive')->whereDate('hire_date', '<=', today()->endOfMonth())->count();

        $this->as('admin')->get(route('dashboard'))
            ->assertInertia(fn (AssertableInertia $p) => $p
                ->has('charts.payrollTrend', $runs->count())
                // Oldest first, so the newest run is the last point on the line.
                ->where('charts.payrollTrend.'.($runs->count() - 1).'.net', (float) $runs->first()->total_net)
                ->has('charts.headcountTrend', 12)
                ->where('charts.headcountTrend.11.employees', $currentStaff)
                ->has('charts.leaveTrend', 12)
                ->where('charts.byDepartment', fn ($departments) => collect($departments)->sum('value') === Employee::whereIn('status', Employee::PAYABLE_STATUSES)->count())
            );
    }

    public function test_line_manager_charts_are_scoped_to_their_team_without_payroll(): void
    {
        $manager = User::where('email', 'manager@yegenfarms.co.ke')->first();
        $teamSize = Employee::where('manager_id', $manager->employee_id)->where('status', '!=', 'inactive')
            ->whereDate('hire_date', '<=', today()->endOfMonth())->count();

        $this->as('manager')->get(route('dashboard'))
            ->assertInertia(fn (AssertableInertia $p) => $p
                ->where('charts.payrollTrend', null)
                ->where('charts.headcountTrend.11.employees', $teamSize)
            );
    }

    public function test_employee_sees_no_company_charts(): void
    {
        $this->as('employee')->get(route('dashboard'))
            ->assertInertia(fn (AssertableInertia $p) => $p
                ->where('charts.payrollTrend', null)
                ->where('charts.headcountTrend', null)
                ->where('charts.leaveTrend', null)
                ->where('charts.byDepartment', null)
            );
    }
}
