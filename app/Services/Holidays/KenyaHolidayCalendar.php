<?php

namespace App\Services\Holidays;

use Carbon\CarbonImmutable;

/**
 * Kenya's statutory holidays that can be computed offline (Public Holidays Act,
 * as amended 2024). Used to seed a fresh install before the first API sync.
 * Idd-ul-Fitr is deliberately absent: it follows the moon sighting announced by
 * the Chief Kadhi, so it is entered by an admin once gazetted.
 */
class KenyaHolidayCalendar
{
    public function forYear(int $year): array
    {
        $easter = $this->easterSunday($year);

        return [
            ['date' => "{$year}-01-01", 'name' => "New Year's Day"],
            ['date' => $easter->subDays(2)->toDateString(), 'name' => 'Good Friday'],
            ['date' => $easter->addDay()->toDateString(), 'name' => 'Easter Monday'],
            ['date' => "{$year}-05-01", 'name' => 'Labour Day'],
            ['date' => "{$year}-06-01", 'name' => 'Madaraka Day'],
            ['date' => "{$year}-10-10", 'name' => 'Mazingira Day'],
            ['date' => "{$year}-10-20", 'name' => 'Mashujaa Day'],
            ['date' => "{$year}-12-12", 'name' => 'Jamhuri Day'],
            ['date' => "{$year}-12-25", 'name' => 'Christmas Day'],
            ['date' => "{$year}-12-26", 'name' => 'Boxing Day'],
        ];
    }

    /** Anonymous Gregorian algorithm — avoids depending on PHP's calendar extension. */
    public function easterSunday(int $year): CarbonImmutable
    {
        $a = $year % 19;
        $b = intdiv($year, 100);
        $c = $year % 100;
        $d = intdiv($b, 4);
        $e = $b % 4;
        $f = intdiv($b + 8, 25);
        $g = intdiv($b - $f + 1, 3);
        $h = (19 * $a + $b - $d - $g + 15) % 30;
        $i = intdiv($c, 4);
        $k = $c % 4;
        $l = (32 + 2 * $e + 2 * $i - $h - $k) % 7;
        $m = intdiv($a + 11 * $h + 22 * $l, 451);
        $month = intdiv($h + $l - 7 * $m + 114, 31);
        $day = (($h + $l - 7 * $m + 114) % 31) + 1;

        return CarbonImmutable::create($year, $month, $day);
    }
}
