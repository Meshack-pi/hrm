<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Employee;
use App\Models\Farm;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FormSubmissionTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->admin = User::create([
            'name' => 'Form Admin',
            'email' => 'forms@yegenfarms.co.ke',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
        $this->admin->assignRole('Super Admin');
    }

    /**
     * The Vue forms send '' for untouched optional selects, so the request must
     * still validate once empty strings are normalised to null.
     */
    public function test_employee_can_be_created_with_empty_optional_fields(): void
    {
        $response = $this->actingAs($this->admin)->post(route('employees.store'), [
            'first_name' => 'Peter',
            'middle_name' => '',
            'last_name' => 'Kipchoge',
            'national_id' => '',
            'passport_number' => '',
            'kra_pin' => '',
            'nssf_number' => '',
            'sha_number' => '',
            'date_of_birth' => '',
            'gender' => '',
            'marital_status' => '',
            'phone' => '',
            'personal_email' => '',
            'work_email' => '',
            'physical_address' => '',
            'county' => '',
            'farm_id' => '',
            'department_id' => '',
            'job_title_id' => '',
            'manager_id' => '',
            'employment_type' => 'permanent',
            'hire_date' => '2026-01-10',
            'probation_end_date' => '',
            'contract_end_date' => '',
        ]);

        $response->assertSessionHasNoErrors();

        $this->assertDatabaseHas('employees', [
            'first_name' => 'Peter',
            'last_name' => 'Kipchoge',
            'employee_number' => 'EMP-0001',
        ]);
    }

    public function test_employee_can_be_created_with_full_details(): void
    {
        $farm = Farm::create(['name' => 'Sugoi Farm', 'code' => 'SUG']);
        $dept = Department::create(['name' => 'Dairy', 'code' => 'DRY']);

        $response = $this->actingAs($this->admin)->post(route('employees.store'), [
            'first_name' => 'Mary',
            'last_name' => 'Chebet',
            'kra_pin' => 'A123456789B',
            'gender' => 'female',
            'farm_id' => $farm->id,
            'department_id' => $dept->id,
            'employment_type' => 'contract',
            'hire_date' => '2026-02-01',
            'contract_end_date' => '2027-02-01',
        ]);

        $response->assertSessionHasNoErrors();

        $this->assertDatabaseHas('employees', [
            'first_name' => 'Mary',
            'kra_pin' => 'A123456789B',
            'farm_id' => $farm->id,
            'employment_type' => 'contract',
        ]);
    }

    public function test_invalid_kra_pin_is_rejected(): void
    {
        $response = $this->actingAs($this->admin)->post(route('employees.store'), [
            'first_name' => 'Bad',
            'last_name' => 'Pin',
            'kra_pin' => 'NOT-A-PIN',
            'employment_type' => 'permanent',
            'hire_date' => '2026-01-10',
        ]);

        $response->assertSessionHasErrors('kra_pin');
    }

    public function test_user_can_be_created_with_roles(): void
    {
        $response = $this->actingAs($this->admin)->post(route('users.store'), [
            'name' => 'Payroll Clerk',
            'email' => 'clerk@yegenfarms.co.ke',
            'phone' => '',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'roles' => ['Payroll Officer'],
            'is_active' => true,
        ]);

        $response->assertSessionHasNoErrors();

        $user = User::where('email', 'clerk@yegenfarms.co.ke')->first();
        $this->assertNotNull($user);
        $this->assertTrue($user->hasRole('Payroll Officer'));
    }

    public function test_user_update_keeps_password_when_left_blank(): void
    {
        $user = User::create([
            'name' => 'Keep Password',
            'email' => 'keep@yegenfarms.co.ke',
            'password' => bcrypt('original-secret'),
            'is_active' => true,
        ]);
        $user->assignRole('Employee');

        $originalHash = $user->password;

        $response = $this->actingAs($this->admin)->put(route('users.update', $user), [
            'name' => 'Keep Password Renamed',
            'email' => 'keep@yegenfarms.co.ke',
            'phone' => '',
            'password' => '',
            'password_confirmation' => '',
            'roles' => ['Employee'],
            'is_active' => true,
        ]);

        $response->assertSessionHasNoErrors();

        $this->assertSame($originalHash, $user->fresh()->password);
        $this->assertSame('Keep Password Renamed', $user->fresh()->name);
    }

    public function test_farm_can_be_created_and_updated(): void
    {
        $this->actingAs($this->admin)
            ->post(route('settings.farms.store'), [
                'name' => 'Mombasa Farm',
                'code' => 'MBA',
                'location' => 'Mombasa',
                'county' => 'Mombasa',
            ])
            ->assertSessionHasNoErrors();

        $farm = Farm::where('code', 'MBA')->firstOrFail();

        $this->actingAs($this->admin)
            ->put(route('settings.farms.update', $farm), [
                'name' => 'Mombasa Coastal Farm',
                'code' => 'MBA',
                'location' => 'Mombasa',
                'county' => 'Mombasa',
                'is_active' => false,
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('Mombasa Coastal Farm', $farm->fresh()->name);
        $this->assertFalse((bool) $farm->fresh()->is_active);
    }

    public function test_farm_with_employees_cannot_be_deleted(): void
    {
        $farm = Farm::create(['name' => 'Busy Farm', 'code' => 'BSY']);

        Employee::create([
            'employee_number' => 'EMP-9001',
            'first_name' => 'Stay',
            'last_name' => 'Put',
            'farm_id' => $farm->id,
            'employment_type' => 'permanent',
            'hire_date' => '2025-01-01',
        ]);

        $this->actingAs($this->admin)->delete(route('settings.farms.destroy', $farm));

        $this->assertDatabaseHas('farms', ['id' => $farm->id]);
    }

    public function test_department_can_be_created(): void
    {
        $this->actingAs($this->admin)
            ->post(route('settings.departments.store'), [
                'name' => 'Agronomy',
                'code' => 'AGR',
                'head_employee_id' => '',
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('departments', ['code' => 'AGR']);
    }
}
