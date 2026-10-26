<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Employee;
use App\Models\Farm;
use App\Models\JobTitle;
use Illuminate\Http\Request;
use Inertia\Inertia;

class EmployeeController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', Employee::class);

        $user = $request->user();

        $employees = Employee::with(['farm', 'department', 'jobTitle', 'manager:id,first_name,last_name'])
            // Managers see only their own reports; an employee sees only themselves.
            ->unless($user->can('employees.view_all'), function ($q) use ($user) {
                if ($user->can('employees.view_team') && $user->employee_id) {
                    $q->where(fn ($q) => $q->where('manager_id', $user->employee_id)
                        ->orWhere('id', $user->employee_id));
                } else {
                    $q->where('id', $user->employee_id);
                }
            })
            ->when($request->search, function ($q, $s) {
                $q->where(function ($q) use ($s) {
                    $q->where('first_name', 'like', "%{$s}%")
                        ->orWhere('last_name', 'like', "%{$s}%")
                        ->orWhere('employee_number', 'like', "%{$s}%")
                        ->orWhere('national_id', 'like', "%{$s}%");
                });
            })
            ->when($request->farm_id, fn ($q, $f) => $q->where('farm_id', $f))
            ->when($request->department_id, fn ($q, $d) => $q->where('department_id', $d))
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->when($request->employment_type, fn ($q, $t) => $q->where('employment_type', $t))
            ->orderBy('first_name')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Employees/Index', [
            'employees' => $employees,
            'farms' => Farm::where('is_active', true)->orderBy('name')->get(),
            'departments' => Department::orderBy('name')->get(),
            'filters' => $request->only(['search', 'farm_id', 'department_id', 'status', 'employment_type']),
            // Loaded only when the edit modal is opened for the employee given in ?edit=<uuid>.
            'editForm' => Inertia::optional(fn () => $this->editForm(
                Employee::where('uuid', (string) $request->query('edit'))->firstOrFail()
            )),
        ]);
    }

    public function create()
    {
        $this->authorize('create', Employee::class);

        return Inertia::render('Employees/Create', [
            'farms' => Farm::where('is_active', true)->orderBy('name')->get(),
            'departments' => Department::orderBy('name')->get(),
            'jobTitles' => JobTitle::orderBy('name')->get(),
            'employees' => Employee::select('id', 'first_name', 'last_name', 'employee_number')
                ->where('status', 'active')
                ->orderBy('first_name')
                ->get(),
        ]);
    }

    public function store(Request $request)
    {
        $this->authorize('create', Employee::class);

        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'middle_name' => 'nullable|string|max:255',
            'last_name' => 'required|string|max:255',
            'national_id' => 'nullable|string|unique:employees',
            'passport_number' => 'nullable|string|unique:employees',
            'kra_pin' => ['nullable', 'string', 'unique:employees', 'regex:/^[A-Z]\d{9}[A-Z]$/'],
            'nssf_number' => 'nullable|string',
            'sha_number' => 'nullable|string',
            'date_of_birth' => 'nullable|date|before:today',
            'gender' => 'nullable|in:male,female,other',
            'marital_status' => 'nullable|in:single,married,divorced,widowed',
            'phone' => 'nullable|string|max:20',
            'personal_email' => 'nullable|email',
            'work_email' => 'nullable|email',
            'physical_address' => 'nullable|string',
            'county' => 'nullable|string|max:255',
            'farm_id' => 'nullable|exists:farms,id',
            'department_id' => 'nullable|exists:departments,id',
            'job_title_id' => 'nullable|exists:job_titles,id',
            'manager_id' => 'nullable|exists:employees,id',
            'employment_type' => 'required|in:permanent,contract,casual,intern',
            'hire_date' => 'required|date',
            'probation_end_date' => 'nullable|date|after:hire_date',
            'contract_end_date' => 'nullable|date|after:hire_date',
        ]);

        $validated['employee_number'] = $this->generateEmployeeNumber();

        $employee = Employee::create($validated);

        return redirect()->route('employees.show', $employee)->with('success', 'Employee created successfully.');
    }

    public function show(Request $request, Employee $employee)
    {
        $this->authorize('view', $employee);

        $relations = [
            'farm', 'department', 'jobTitle',
            'manager:id,first_name,last_name,employee_number',
            'nextOfKin', 'documents.uploader',
            'payComponents.payComponent', 'leaveBalances.leaveType',
        ];

        $canViewPay = $request->user()->can('viewSalary', $employee);

        // Salary history, reliefs and payment details are only loaded for users
        // entitled to see them, so they never reach the page payload otherwise.
        if ($canViewPay) {
            $relations[] = 'salaries.approver';
            $relations[] = 'reliefs';
            $relations[] = 'bankDetails';
        }

        $employee->load($relations);

        return Inertia::render('Employees/Show', [
            'employee' => $employee,
            'canViewPay' => $canViewPay,
            'editForm' => Inertia::optional(fn () => $this->editForm($employee)),
        ]);
    }

    public function update(Request $request, Employee $employee)
    {
        $this->authorize('update', $employee);

        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'middle_name' => 'nullable|string|max:255',
            'last_name' => 'required|string|max:255',
            'national_id' => 'nullable|string|unique:employees,national_id,'.$employee->id,
            'passport_number' => 'nullable|string|unique:employees,passport_number,'.$employee->id,
            'kra_pin' => ['nullable', 'string', 'unique:employees,kra_pin,'.$employee->id, 'regex:/^[A-Z]\d{9}[A-Z]$/'],
            'nssf_number' => 'nullable|string',
            'sha_number' => 'nullable|string',
            'date_of_birth' => 'nullable|date|before:today',
            'gender' => 'nullable|in:male,female,other',
            'marital_status' => 'nullable|in:single,married,divorced,widowed',
            'phone' => 'nullable|string|max:20',
            'personal_email' => 'nullable|email',
            'work_email' => 'nullable|email',
            'physical_address' => 'nullable|string',
            'county' => 'nullable|string|max:255',
            'farm_id' => 'nullable|exists:farms,id',
            'department_id' => 'nullable|exists:departments,id',
            'job_title_id' => 'nullable|exists:job_titles,id',
            'manager_id' => 'nullable|exists:employees,id',
            'employment_type' => 'required|in:permanent,contract,casual,intern',
            'hire_date' => 'required|date',
            'probation_end_date' => 'nullable|date|after:hire_date',
            'contract_end_date' => 'nullable|date|after:hire_date',
            'status' => 'nullable|in:active,on_leave,suspended,inactive',
            'has_disability_exemption' => 'boolean',
            'disability_certificate_expiry' => 'nullable|date',
        ]);

        $employee->update($validated);

        return back()->with('success', 'Employee updated successfully.');
    }

    public function destroy(Employee $employee)
    {
        $this->authorize('delete', $employee);

        $employee->delete();

        return redirect()->route('employees.index')->with('success', 'Employee deleted successfully.');
    }

    /**
     * Everything the edit modal needs: the editable record and the select options.
     *
     * @return array{employee: Employee, farms: mixed, departments: mixed, jobTitles: mixed, employees: mixed}
     */
    private function editForm(Employee $employee): array
    {
        $this->authorize('update', $employee);

        return [
            'employee' => $employee,
            'farms' => Farm::where('is_active', true)->orderBy('name')->get(),
            'departments' => Department::orderBy('name')->get(),
            'jobTitles' => JobTitle::orderBy('name')->get(),
            'employees' => Employee::select('id', 'first_name', 'last_name', 'employee_number')
                ->where('status', 'active')
                ->where('id', '!=', $employee->id)
                ->orderBy('first_name')
                ->get(),
        ];
    }

    private function generateEmployeeNumber(): string
    {
        $last = Employee::withTrashed()->orderByDesc('id')->value('employee_number');
        $next = $last ? (int) substr($last, 4) + 1 : 1;

        return 'EMP-'.str_pad($next, 4, '0', STR_PAD_LEFT);
    }
}
