<?php

namespace Tests\Feature\Payroll;

use App\Models\PayrollRun;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayrollApprovalTest extends TestCase
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

    public function test_calculator_cannot_approve_own_run(): void
    {
        $officer = $this->makeUser('Payroll Officer');
        $approver = $this->makeUser('Finance Approver');

        // Give the officer approval rights too, so the only thing blocking
        // them is the maker-checker rule rather than a missing permission.
        $officer->givePermissionTo('payroll.approve');

        $run = PayrollRun::create([
            'period_month' => 6,
            'period_year' => 2026,
            'status' => 'calculated',
            'calculated_by' => $officer->id,
            'calculated_at' => now(),
        ]);

        $response = $this->actingAs($officer)
            ->post(route('payroll.runs.approve', $run));

        $response->assertSessionHas('error');

        $run->refresh();
        $this->assertSame('calculated', $run->status);
        $this->assertNull($run->approved_by);
        $this->assertNull($run->locked_at);
    }

    public function test_different_user_can_approve(): void
    {
        $officer = $this->makeUser('Payroll Officer');
        $approver = $this->makeUser('Finance Approver');

        $run = PayrollRun::create([
            'period_month' => 6,
            'period_year' => 2026,
            'status' => 'calculated',
            'calculated_by' => $officer->id,
            'calculated_at' => now(),
        ]);

        $this->actingAs($approver)
            ->post(route('payroll.runs.approve', $run))
            ->assertSessionHas('success');

        $run->refresh();
        $this->assertSame('approved', $run->status);
        $this->assertSame($approver->id, $run->approved_by);
        $this->assertNotNull($run->locked_at);
    }

    public function test_approved_run_is_locked_against_recalculation(): void
    {
        $officer = $this->makeUser('Payroll Officer');

        $run = PayrollRun::create([
            'period_month' => 6,
            'period_year' => 2026,
            'status' => 'approved',
            'calculated_by' => $officer->id,
            'locked_at' => now(),
        ]);

        $this->actingAs($officer)
            ->post(route('payroll.runs.calculate', $run))
            ->assertSessionHas('error');

        $run->refresh();
        $this->assertSame('approved', $run->status);
    }

    public function test_draft_run_cannot_be_approved(): void
    {
        $approver = $this->makeUser('Finance Approver');

        $run = PayrollRun::create([
            'period_month' => 6,
            'period_year' => 2026,
            'status' => 'draft',
        ]);

        $this->actingAs($approver)
            ->post(route('payroll.runs.approve', $run))
            ->assertSessionHas('error');

        $this->assertSame('draft', $run->fresh()->status);
    }

    public function test_user_without_approve_permission_is_forbidden(): void
    {
        $officer = $this->makeUser('Payroll Officer');
        $other = $this->makeUser('Payroll Officer');

        $run = PayrollRun::create([
            'period_month' => 6,
            'period_year' => 2026,
            'status' => 'calculated',
            'calculated_by' => $officer->id,
        ]);

        $this->actingAs($other)
            ->post(route('payroll.runs.approve', $run))
            ->assertForbidden();
    }
}
