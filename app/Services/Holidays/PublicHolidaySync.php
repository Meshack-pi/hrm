<?php

namespace App\Services\Holidays;

use App\Models\PublicHoliday;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Pulls Kenyan public holidays from Nager.Date into public_holidays.
 *
 * Rules:
 *  - A failed or empty response never deletes anything; the last good data stays.
 *  - Manual (admin-entered) holidays are never touched.
 *  - A holiday an admin switched off stays off even if the API keeps returning it.
 *  - Sunday holidays get a Monday substitute (Public Holidays Act), which the API omits.
 */
class PublicHolidaySync
{
    /** @return array<int, array{year:int, added:int, updated:int, removed:int, substitutes:int, error:?string}> */
    public function sync(array $years): array
    {
        return array_map(fn ($y) => $this->syncYear((int) $y), $years);
    }

    public function syncYear(int $year): array
    {
        $result = ['year' => $year, 'added' => 0, 'updated' => 0, 'removed' => 0, 'substitutes' => 0, 'error' => null];

        try {
            $holidays = $this->fetch($year);
        } catch (\Throwable $e) {
            Log::warning('Public holiday sync failed', ['year' => $year, 'error' => $e->getMessage()]);

            return [...$result, 'error' => $e->getMessage()];
        }

        DB::transaction(function () use ($year, $holidays, &$result) {
            $existing = PublicHoliday::where('year', $year)->where('is_substitute', false)->get()
                ->keyBy(fn ($h) => $h->date->toDateString().'|'.$h->name);

            $now = now();

            foreach ($holidays as $h) {
                $key = $h['date'].'|'.$h['name'];
                $row = $existing->get($key);

                if ($row?->source === 'manual') {
                    continue;
                }

                if ($row) {
                    $row->update(['source' => 'api', 'synced_at' => $now]);
                    $result['updated']++;
                } else {
                    PublicHoliday::create([
                        'date' => $h['date'], 'name' => $h['name'], 'year' => $year,
                        'source' => 'api', 'is_active' => true, 'synced_at' => $now,
                    ]);
                    $result['added']++;
                }
            }

            $returned = collect($holidays)->map(fn ($h) => $h['date'].'|'.$h['name']);
            $stale = $existing->filter(fn ($row, $key) => $row->source !== 'manual' && ! $returned->contains($key));

            $result['removed'] = $stale->count();
            $stale->each->delete();

            $result['substitutes'] = $this->rebuildSubstitutes($year);
        });

        return $result;
    }

    /**
     * Recomputes "observed" Mondays for every active holiday that falls on a Sunday.
     * Call after any change to a year's holidays, including manual edits.
     */
    public function rebuildSubstitutes(int $year): int
    {
        PublicHoliday::where('year', $year)->where('is_substitute', true)->where('source', '!=', 'manual')->delete();

        $holidays = PublicHoliday::active()->where('year', $year)->where('is_substitute', false)->orderBy('date')->get();

        // Every date that is already a holiday, including next January, so a substitute never lands on one.
        $taken = PublicHoliday::active()->whereIn('year', [$year, $year + 1])->pluck('date')
            ->map(fn ($d) => $d->toDateString())->flip();

        $created = 0;

        foreach ($holidays->filter(fn ($h) => $h->date->isSunday()) as $holiday) {
            // The API already shifts some Sunday holidays itself (e.g. Jamhuri Day 2027); if the
            // same holiday also appears in the next few days, it's already observed — don't add another.
            $alreadyObserved = $holidays->contains(fn ($o) => $o->name === $holiday->name
                && $o->date->gt($holiday->date) && $o->date->lte($holiday->date->copy()->addDays(3)));

            if ($alreadyObserved) {
                continue;
            }

            $observed = CarbonImmutable::parse($holiday->date)->addDay();
            while ($taken->has($observed->toDateString())) {
                $observed = $observed->addDay();
            }

            PublicHoliday::create([
                'date' => $observed->toDateString(),
                'name' => "{$holiday->name} (observed)",
                'year' => $observed->year,
                'source' => 'system',
                'is_active' => true,
                'is_substitute' => true,
            ]);

            $taken[$observed->toDateString()] = true;
            $created++;
        }

        return $created;
    }

    /** @return array<int, array{date:string, name:string}> */
    private function fetch(int $year): array
    {
        $config = config('services.holidays');

        try {
            $response = Http::baseUrl($config['url'])
                ->timeout($config['timeout'])
                ->retry(2, 500, throw: false)
                ->acceptJson()
                ->get("PublicHolidays/{$year}/{$config['country']}");
        } catch (ConnectionException $e) {
            throw new \RuntimeException("Could not reach the holiday API: {$e->getMessage()}");
        }

        if (! $response->successful()) {
            throw new \RuntimeException("Holiday API returned HTTP {$response->status()}");
        }

        $items = $response->json();

        if (! is_array($items) || $items === []) {
            throw new \RuntimeException('Holiday API returned no holidays; keeping existing data.');
        }

        return collect($items)
            // County-specific observances aren't company-wide days off.
            ->filter(fn ($i) => ($i['global'] ?? true) === true && in_array('Public', $i['types'] ?? ['Public'], true))
            ->map(fn ($i) => ['date' => (string) ($i['date'] ?? ''), 'name' => trim((string) ($i['name'] ?? ''))])
            ->filter(fn ($i) => $i['name'] !== ''
                && preg_match('/^\d{4}-\d{2}-\d{2}$/', $i['date'])
                && (int) substr($i['date'], 0, 4) === $year)
            ->unique(fn ($i) => $i['date'].'|'.$i['name'])
            ->values()
            ->all();
    }
}
