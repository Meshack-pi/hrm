<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\EmployeeBankDetail;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class EmployeeAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private const ACCOUNT_NUMBER = '0123456789';

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

    private function withBankDetails(Employee $employee): EmployeeBankDetail
    {
        return EmployeeBankDetail::create([
            'employee_id' => $employee->id,
            'payment_method' => 'bank',
            'bank_name' => 'Equity Bank',
            'account_name' => 'Jane Wanjiru',
            'account_number' => self::ACCOUNT_NUMBER,
            'is_primary' => true,
        ]);
    }

    private function makeUser(string $role, ?Employee $employee = null): User
    {
        $user = User::factory()->create(['employee_id' => $employee?->id]);
        $user->assignRole($role);

        return $user;
    }

    public function test_account_number_is_never_serialised(): void
    {
        $employee = $this->makeEmployee();
        $detail = $this->withBankDetails($employee);

        $json = $detail->fresh()->toJson();

        $this->assertStringNotContainsString(self::ACCOUNT_NUMBER, $json);
        $this->assertStringContainsString('6789', $json, 'Masked form should expose only the last 4.');
    }

    public function test_full_account_number_remains_readable_server_side(): void
    {
        $employee = $this->makeEmployee();
        $this->withBankDetails($employee);

        // Payment file generation still needs the real value.
        $this->assertSame(self::ACCOUNT_NUMBER, $employee->bankDetails()->first()->account_number);
    }

    public function test_plain_employee_cannot_view_another_employees_record(): void
    {
        $own = $this->makeEmployee('EMP-0001');
        $other = $this->makeEmployee('EMP-0002');

        $user = $this->makeUser('Employee', $own);

        $this->actingAs($user)
            ->get(route('employees.show', $other))
            ->assertForbidden();
    }

    public function test_plain_employee_can_view_own_record(): void
    {
        $own = $this->makeEmployee('EMP-0001');
        $user = $this->makeUser('Employee', $own);

        $this->actingAs($user)
            ->get(route('employees.show', $own))
            ->assertOk();
    }

    public function test_bank_details_are_not_sent_to_users_without_salary_permission(): void
    {
        $employee = $this->makeEmployee();
        $this->withBankDetails($employee);

        $manager = $this->makeUser('Line Manager', $this->makeEmployee('EMP-0002'));
        $employee->update(['manager_id' => $manager->employee_id]);

        $response = $this->actingAs($manager)->get(route('employees.show', $employee));

        $response->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('canViewPay', false)
                ->missing('employee.bank_details')
            );

        $response->assertDontSee(self::ACCOUNT_NUMBER);
    }

    public function test_hr_manager_receives_bank_details_but_masked(): void
    {
        $employee = $this->makeEmployee();
        $this->withBankDetails($employee);

        $hr = $this->makeUser('HR Manager');

        $response = $this->actingAs($hr)->get(route('employees.show', $employee));

        $response->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('canViewPay', true)
                ->has('employee.bank_details', 1)
            );

        // Even a permitted viewer gets the masked form in the payload.
        $response->assertDontSee(self::ACCOUNT_NUMBER);
    }

    public function test_line_manager_index_is_scoped_to_their_team(): void
    {
        $managerEmployee = $this->makeEmployee('EMP-0100');
        $manager = $this->makeUser('Line Manager', $managerEmployee);

        $report = $this->makeEmployee('EMP-0101', $managerEmployee->id);
        $stranger = $this->makeEmployee('EMP-0102');

        $this->actingAs($manager)
            ->get(route('employees.index'))
            ->assertOk()
            ->assertSee($report->employee_number)
            ->assertDontSee($stranger->employee_number);
    }

    public function test_employee_without_create_permission_cannot_open_create_page(): void
    {
        $user = $this->makeUser('Employee', $this->makeEmployee());

        $this->actingAs($user)
            ->get(route('employees.create'))
            ->assertForbidden();
    }
}
