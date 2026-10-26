<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PublicHoliday;
use App\Services\Holidays\PublicHolidaySync;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class PublicHolidayController extends Controller
{
    public function __construct(private PublicHolidaySync $sync) {}

    public function index(Request $request)
    {
        $year = $request->integer('year') ?: (int) date('Y');

        return Inertia::render('Settings/Holidays/Index', [
            'year' => $year,
            'years' => range((int) date('Y') - 1, (int) date('Y') + 1),
            'holidays' => PublicHoliday::where('year', $year)->orderBy('date')->get()
                ->map(fn ($h) => [
                    'id' => $h->id,
                    'date' => $h->date->toDateString(),
                    'weekday' => $h->date->format('l'),
                    'name' => $h->name,
                    'source' => $h->source,
                    'isActive' => $h->is_active,
                    'isSubstitute' => $h->is_substitute,
                    'syncedAt' => $h->synced_at,
                ]),
            'lastSyncedAt' => PublicHoliday::max('synced_at'),
            'apiUrl' => config('services.holidays.url'),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'date' => 'required|date',
            'name' => [
                'required', 'string', 'max:120',
                Rule::unique('public_holidays')->where(fn ($q) => $q->where('date', $request->input('date'))),
            ],
        ]);

        $holiday = PublicHoliday::create([
            ...$data,
            'year' => (int) substr($data['date'], 0, 4),
            'source' => 'manual',
            'is_active' => true,
        ]);

        $this->sync->rebuildSubstitutes($holiday->year);

        return back()->with('success', "{$holiday->name} added. The weekly sync will not change it.");
    }

    public function update(Request $request, PublicHoliday $holiday)
    {
        $data = $request->validate([
            'is_active' => 'sometimes|boolean',
            'name' => 'sometimes|string|max:120',
        ]);

        // Only manual holidays can be renamed; synced ones can just be switched off.
        if (isset($data['name']) && $holiday->source !== 'manual') {
            unset($data['name']);
        }

        $holiday->update($data);
        $this->sync->rebuildSubstitutes($holiday->year);

        return back()->with('success', $holiday->is_active ? "{$holiday->name} is active." : "{$holiday->name} switched off — it no longer counts for leave.");
    }

    public function destroy(PublicHoliday $holiday)
    {
        if ($holiday->source !== 'manual') {
            return back()->with('error', 'Synced holidays come back on the next sync. Switch it off instead.');
        }

        $year = $holiday->year;
        $holiday->delete();
        $this->sync->rebuildSubstitutes($year);

        return back()->with('success', 'Holiday removed.');
    }

    public function sync(Request $request)
    {
        $year = (int) date('Y');
        $results = $this->sync->sync([$year, $year + 1]);

        $errors = array_filter($results, fn ($r) => $r['error']);

        if (count($errors) === count($results)) {
            return back()->with('error', 'Could not sync holidays: '.reset($errors)['error'].' Existing holidays were kept.');
        }

        $summary = collect($results)->map(fn ($r) => $r['error']
            ? "{$r['year']}: failed"
            : "{$r['year']}: {$r['added']} added, {$r['removed']} removed")->implode('; ');

        return back()->with($errors ? 'warning' : 'success', "Holidays synced — {$summary}.");
    }
}
