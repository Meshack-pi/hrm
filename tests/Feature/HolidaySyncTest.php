<?php

namespace Tests\Feature;

use App\Models\LeaveType;
use App\Models\PublicHoliday;
use App\Models\User;
use App\Services\Holidays\KenyaHolidayCalendar;
use App\Services\Holidays\PublicHolidaySync;
use App\Services\Leave\LeaveDayCounter;
use Database\Seeders\LeaveTypesSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class HolidaySyncTest extends TestCase
{
    use RefreshDatabase;

    private const URL = 'date.nager.at/api/v3/PublicHolidays/2027/KE';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolesAndPermissionsSeeder::class, LeaveTypesSeeder::class]);
    }

    private function api(array $holidays, int $status = 200): void
    {
        Http::fake(['*' => Http::response($holidays, $status)]);
    }

    private function item(string $date, string $name, bool $global = true, array $types = ['Public']): array
    {
        return ['date' => $date, 'localName' => $name, 'name' => $name, 'countryCode' => 'KE',
            'global' => $global, 'counties' => null, 'types' => $types];
    }

    private function sync(int $year = 2027): array
    {
        return app(PublicHolidaySync::class)->syncYear($year);
    }

    public function test_sync_imports_holidays_and_is_idempotent(): void
    {
        $this->api([$this->item('2027-01-01', "New Year's Day"), $this->item('2027-06-01', 'Madaraka Day')]);

        $first = $this->sync();
        $second = $this->sync();

        $this->assertSame(2, $first['added']);
        $this->assertSame(0, $second['added']);
        $this->assertSame(2, $second['updated']);
        $this->assertSame(2, PublicHoliday::count());
        $this->assertSame('api', PublicHoliday::first()->source);
        $this->assertNotNull(PublicHoliday::first()->synced_at);
        Http::assertSent(fn ($r) => str_contains($r->url(), self::URL));
    }

    public function test_api_failure_keeps_existing_holidays(): void
    {
        PublicHoliday::create(['date' => '2027-06-01', 'name' => 'Madaraka Day', 'year' => 2027, 'source' => 'api']);

        $this->api(['message' => 'down'], 503);
        $result = $this->sync();

        $this->assertNotNull($result['error']);
        $this->assertSame(1, PublicHoliday::count());
    }

    public function test_empty_api_response_does_not_wipe_the_year(): void
    {
        PublicHoliday::create(['date' => '2027-06-01', 'name' => 'Madaraka Day', 'year' => 2027, 'source' => 'api']);

        $this->api([]);
        $result = $this->sync();

        $this->assertNotNull($result['error']);
        $this->assertSame(1, PublicHoliday::count());
    }

    public function test_county_only_and_malformed_entries_are_ignored(): void
    {
        $this->api([
            $this->item('2027-06-01', 'Madaraka Day'),
            $this->item('2027-07-01', 'County Show Day', global: false),
            $this->item('2027-08-01', 'Bank Holiday', types: ['Bank']),
            $this->item('not-a-date', 'Broken'),
            $this->item('2026-12-31', 'Wrong year'),
            $this->item('2027-09-01', ''),
        ]);

        $this->sync();

        $this->assertSame(['Madaraka Day'], PublicHoliday::pluck('name')->all());
    }

    public function test_manual_holidays_are_never_touched(): void
    {
        PublicHoliday::create(['date' => '2027-03-10', 'name' => 'Idd-ul-Fitr', 'year' => 2027, 'source' => 'manual']);

        $this->api([$this->item('2027-06-01', 'Madaraka Day')]);
        $result = $this->sync();

        $this->assertSame(0, $result['removed']);
        $this->assertTrue(PublicHoliday::where('name', 'Idd-ul-Fitr')->where('source', 'manual')->exists());
    }

    public function test_holidays_dropped_by_the_api_are_removed(): void
    {
        PublicHoliday::create(['date' => '2027-02-11', 'name' => 'Moi Day', 'year' => 2027, 'source' => 'system']);

        $this->api([$this->item('2027-06-01', 'Madaraka Day')]);
        $result = $this->sync();

        $this->assertSame(1, $result['removed']);
        $this->assertFalse(PublicHoliday::where('name', 'Moi Day')->exists());
    }

    public function test_switched_off_holiday_stays_off_after_sync(): void
    {
        $this->api([$this->item('2027-06-01', 'Madaraka Day')]);
        $this->sync();
        PublicHoliday::first()->update(['is_active' => false]);

        $this->sync();

        $this->assertFalse(PublicHoliday::first()->is_active);
    }

    public function test_sunday_holiday_gets_monday_substitute_skipping_other_holidays(): void
    {
        // 26 Dec 2027 is a Sunday; Monday 27 must be the observed day.
        $this->api([$this->item('2027-12-25', 'Christmas Day'), $this->item('2027-12-26', 'Boxing Day')]);
        $result = $this->sync();

        $this->assertSame(1, $result['substitutes']);
        $sub = PublicHoliday::where('is_substitute', true)->first();
        $this->assertSame('2027-12-27', $sub->date->toDateString());
        $this->assertSame('Boxing Day (observed)', $sub->name);
    }

    public function test_no_second_substitute_when_the_api_already_shifted_the_holiday(): void
    {
        // Some sources list both the Sunday and the observed Monday under the same name.
        $this->api([$this->item('2027-12-12', 'Jamhuri Day'), $this->item('2027-12-13', 'Jamhuri Day')]);
        $result = $this->sync();

        $this->assertSame(0, $result['substitutes']);
    }

    public function test_leave_counting_skips_active_holidays_and_substitutes_only(): void
    {
        $annual = LeaveType::where('code', 'ANNUAL')->first();
        $counter = app(LeaveDayCounter::class);

        PublicHoliday::create(['date' => '2027-12-13', 'name' => 'Jamhuri Day', 'year' => 2027, 'source' => 'api']);
        PublicHoliday::create(['date' => '2027-12-27', 'name' => 'Boxing Day (observed)', 'year' => 2027, 'source' => 'system', 'is_substitute' => true]);
        PublicHoliday::create(['date' => '2027-12-14', 'name' => 'Disabled', 'year' => 2027, 'source' => 'api', 'is_active' => false]);

        // Mon 13 – Fri 17 Dec: 5 weekdays, minus Jamhuri Day = 4 (the disabled holiday still counts as work).
        $this->assertSame(4.0, $counter->count('2027-12-13', '2027-12-17', $annual));
        // Mon 27 – Fri 31 Dec: 5 weekdays minus the observed Boxing Day = 4.
        $this->assertSame(4.0, $counter->count('2027-12-27', '2027-12-31', $annual));
    }

    public function test_offline_calendar_computes_easter_correctly(): void
    {
        $cal = app(KenyaHolidayCalendar::class);

        foreach (['2024' => '2024-03-31', '2025' => '2025-04-20', '2026' => '2026-04-05', '2027' => '2027-03-28', '2030' => '2030-04-21'] as $year => $easter) {
            $this->assertSame($easter, $cal->easterSunday((int) $year)->toDateString());
        }

        $names = collect($cal->forYear(2026))->pluck('date', 'name');
        $this->assertSame('2026-04-03', $names['Good Friday']);
        $this->assertSame('2026-04-06', $names['Easter Monday']);
        $this->assertSame('2026-10-10', $names['Mazingira Day']);
    }

    public function test_command_succeeds_when_the_api_responds(): void
    {
        $this->api([$this->item('2027-06-01', 'Madaraka Day')]);

        $this->artisan('holidays:sync', ['--year' => [2027]])->assertSuccessful();
    }

    public function test_command_fails_when_every_year_fails(): void
    {
        $this->api([], 500);

        $this->artisan('holidays:sync', ['--year' => [2027]])->assertFailed();
    }

    public function test_admin_screen_permissions_sync_and_manual_entry(): void
    {
        $hr = User::factory()->create();
        $hr->assignRole('HR Manager');
        $employee = User::factory()->create();
        $employee->assignRole('Employee');

        $this->actingAs($employee)->get(route('settings.holidays.index'))->assertForbidden();
        $this->actingAs($employee)->post(route('settings.holidays.sync'))->assertForbidden();

        $this->actingAs($hr)->get(route('settings.holidays.index'))->assertOk();

        $this->api([$this->item((date('Y')).'-06-01', 'Madaraka Day')]);
        $this->actingAs($hr)->post(route('settings.holidays.sync'))->assertSessionHas('success');

        $this->actingAs($hr)->post(route('settings.holidays.store'), ['date' => date('Y').'-03-20', 'name' => 'Idd-ul-Fitr'])
            ->assertSessionHas('success');
        $manual = PublicHoliday::where('name', 'Idd-ul-Fitr')->firstOrFail();
        $this->assertSame('manual', $manual->source);

        // Synced holidays can't be deleted (they'd return next sync) — only switched off.
        $synced = PublicHoliday::where('source', 'api')->first();
        $this->actingAs($hr)->delete(route('settings.holidays.destroy', $synced))->assertSessionHas('error');
        $this->actingAs($hr)->patch(route('settings.holidays.update', $synced), ['is_active' => false]);
        $this->assertFalse($synced->fresh()->is_active);

        $this->actingAs($hr)->delete(route('settings.holidays.destroy', $manual))->assertSessionHas('success');
        $this->assertModelMissing($manual);
    }
}
