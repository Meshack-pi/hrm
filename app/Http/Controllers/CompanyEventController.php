<?php

namespace App\Http\Controllers;

use App\Models\CompanyEvent;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CompanyEventController extends Controller
{
    public function store(Request $request)
    {
        CompanyEvent::create([...$this->validated($request), 'created_by' => $request->user()->id]);

        return back()->with('success', 'Event added to the calendar.');
    }

    public function update(Request $request, CompanyEvent $event)
    {
        $event->update($this->validated($request));

        return back()->with('success', 'Event updated.');
    }

    public function destroy(CompanyEvent $event)
    {
        $event->delete();

        return back()->with('success', 'Event removed.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'title' => 'required|string|max:150',
            'description' => 'nullable|string|max:2000',
            'category' => ['required', Rule::in(array_keys(CompanyEvent::CATEGORIES))],
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'start_time' => 'nullable|date_format:H:i',
            'end_time' => 'nullable|date_format:H:i|required_with:start_time',
            'location' => 'nullable|string|max:150',
            'farm_id' => 'nullable|exists:farms,id',
        ]);

        $start = $data['start_time'] ?? null;
        $end = $data['end_time'] ?? null;

        if ($start && $end && $data['start_date'] === $data['end_date'] && $end <= $start) {
            throw ValidationException::withMessages(['end_time' => 'The end time must be after the start time.']);
        }

        return $data;
    }
}
