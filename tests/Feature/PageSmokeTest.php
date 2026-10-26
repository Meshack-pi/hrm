<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Farm;
use App\Models\PayrollRun;
use App\Models\User;
use Database\Seeders\LeaveTypesSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PageSmokeTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(LeaveTypesSeeder::class);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('Super Admin');
    }

    public static function pageProvider(): array
    {
        return [
            'dashboard' => ['dashboard', 'Dashboard'],
            'users index' => ['users.index', 'Users/Index'],
            'users create' => ['users.create', 'Users/Create'],
            'employees index' => ['employees.index', 'Employees/Index'],
            'employees create' => ['employees.create', 'Employees/Create'],
            'farms index' => ['settings.farms.index', 'Settings/Farms/Index'],
            'departments index' => ['settings.departments.index', 'Settings/Departments/Index'],
            'payroll runs index' => ['payroll.runs.index', 'Payroll/Runs/Index'],
            'payroll runs create' => ['payroll.runs.create', 'Payroll/Runs/Create'],
            'leave requests index' => ['leave.requests.index', 'Leave/Requests/Index'],
        ];
    }

    #[DataProvider('pageProvider')]
    public function test_page_renders(string $routeName, string $component): void
    {
        $this->actingAs($this->admin)
            ->get(route($routeName))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->component($component));
    }

    public function test_employee_show_renders(): void
    {
        $farm = Farm::create(['name' => 'Kosachei Farm', 'code' => 'KOS']);

        $employee = Employee::create([
            'employee_number' => 'EMP-0001',
            'first_name' => 'Jane',
            'last_name' => 'Wanjiru',
            'farm_id' => $farm->id,
            'employment_type' => 'permanent',
            'hire_date' => '2024-01-01',
            'status' => 'active',
        ]);

        $this->actingAs($this->admin)
            ->get(route('employees.show', $employee))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Employees/Show')
                ->where('employee.employee_number', 'EMP-0001')
            );
    }

    public function test_payroll_run_show_renders(): void
    {
        $run = PayrollRun::create([
            'period_month' => 6,
            'period_year' => 2026,
            'status' => 'draft',
        ]);

        $this->actingAs($this->admin)
            ->get(route('payroll.runs.show', $run))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->component('Payroll/Runs/Show'));
    }

    public function test_shared_auth_props_include_permissions(): void
    {
        $this->actingAs($this->admin)
            ->get(route('dashboard'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('auth.permissions')
                ->has('auth.roles')
                ->where('auth.user.id', $this->admin->id)
            );
    }

    public function test_guest_is_redirected_from_protected_pages(): void
    {
        $this->get(route('employees.index'))->assertRedirect(route('login'));
        $this->get(route('payroll.runs.index'))->assertRedirect(route('login'));
    }
}
