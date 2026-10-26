<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class EmployeeEditModalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function makeEmployee(string $number = 'EMP-0001', ?int $managerId = null): Employee
    {
        return Employee::create([
            'employee_number' => $number,
            'first_name' => 'Jane',
            'last_name' => 'Wanjiru',
            'employment_type' => 'permanent',
            'hire_date' => '2024-01-01',
            'status' => 'active',
            'manager_id' => $managerId,
        ]);
    }

    private function makeUser(string $role, ?Employee $employee = null): User
    {
        $user = User::factory()->create(['employee_id' => $employee?->id]);
        $user->assignRole($role);

        return $user;
    }

    public function test_employee_urls_use_the_uuid_and_not_the_numeric_id(): void
    {
        $employee = $this->makeEmployee();
        $hr = $this->makeUser('HR Manager');

        $this->assertSame(url("/employees/{$employee->uuid}"), route('employees.show', $employee));

        $this->actingAs($hr)->get(route('employees.show', $employee))->assertOk();
        $this->actingAs($hr)->get("/employees/{$employee->id}")->assertNotFound();
    }

    public function test_edit_page_route_no_longer_exists(): void
    {
        $employee = $this->makeEmployee();

        $this->actingAs($this->makeUser('HR Manager'))
            ->get("/employees/{$employee->uuid}/edit")
            ->assertNotFound();
    }

    public function test_show_loads_edit_form_only_when_requested(): void
    {
        $employee = $this->makeEmployee();

        $this->actingAs($this->makeUser('HR Manager'))
            ->get(route('employees.show', $employee))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Employees/Show')
                ->missing('editForm')
                ->reloadOnly('editForm', fn (AssertableInertia $reload) => $reload
                    ->where('editForm.employee.uuid', $employee->uuid)
                    ->has('editForm.farms')
                    ->has('editForm.departments')
                    ->has('editForm.jobTitles')
                    ->has('editForm.employees')
                )
            );
    }

    public function test_index_loads_edit_form_for_the_requested_employee(): void
    {
        $this->makeEmployee('EMP-0001');
        $target = $this->makeEmployee('EMP-0002');

        $this->actingAs($this->makeUser('HR Manager'))
            ->get(route('employees.index', ['edit' => $target->uuid]))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Employees/Index')
                ->missing('editForm')
                ->reloadOnly('editForm', fn (AssertableInertia $reload) => $reload
                    ->where('editForm.employee.uuid', $target->uuid)
                    ->where('editForm.employees', fn ($options) => collect($options)->doesntContain('id', $target->id))
                )
            );
    }

    public function test_line_manager_cannot_load_edit_form_for_their_report(): void
    {
        $manager = $this->makeUser('Line Manager', $this->makeEmployee('EMP-0100'));
        $report = $this->makeEmployee('EMP-0001', $manager->employee_id);

        $version = $this->actingAs($manager)->get(route('employees.show', $report))->assertOk()->viewData('page')['version'];

        $this->actingAs($manager)
            ->get(route('employees.show', $report), [
                'X-Inertia' => 'true',
                'X-Inertia-Version' => $version,
                'X-Inertia-Partial-Component' => 'Employees/Show',
                'X-Inertia-Partial-Data' => 'editForm',
            ])
            ->assertForbidden();
    }

    public function test_update_saves_and_returns_to_the_previous_page(): void
    {
        $employee = $this->makeEmployee();

        $this->actingAs($this->makeUser('HR Manager'))
            ->from(route('employees.index'))
            ->put(route('employees.update', $employee), [
                'first_name' => 'Janet',
                'last_name' => 'Wanjiru',
                'employment_type' => 'contract',
                'hire_date' => '2024-01-01',
                'status' => 'active',
                'has_disability_exemption' => false,
            ])
            ->assertRedirect(route('employees.index'))
            ->assertSessionHas('success', 'Employee updated successfully.');

        $this->assertSame('Janet', $employee->fresh()->first_name);
        $this->assertSame('contract', $employee->fresh()->employment_type);
    }
}
