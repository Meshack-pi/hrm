<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Employee;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DepartmentController extends Controller
{
    public function index()
    {
        return Inertia::render('Settings/Departments/Index', [
            'departments' => Department::with('head:id,first_name,last_name')
                ->withCount('employees')
                ->orderBy('name')
                ->get(),
            'employees' => Employee::select('id', 'first_name', 'last_name', 'employee_number')
                ->where('status', 'active')
                ->orderBy('first_name')
                ->get(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:10|unique:departments',
            'head_employee_id' => 'nullable|exists:employees,id',
        ]);

        Department::create($validated);

        return back()->with('success', 'Department created successfully.');
    }

    public function update(Request $request, Department $department)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:10|unique:departments,code,'.$department->id,
            'head_employee_id' => 'nullable|exists:employees,id',
        ]);

        $department->update($validated);

        return back()->with('success', 'Department updated successfully.');
    }

    public function destroy(Department $department)
    {
        if ($department->employees()->exists()) {
            return back()->with('error', 'Cannot delete a department with employees assigned.');
        }

        $department->delete();

        return back()->with('success', 'Department deleted successfully.');
    }
}
