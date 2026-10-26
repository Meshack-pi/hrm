<?php

namespace App\Http\Controllers;

use App\Exports\EmployeesExport;
use App\Exports\PayrollRegisterExport;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Farm;
use App\Models\LeaveRequest;
use App\Models\PayrollRun;
use App\Models\Payslip;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Maatwebsite\Excel\Excel as ExcelWriter;
use Maatwebsite\Excel\Facades\Excel;
use Spatie\Activitylog\Models\Activity;

class ReportController extends Controller
{
    /** Each report section and who may open it; also drives the sidebar Reports group. */
    public const SECTIONS = [
        'headcount' => ['label' => 'Headcount', 'permissions' => ['reports.view'], 'desc' => 'Workforce by farm, department and contract type'],
        'payroll' => ['label' => 'Payroll', 'permissions' => ['salaries.view', 'payroll.export'], 'desc' => 'Pay totals, farm cost and month-on-month change'],
        'statutory' => ['label' => 'Statutory', 'permissions' => ['salaries.view', 'payroll.export'], 'desc' => 'PAYE, NSSF, SHIF, Housing Levy and NITA due'],
        'leave' => ['label' => 'Leave', 'permissions' => ['reports.view'], 'desc' => 'Leave taken and pending by type and farm'],
        'audit' => ['label' => 'Audit Log', 'permissions' => ['audit.view'], 'desc' => 'Who changed what, and when'],
    ];

    public function index(Request $request)
    {
        $first = $this->sections($request->user())->first();

        abort_unless($first, 403);

        return redirect()->route($first['route'], $request->query());
    }

    public function headcount(Request $request)
    {
        $farmId = $this->farmFilter($request);
        $base = fn () => Employee::query()->when($farmId, fn ($q) => $q->where('farm_id', $farmId));

        $farmNames = Farm::pluck('name', 'id');
        $deptNames = Department::pluck('name', 'id');

        $byFarm = $base()
            ->selectRaw("farm_id, count(*) as total,
                sum(status = 'active') as active, sum(status = 'on_leave') as on_leave,
                sum(status = 'suspended') as suspended, sum(status = 'inactive') as inactive,
                sum(employment_type = 'permanent') as permanent, sum(employment_type = 'contract') as contract,
                sum(employment_type = 'casual') as casual, sum(employment_type = 'intern') as intern")
            ->groupBy('farm_id')->get()
            ->map(fn ($r) => [
                'farm' => $farmNames[$r->farm_id] ?? 'Unassigned',
                ...collect(['total', 'active', 'on_leave', 'suspended', 'inactive', 'permanent', 'contract', 'casual', 'intern'])
                    ->mapWithKeys(fn ($k) => [$k => (int) $r->{$k}])->all(),
            ])
            ->sortByDesc('total')->values();

        $byDepartment = $base()->selectRaw('department_id, count(*) as total')
            ->groupBy('department_id')->get()
            ->map(fn ($r) => ['department' => $deptNames[$r->department_id] ?? 'Unassigned', 'total' => (int) $r->total])
            ->sortByDesc('total')->values();

        return $this->render($request, 'headcount', [
            'summary' => [
                'total' => $byFarm->sum('total'),
                'active' => $byFarm->sum('active'),
                'onLeave' => $byFarm->sum('on_leave'),
                'newHires' => $base()->where('hire_date', '>=', now()->subDays(30))->count(),
                'contractsEnding' => $base()->where('status', 'active')
                    ->whereBetween('contract_end_date', [now(), now()->addDays(60)])->count(),
            ],
            'byFarm' => $byFarm,
            'byDepartment' => $byDepartment,
            'recentHires' => $base()->with('farm:id,name', 'jobTitle:id,name')
                ->where('hire_date', '>=', now()->subDays(30))->orderByDesc('hire_date')->limit(10)
                ->get()->map(fn ($e) => [
                    'id' => $e->id, 'uuid' => $e->uuid, 'name' => $e->full_name, 'number' => $e->employee_number,
                    'farm' => $e->farm?->name, 'title' => $e->jobTitle?->name,
                    'type' => $e->employment_type, 'hired' => $e->hire_date->toDateString(),
                ]),
        ]);
    }

    public function payroll(Request $request)
    {
        $farmId = $this->farmFilter($request);
        [$runs, $run] = $this->runs($request);

        $previous = $run ? PayrollRun::where(fn ($q) => $q
            ->where('period_year', '<', $run->period_year)
            ->orWhere(fn ($q) => $q->where('period_year', $run->period_year)->where('period_month', '<', $run->period_month)))
            ->orderByDesc('period_year')->orderByDesc('period_month')->first() : null;

        return $this->render($request, 'payroll', [
            'runs' => $runs,
            'selected' => $run,
            'totals' => $run ? $this->payrollTotals($run, $farmId) : null,
            'previous' => $previous ? ['period' => $previous->period_label, ...$this->payrollTotals($previous, $farmId)] : null,
            'byFarm' => $run ? $this->payrollGrouped($run, $farmId, 'employees.farm_id', Farm::pluck('name', 'id')) : [],
            'byDepartment' => $run ? $this->payrollGrouped($run, $farmId, 'employees.department_id', Department::pluck('name', 'id')) : [],
            'paymentStatus' => $run ? $this->payslipQuery($run, $farmId)
                ->selectRaw('payslips.payment_status as status, count(*) as count, sum(payslips.net_pay) as amount')
                ->groupBy('payslips.payment_status')->get()
                ->map(fn ($r) => ['status' => $r->status, 'count' => (int) $r->count, 'amount' => round((float) $r->amount, 2)])
                : [],
        ]);
    }

    public function statutory(Request $request)
    {
        $farmId = $this->farmFilter($request);
        [$runs, $run] = $this->runs($request);

        $byFarm = [];
        if ($run) {
            $farmNames = Farm::pluck('name', 'id');
            $byFarm = $this->payslipQuery($run, $farmId)
                ->selectRaw('employees.farm_id, count(*) as headcount, sum(paye) as paye,
                    sum(nssf_employee + nssf_employer) as nssf, sum(shif) as shif,
                    sum(ahl_employee + ahl_employer) as ahl, sum(nita_employer) as nita')
                ->groupBy('employees.farm_id')->get()
                ->map(fn ($r) => [
                    'farm' => $farmNames[$r->farm_id] ?? 'Unassigned',
                    'headcount' => (int) $r->headcount,
                    ...collect(['paye', 'nssf', 'shif', 'ahl', 'nita'])->mapWithKeys(fn ($k) => [$k => round((float) $r->{$k}, 2)])->all(),
                ])
                ->sortBy('farm')->values();
        }

        return $this->render($request, 'statutory', [
            'runs' => $runs,
            'selected' => $run,
            'remittances' => $run ? $this->statutoryTotals($run, $farmId) : [],
            'byFarm' => $byFarm,
            'dueDate' => $run ? now()->setDate($run->period_year, $run->period_month, 1)->addMonthNoOverflow()->setDay(9)->toDateString() : null,
        ]);
    }

    public function leave(Request $request)
    {
        $farmId = $this->farmFilter($request);
        $year = $request->integer('year') ?: (int) date('Y');
        $farmNames = Farm::pluck('name', 'id');

        $base = fn () => LeaveRequest::query()
            ->join('employees', 'employees.id', '=', 'leave_requests.employee_id')
            ->whereYear('leave_requests.start_date', $year)
            ->when($farmId, fn ($q) => $q->where('employees.farm_id', $farmId));

        $statusSums = "sum(case when leave_requests.status = 'approved' then leave_requests.days else 0 end) as days_taken,
            sum(leave_requests.status = 'approved') as approved,
            sum(leave_requests.status = 'pending') as pending,
            sum(leave_requests.status = 'rejected') as rejected";

        $shape = fn ($r) => [
            'days_taken' => round((float) $r->days_taken, 2),
            'approved' => (int) $r->approved,
            'pending' => (int) $r->pending,
            'rejected' => (int) $r->rejected,
        ];

        $byType = $base()->join('leave_types', 'leave_types.id', '=', 'leave_requests.leave_type_id')
            ->selectRaw("leave_types.name as type, {$statusSums}")
            ->groupBy('leave_types.name')->get()
            ->map(fn ($r) => ['type' => $r->type, ...$shape($r)])
            ->sortByDesc('days_taken')->values();

        $byFarm = $base()->selectRaw("employees.farm_id, {$statusSums}")
            ->groupBy('employees.farm_id')->get()
            ->map(fn ($r) => ['farm' => $farmNames[$r->farm_id] ?? 'Unassigned', ...$shape($r)])
            ->sortByDesc('days_taken')->values();

        $onLeave = LeaveRequest::where('status', 'approved')
            ->whereDate('start_date', '<=', today())->whereDate('end_date', '>=', today())
            ->whereHas('employee', fn ($q) => $q->when($farmId, fn ($q) => $q->where('farm_id', $farmId)))
            ->with('employee:id,first_name,last_name,farm_id', 'employee.farm:id,name', 'leaveType:id,name')
            ->orderBy('end_date')->get()
            ->map(fn ($r) => [
                'id' => $r->id, 'employee' => $r->employee?->full_name, 'farm' => $r->employee?->farm?->name,
                'type' => $r->leaveType?->name, 'from' => $r->start_date->toDateString(), 'to' => $r->end_date->toDateString(),
            ]);

        return $this->render($request, 'leave', [
            'year' => $year,
            'summary' => [
                'daysTaken' => round($byType->sum('days_taken'), 2),
                'approved' => $byType->sum('approved'),
                'pending' => $byType->sum('pending'),
                'rejected' => $byType->sum('rejected'),
                'onLeaveToday' => $onLeave->count(),
            ],
            'byType' => $byType,
            'byFarm' => $byFarm,
            'onLeave' => $onLeave,
        ], ['year' => $year]);
    }

    public function audit(Request $request)
    {
        $subject = $request->query('subject');
        $event = $request->query('event');
        $causerId = $request->integer('causer_id') ?: null;

        $entries = Activity::with('causer:id,name')
            ->when($subject, fn ($q) => $q->where('subject_type', 'like', '%\\'.$subject))
            ->when($event, fn ($q) => $q->where('event', $event))
            ->when($causerId, fn ($q) => $q->where('causer_type', User::class)->where('causer_id', $causerId))
            ->latest()->paginate(25)->withQueryString()
            ->through(fn ($a) => [
                'id' => $a->id,
                'event' => $a->event ?? $a->description,
                'subject' => class_basename($a->subject_type ?? ''),
                'subjectId' => $a->subject_id,
                'causer' => $a->causer?->name,
                'at' => $a->created_at,
                'changes' => collect($a->attribute_changes?->get('attributes') ?? [])
                    ->map(fn ($new, $field) => [
                        'field' => $field,
                        'old' => $a->attribute_changes?->get('old')[$field] ?? null,
                        'new' => $new,
                    ])->values(),
            ]);

        return $this->render($request, 'audit', [
            'entries' => $entries,
            'subjects' => Activity::query()->distinct()->pluck('subject_type')->filter()
                ->map(fn ($t) => class_basename($t))->unique()->sort()->values(),
            'causers' => User::whereIn('id', Activity::where('causer_type', User::class)->distinct()->select('causer_id'))
                ->orderBy('name')->get(['id', 'name']),
        ], ['subject' => $subject, 'event' => $event, 'causer_id' => $causerId]);
    }

    public function exportEmployees(Request $request)
    {
        [$writer, $ext] = $this->format($request);

        return Excel::download(new EmployeesExport($this->farmFilter($request)), 'employees-'.now()->format('Ymd').".{$ext}", $writer);
    }

    public function exportPayrollRegister(Request $request, PayrollRun $run)
    {
        [$writer, $ext] = $this->format($request);
        $name = sprintf('payroll-register-%04d-%02d.%s', $run->period_year, $run->period_month, $ext);

        return Excel::download(new PayrollRegisterExport($run, $this->farmFilter($request)), $name, $writer);
    }

    // ---------------------------------------------------------------------

    private function render(Request $request, string $section, array $props, array $extraFilters = [])
    {
        return Inertia::render('Reports/'.ucfirst($section), [
            ...$props,
            'section' => $section,
            'sections' => $this->sections($request->user()),
            'farms' => Farm::orderBy('name')->get(['id', 'name']),
            'filters' => ['farm_id' => $this->farmFilter($request), 'run_id' => $props['selected']->id ?? null, ...$extraFilters],
            'can' => [
                'exportEmployees' => $request->user()->can('reports.view'),
                'exportPayroll' => $request->user()->can('payroll.export'),
            ],
        ]);
    }

    public function sections(User $user): Collection
    {
        return collect(self::SECTIONS)
            ->filter(fn ($s) => $user->canAny($s['permissions']))
            ->map(fn ($s, $key) => ['key' => $key, 'label' => $s['label'], 'desc' => $s['desc'], 'route' => "reports.{$key}"])
            ->values();
    }

    private function farmFilter(Request $request): ?int
    {
        return $request->integer('farm_id') ?: null;
    }

    private function runs(Request $request): array
    {
        $runs = PayrollRun::withCount('payslips')
            ->orderByDesc('period_year')->orderByDesc('period_month')->limit(24)->get()
            ->each->append('period_label');

        return [$runs, $runs->firstWhere('id', $request->integer('run_id')) ?? $runs->first()];
    }

    private function format(Request $request): array
    {
        return $request->query('format') === 'csv' ? [ExcelWriter::CSV, 'csv'] : [ExcelWriter::XLSX, 'xlsx'];
    }

    private function payslipQuery(PayrollRun $run, ?int $farmId)
    {
        return Payslip::query()
            ->join('employees', 'employees.id', '=', 'payslips.employee_id')
            ->where('payslips.payroll_run_id', $run->id)
            ->when($farmId, fn ($q) => $q->where('employees.farm_id', $farmId));
    }

    private const PAY_SUMS = 'count(*) as headcount, sum(payslips.gross_pay) as gross,
        sum(payslips.total_deductions) as deductions, sum(payslips.net_pay) as net,
        sum(payslips.gross_pay + payslips.nssf_employer + payslips.ahl_employer + payslips.nita_employer) as employer_cost';

    private function shapePay($r): array
    {
        return [
            'headcount' => (int) $r->headcount,
            'gross' => round((float) $r->gross, 2),
            'deductions' => round((float) $r->deductions, 2),
            'net' => round((float) $r->net, 2),
            'employer_cost' => round((float) $r->employer_cost, 2),
        ];
    }

    private function payrollTotals(PayrollRun $run, ?int $farmId): array
    {
        return $this->shapePay($this->payslipQuery($run, $farmId)->selectRaw(self::PAY_SUMS)->first());
    }

    private function payrollGrouped(PayrollRun $run, ?int $farmId, string $column, $names): array
    {
        return $this->payslipQuery($run, $farmId)
            ->selectRaw("{$column} as group_id, ".self::PAY_SUMS)
            ->groupBy($column)->get()
            ->map(fn ($r) => ['name' => $names[$r->group_id] ?? 'Unassigned', ...$this->shapePay($r)])
            ->sortByDesc('gross')->values()->all();
    }

    private function statutoryTotals(PayrollRun $run, ?int $farmId): array
    {
        $t = $this->payslipQuery($run, $farmId)
            ->selectRaw('sum(paye) as paye, sum(nssf_employee) as nssf_ee, sum(nssf_employer) as nssf_er,
                sum(shif) as shif, sum(ahl_employee) as ahl_ee, sum(ahl_employer) as ahl_er, sum(nita_employer) as nita')
            ->first();

        $v = fn ($x) => round((float) $x, 2);

        return [
            ['label' => 'PAYE', 'body' => 'KRA (iTax)', 'employee' => $v($t->paye), 'employer' => 0.0],
            ['label' => 'NSSF', 'body' => 'NSSF', 'employee' => $v($t->nssf_ee), 'employer' => $v($t->nssf_er)],
            ['label' => 'SHIF', 'body' => 'SHA', 'employee' => $v($t->shif), 'employer' => 0.0],
            ['label' => 'Housing Levy', 'body' => 'KRA (iTax)', 'employee' => $v($t->ahl_ee), 'employer' => $v($t->ahl_er)],
            ['label' => 'NITA', 'body' => 'NITA', 'employee' => 0.0, 'employer' => $v($t->nita)],
        ];
    }
}
