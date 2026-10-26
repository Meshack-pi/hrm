<?php

namespace Database\Seeders;

use App\Models\PublicHoliday;
use App\Services\Holidays\KenyaHolidayCalendar;
use App\Services\Holidays\PublicHolidaySync;
use Illuminate\Database\Seeder;

/**
 * Offline baseline for this year and next so a fresh install is correct before
 * the first API sync. Rows are 'system' sourced, so the sync may replace them.
 */
class PublicHolidaysSeeder extends Seeder
{
    public function run(): void
    {
        $calendar = app(KenyaHolidayCalendar::class);
        $year = (int) date('Y');

        foreach ([$year, $year + 1] as $y) {
            foreach ($calendar->forYear($y) as $h) {
                PublicHoliday::firstOrCreate(
                    ['date' => $h['date'], 'name' => $h['name']],
                    ['year' => $y, 'source' => 'system', 'is_active' => true]
                );
            }

            app(PublicHolidaySync::class)->rebuildSubstitutes($y);
        }
    }
}
