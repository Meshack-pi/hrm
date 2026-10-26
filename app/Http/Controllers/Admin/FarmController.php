<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Farm;
use Illuminate\Http\Request;
use Inertia\Inertia;

class FarmController extends Controller
{
    public function index()
    {
        return Inertia::render('Settings/Farms/Index', [
            'farms' => Farm::withCount('employees')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:10|unique:farms',
            'location' => 'nullable|string|max:255',
            'county' => 'nullable|string|max:255',
        ]);

        Farm::create($validated);

        return back()->with('success', 'Farm created successfully.');
    }

    public function update(Request $request, Farm $farm)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:10|unique:farms,code,'.$farm->id,
            'location' => 'nullable|string|max:255',
            'county' => 'nullable|string|max:255',
            'is_active' => 'boolean',
        ]);

        $farm->update($validated);

        return back()->with('success', 'Farm updated successfully.');
    }

    public function destroy(Farm $farm)
    {
        if ($farm->employees()->exists()) {
            return back()->with('error', 'Cannot delete a farm with employees assigned.');
        }

        $farm->delete();

        return back()->with('success', 'Farm deleted successfully.');
    }
}
