<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\User;
use Database\Seeders\LeaveTypesSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class LeaveRequestListTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolesAndPermissionsSeeder::class, LeaveTypesSeeder::class]);
    }

    private function makeEmployee(string $number, string $firstName): Employee
    {
        return Employee::create([
            'employee_number' => $number, 'first_name' => $firstName, 'last_name' => 'Wanjiru',
            'employment_type' => 'permanent', 'hire_date' => '2024-01-01', 'status' => 'active',
        ]);
    }

    public function test_list_includes_what_the_detail_panel_shows(): void
    {
        $applicant = $this->makeEmployee('EMP-0001', 'Jane');
        $relief = $this->makeEmployee('EMP-0002', 'Mary');

        LeaveRequest::create([
            'employee_id' => $applicant->id,
            'leave_type_id' => LeaveType::where('code', 'ANNUAL')->value('id'),
            'start_date' => '2026-11-02', 'end_date' => '2026-11-04', 'days' => 3, 'status' => 'pending',
            'reason' => 'Attending my sister\'s graduation in Eldoret.',
            'relief_employee_id' => $relief->id,
        ]);

        $hr = User::factory()->create();
        $hr->assignRole('HR Manager');

        $this->actingAs($hr)
            ->get(route('leave.requests.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Leave/Requests/Index')
                ->where('requests.data.0.reason', 'Attending my sister\'s graduation in Eldoret.')
                ->where('requests.data.0.relief_employee.first_name', 'Mary')
            );
    }
}
