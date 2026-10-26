<?php

namespace Tests\Feature;

use App\Models\CompanyEvent;
use App\Models\Employee;
use App\Models\Farm;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\PublicHoliday;
use App\Models\User;
use Database\Seeders\LeaveTypesSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class CalendarTest extends TestCase
{
    use RefreshDatabase;

    private const MONTH = '2027-12';

    private Farm $kos;

    private Farm $sug;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolesAndPermissionsSeeder::class, LeaveTypesSeeder::class]);
        $this->kos = Farm::create(['name' => 'Kosachei Farm', 'code' => 'KOS']);
        $this->sug = Farm::create(['name' => 'Sugoi Farm', 'code' => 'SUG']);
    }

    private function employee(string $n, ?Employee $manager = null, ?Farm $farm = null): Employee
    {
        return Employee::create([
            'employee_number' => "EMP-{$n}", 'first_name' => "Person{$n}", 'last_name' => 'Test',
            'farm_id' => ($farm ?? $this->kos)->id, 'manager_id' => $manager?->id,
            'employment_type' => 'permanent', 'hire_date' => '2020-01-01', 'status' => 'active',
        ]);
    }

    private function user(string $role, ?Employee $employee = null): User
    {
        $u = User::factory()->create(['employee_id' => $employee?->id]);
        $u->assignRole($role);

        return $u;
    }

    private function leave(Employee $e, string $status, string $start = '2027-12-06', string $end = '2027-12-08'): LeaveRequest
    {
        return LeaveRequest::create([
            'employee_id' => $e->id, 'leave_type_id' => LeaveType::where('code', 'ANNUAL')->value('id'),
            'start_date' => $start, 'end_date' => $end, 'days' => 3, 'status' => $status,
        ]);
    }

    private function eventsFor(User $user, array $query = []): array
    {
        $events = null;

        $this->actingAs($user)->get(route('calendar.index', ['month' => self::MONTH, ...$query]))
            ->assertOk()
            ->assertInertia(function (AssertableInertia $p) use (&$events) {
                $p->component('Calendar/Index');
                $events = $p->toArray()['props']['events'];
            });

        return $events;
    }

    private function titles(array $events, ?string $type = null): array
    {
        return collect($events)->when($type, fn ($c) => $c->where('type', $type))->pluck('title')->sort()->values()->all();
    }

    public function test_everyone_sees_active_holidays_and_company_events(): void
    {
        PublicHoliday::create(['date' => '2027-12-13', 'name' => 'Jamhuri Day', 'year' => 2027, 'source' => 'api']);
        PublicHoliday::create(['date' => '2027-12-14', 'name' => 'Switched off', 'year' => 2027, 'source' => 'api', 'is_active' => false]);
        CompanyEvent::create(['title' => 'Staff party', 'category' => 'celebration', 'start_date' => '2027-12-18', 'end_date' => '2027-12-18']);

        $events = $this->eventsFor($this->user('Employee', $this->employee('1')));

        $this->assertSame(['Jamhuri Day'], $this->titles($events, 'holiday'));
        $this->assertSame(['Staff party'], $this->titles($events, 'event'));
    }

    public function test_grid_includes_spill_over_days_from_neighbouring_months(): void
    {
        // Dec 2027 starts on a Wednesday, so the Monday-first grid opens on 29 Nov.
        PublicHoliday::create(['date' => '2027-11-29', 'name' => 'Spill-over day', 'year' => 2027, 'source' => 'manual']);

        $this->actingAs($this->user('Employee', $this->employee('1')))
            ->get(route('calendar.index', ['month' => self::MONTH]))
            ->assertInertia(fn (AssertableInertia $p) => $p
                ->where('range.from', '2027-11-29')
                ->where('range.to', '2028-01-02')
                ->where('events.0.title', 'Spill-over day'));
    }

    public function test_leave_visibility_is_scoped_by_role(): void
    {
        $manager = $this->employee('M');
        $report = $this->employee('R', $manager);
        $stranger = $this->employee('S');

        $this->leave($report, 'approved');
        $this->leave($stranger, 'approved');
        $this->leave($stranger, 'pending', '2027-12-20', '2027-12-21');
        $ownPending = $this->leave($report, 'pending', '2027-12-15', '2027-12-16');

        // Employee: only their own leave, including their pending request.
        $events = $this->eventsFor($this->user('Employee', $report));
        $this->assertCount(2, $this->titles($events, 'leave'));
        $this->assertTrue(collect($events)->where('type', 'leave')->every(fn ($e) => $e['meta']['mine']));
        $this->assertContains("leave-{$ownPending->id}", collect($events)->pluck('id'));

        // Line manager: own team's approved leave, never the stranger's.
        $events = $this->eventsFor($this->user('Line Manager', $manager));
        $this->assertSame(['PersonR Test · Annual Leave'], $this->titles($events, 'leave'));

        // HR: everyone's approved leave, but not others' pending requests.
        $events = $this->eventsFor($this->user('HR Manager'));
        $this->assertSame(['PersonR Test · Annual Leave', 'PersonS Test · Annual Leave'], $this->titles($events, 'leave'));
    }

    public function test_farm_filter_keeps_company_wide_events(): void
    {
        CompanyEvent::create(['title' => 'All farms', 'category' => 'meeting', 'start_date' => '2027-12-01', 'end_date' => '2027-12-01']);
        CompanyEvent::create(['title' => 'Kosachei only', 'category' => 'farm_activity', 'start_date' => '2027-12-02', 'end_date' => '2027-12-02', 'farm_id' => $this->kos->id]);
        CompanyEvent::create(['title' => 'Sugoi only', 'category' => 'farm_activity', 'start_date' => '2027-12-03', 'end_date' => '2027-12-03', 'farm_id' => $this->sug->id]);

        $events = $this->eventsFor($this->user('HR Manager'), ['farm_id' => $this->kos->id]);

        $this->assertSame(['All farms', 'Kosachei only'], $this->titles($events, 'event'));
    }

    public function test_payroll_deadlines_only_for_payroll_staff(): void
    {
        $this->assertSame([], $this->titles($this->eventsFor($this->user('Employee', $this->employee('1'))), 'payroll'));

        $events = $this->eventsFor($this->user('Payroll Officer'));
        $deadline = collect($events)->firstWhere('type', 'payroll');
        $this->assertSame('2027-12-09', $deadline['start']);
        $this->assertStringContainsString('November 2027', $deadline['meta']['description']);
    }

    public function test_invalid_month_falls_back_to_current_month(): void
    {
        $this->actingAs($this->user('Employee', $this->employee('1')))
            ->get(route('calendar.index', ['month' => '2027-13']))
            ->assertInertia(fn (AssertableInertia $p) => $p->where('month', now()->format('Y-m')));
    }

    public function test_only_calendar_managers_can_create_edit_and_delete_events(): void
    {
        $payload = ['title' => 'Vet visit', 'category' => 'farm_activity', 'start_date' => '2027-12-05',
            'end_date' => '2027-12-05', 'start_time' => '09:00', 'end_time' => '11:00', 'farm_id' => $this->sug->id];

        $employee = $this->user('Employee', $this->employee('1'));
        $this->actingAs($employee)->post(route('calendar.events.store'), $payload)->assertForbidden();

        $hr = $this->user('HR Manager');
        $this->actingAs($hr)->post(route('calendar.events.store'), $payload)->assertSessionHas('success');

        $event = CompanyEvent::firstOrFail();
        $this->assertSame($hr->id, $event->created_by);

        $this->actingAs($employee)->put(route('calendar.events.update', $event), $payload)->assertForbidden();
        $this->actingAs($hr)->put(route('calendar.events.update', $event), [...$payload, 'title' => 'Vet visit (moved)'])->assertSessionHas('success');
        $this->assertSame('Vet visit (moved)', $event->fresh()->title);

        $this->actingAs($employee)->delete(route('calendar.events.destroy', $event))->assertForbidden();
        $this->actingAs($hr)->delete(route('calendar.events.destroy', $event));
        $this->assertModelMissing($event);
    }

    public function test_event_validation(): void
    {
        $hr = $this->user('HR Manager');

        $this->actingAs($hr)->post(route('calendar.events.store'), [
            'title' => 'Backwards', 'category' => 'meeting', 'start_date' => '2027-12-10', 'end_date' => '2027-12-09',
        ])->assertSessionHasErrors('end_date');

        $this->actingAs($hr)->post(route('calendar.events.store'), [
            'title' => 'Time travel', 'category' => 'meeting', 'start_date' => '2027-12-10', 'end_date' => '2027-12-10',
            'start_time' => '14:00', 'end_time' => '09:00',
        ])->assertSessionHasErrors('end_time');

        $this->actingAs($hr)->post(route('calendar.events.store'), [
            'title' => 'No times', 'category' => 'meeting', 'start_date' => '2027-12-10', 'end_date' => '2027-12-10',
        ])->assertSessionHas('success');
    }
}
