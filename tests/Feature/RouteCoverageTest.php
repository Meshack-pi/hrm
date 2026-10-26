<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\PayrollRun;
use App\Models\Payslip;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class RouteCoverageTest extends TestCase
{
    use RefreshDatabase;

    // Not pages: framework endpoints, signed links, or routes covered by Breeze's own tests.
    // verification.notice redirects already-verified users, and reports.index redirects to a section;
    // both redirects are asserted in their own tests.
    private const SKIP = [
        'sanctum.csrf-cookie', 'storage.local', 'verification.verify', 'verification.notice', 'reports.index', 'password.reset',
    ];

    private const GUEST_ONLY = ['login', 'register', 'password.request'];

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->admin = User::where('email', 'admin@yegenfarms.co.ke')->firstOrFail();
    }

    private function params(): array
    {
        $employee = Employee::create([
            'employee_number' => 'EMP-0001', 'first_name' => 'Jane', 'last_name' => 'Wanjiru',
            'employment_type' => 'permanent', 'hire_date' => '2024-01-01', 'status' => 'active',
        ]);

        $run = PayrollRun::create(['period_month' => 6, 'period_year' => 2026]);

        return [
            'employee' => $employee->uuid,
            'user' => $this->admin->id,
            'run' => $run->id,
            'payslip' => Payslip::create([
                'payroll_run_id' => $run->id, 'employee_id' => $employee->id,
                'basic_salary' => 20000, 'gross_pay' => 20000, 'taxable_pay' => 17950,
                'nssf_employee' => 1200, 'shif' => 550, 'ahl_employee' => 300,
                'total_deductions' => 2050, 'net_pay' => 17950,
            ])->id,
        ];
    }

    public function test_every_get_route_renders_for_super_admin(): void
    {
        $params = $this->params();
        $checked = [];

        foreach (Route::getRoutes() as $route) {
            $name = $route->getName();

            if (! in_array('GET', $route->methods()) || ! $name || in_array($name, self::SKIP)) {
                continue;
            }

            $url = route($name, array_intersect_key($params, array_flip($route->parameterNames())));

            if (in_array($name, self::GUEST_ONLY)) {
                $response = $this->get($url);
            } else {
                $response = $this->actingAs($this->admin)->get($url);
            }

            $this->assertContains(
                $response->getStatusCode(),
                [200],
                "GET {$url} ({$name}) returned {$response->getStatusCode()}"
                    .(($response->exception ?? null) ? ': '.$response->exception->getMessage() : '')
            );

            $checked[] = $name;
            $this->app['auth']->forgetGuards();
        }

        $this->assertGreaterThan(15, count($checked));
    }

    public function test_plain_employee_is_blocked_from_payroll_and_settings(): void
    {
        $params = $this->params();

        $user = User::factory()->create(['employee_id' => Employee::where('uuid', $params['employee'])->value('id')]);
        $user->assignRole('Employee');

        foreach ([
            route('payroll.runs.index'),
            route('payroll.runs.create'),
            route('payroll.runs.show', $params['run']),
            route('settings.farms.index'),
            route('settings.departments.index'),
            route('users.index'),
        ] as $url) {
            $this->actingAs($user)->get($url)->assertForbidden();
        }

        $this->actingAs($user)->post(route('settings.farms.store'), [
            'name' => 'Rogue Farm', 'code' => 'RGE',
        ])->assertForbidden();

        $this->actingAs($user)->delete(route('payroll.runs.destroy', $params['run']))->assertForbidden();
    }

    public function test_payroll_roles_can_open_payroll(): void
    {
        $params = $this->params();

        foreach (['Payroll Officer', 'Finance Approver', 'Auditor'] as $role) {
            $user = User::factory()->create();
            $user->assignRole($role);

            $this->actingAs($user)->get(route('payroll.runs.index'))->assertOk();
            $this->actingAs($user)->get(route('payroll.runs.show', $params['run']))->assertOk();
        }
    }
}
