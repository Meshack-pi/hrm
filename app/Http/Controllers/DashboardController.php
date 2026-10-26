<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Farm;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\PayrollRun;
use App\Models\Payslip;
use App\Models\PublicHoliday;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function __invoke(Request $request)
    {
        $user = $request->user();

        return Inertia::render('Dashboard', [
            'stats' => $this->stats($user),
            'payroll' => $this->latestRun($user),
            'pendingApprovals' => $this->pendingApprovals($user),
            'onLeaveToday' => $this->onLeaveToday($user),
            'contractsEnding' => $user->can('employees.view_all') ? $this->contractsEnding() : null,
            'me' => $user->employee_id ? $this->me($user) : null,
            'nextHoliday' => $this->nextHoliday(),
        ]);
    }

    private function nextHoliday(): ?array
    {
        $holiday = PublicHoliday::active()->whereDate('date', '>=', today())->orderBy('date')->first();

        return $holiday ? [
            'name' => $holiday->name,
            'date' => $holiday->date->toDateString(),
            'daysAway' => (int) today()->diffInDays($holiday->date),
        ] : null;
    }

    /**
     * The employees this user may see in aggregate: everyone, their direct reports, or nobody.
     */
    private function visibleEmployees(User $user): ?Builder
    {
        if ($user->can('employees.view_all')) {
            return Employee::query();
        }

        if ($user->can('employees.view_team') && $user->employee_id) {
            return Employee::where('manager_id', $user->employee_id);
        }

        return null;
    }

    /**
     * Leave requests this user may act on, mirroring LeaveRequestController's approval rules.
     */
    private function approvableLeave(User $user): ?Builder
    {
        if ($user->can('leave.approve_all')) {
            return LeaveRequest::query();
        }

        if ($user->can('leave.approve_team') && $user->employee_id) {
            return LeaveRequest::whereHas('employee', fn ($q) => $q->where('manager_id', $user->employee_id));
        }

        return null;
    }

    private function stats(User $user): array
    {
        $employees = $this->visibleEmployees($user);
        $approvable = $this->approvableLeave($user);

        return [
            'employees' => $employees ? [
                'total' => (clone $employees)->count(),
                'active' => (clone $employees)->where('status', 'active')->count(),
                'onLeave' => (clone $employees)->where('status', 'on_leave')->count(),
                'scope' => $user->can('employees.view_all') ? 'company' : 'team',
            ] : null,
            'farms' => $user->can('employees.view_all') ? [
                'active' => Farm::where('is_active', true)->count(),
                'breakdown' => Farm::where('is_active', true)->withCount(['employees' => fn ($q) => $q->whereIn('status', Employee::PAYABLE_STATUSES)])
                    ->orderBy('name')->get(['id', 'name', 'code'])
                    ->map(fn ($f) => ['name' => $f->name, 'code' => $f->code, 'employees' => $f->employees_count]),
            ] : null,
            'pendingLeave' => $approvable ? (clone $approvable)->where('status', 'pending')->count() : null,
            'users' => $user->can('users.view') ? [
                'total' => User::count(),
                'active' => User::where('is_active', true)->count(),
            ] : null,
        ];
    }

    private function latestRun(User $user): ?array
    {
        if (! $user->canAny(['payroll.create', 'payroll.calculate', 'payroll.approve', 'payroll.mark_paid', 'payroll.export'])) {
            return null;
        }

        $run = PayrollRun::with('calculatedByUser:id,name')->withCount('payslips')
            ->orderByDesc('period_year')->orderByDesc('period_month')->first();

        if (! $run) {
            return ['run' => null];
        }

        $awaitingMe = $run->status === 'calculated'
            && $user->can('payroll.approve')
            && $run->calculated_by !== $user->id;

        return [
            'run' => [
                'id' => $run->id,
                'period' => $run->period_label,
                'status' => $run->status,
                'payslips' => $run->payslips_count,
                'gross' => (float) $run->total_gross,
                'net' => (float) $run->total_net,
                'employerCost' => (float) $run->total_employer_cost,
                'calculatedBy' => $run->calculatedByUser?->name,
            ],
            'awaitingMyApproval' => $awaitingMe,
            'awaitingOtherApprover' => $run->status === 'calculated' && $run->calculated_by === $user->id,
            'pendingPayment' => $run->status === 'approved',
        ];
    }

    private function pendingApprovals(User $user): ?array
    {
        $approvable = $this->approvableLeave($user);

        if (! $approvable) {
            return null;
        }

        return $approvable->where('status', 'pending')
            ->with(['employee:id,employee_number,first_name,last_name,farm_id', 'employee.farm:id,name', 'leaveType:id,name'])
            ->orderBy('start_date')->limit(5)->get()
            ->map(fn ($r) => [
                'id' => $r->id,
                'employee' => $r->employee?->full_name,
                'employeeNumber' => $r->employee?->employee_number,
                'farm' => $r->employee?->farm?->name,
                'type' => $r->leaveType?->name,
                'start' => $r->start_date->toDateString(),
                'end' => $r->end_date->toDateString(),
                'days' => (float) $r->days,
            ])
            ->all();
    }

    private function onLeaveToday(User $user): ?array
    {
        $employees = $this->visibleEmployees($user);

        if (! $employees) {
            return null;
        }

        $ids = (clone $employees)->select('id');

        return LeaveRequest::where('status', 'approved')
            ->whereIn('employee_id', $ids)
            ->whereDate('start_date', '<=', today())
            ->whereDate('end_date', '>=', today())
            ->with(['employee:id,first_name,last_name,farm_id', 'employee.farm:id,name', 'leaveType:id,name'])
            ->orderBy('end_date')->limit(6)->get()
            ->map(fn ($r) => [
                'id' => $r->id,
                'employee' => $r->employee?->full_name,
                'farm' => $r->employee?->farm?->name,
                'type' => $r->leaveType?->name,
                'returns' => $r->end_date->copy()->addDay()->toDateString(),
            ])
            ->all();
    }

    private function contractsEnding(): array
    {
        return Employee::where('status', 'active')
            ->whereBetween('contract_end_date', [today(), today()->addDays(60)])
            ->with('farm:id,name', 'jobTitle:id,name')
            ->orderBy('contract_end_date')->limit(5)
            ->get(['id', 'uuid', 'employee_number', 'first_name', 'last_name', 'farm_id', 'job_title_id', 'contract_end_date'])
            ->map(fn ($e) => [
                'id' => $e->id,
                'uuid' => $e->uuid,
                'name' => $e->full_name,
                'farm' => $e->farm?->name,
                'title' => $e->jobTitle?->name,
                'ends' => $e->contract_end_date->toDateString(),
                'daysLeft' => (int) today()->diffInDays($e->contract_end_date),
            ])
            ->all();
    }

    private function me(User $user): array
    {
        $balances = LeaveBalance::with('leaveType:id,name,code')
            ->where('employee_id', $user->employee_id)
            ->where('year', (int) date('Y'))
            ->get()
            ->map(fn ($b) => [
                'type' => $b->leaveType?->name,
                'code' => $b->leaveType?->code,
                'balance' => $b->balance,
                'taken' => (float) $b->taken,
                'pending' => (float) $b->pending,
            ])
            ->sortBy(fn ($b) => $b['code'] === 'ANNUAL' ? 0 : 1)
            ->values();

        // Payslips only become visible to the employee once the run is approved.
        $payslips = Payslip::where('employee_id', $user->employee_id)
            ->whereHas('payrollRun', fn ($q) => $q->whereIn('status', Payslip::VISIBLE_RUN_STATUSES))
            ->with('payrollRun:id,period_month,period_year')
            ->get()
            ->sortByDesc(fn ($p) => $p->payrollRun->period_year * 100 + $p->payrollRun->period_month)
            ->take(12)
            ->values();

        $latest = $payslips->first();

        return [
            'leaveBalances' => $balances,
            'pendingRequests' => LeaveRequest::where('employee_id', $user->employee_id)->where('status', 'pending')->count(),
            'latestPayslip' => $latest ? [
                'id' => $latest->id,
                'period' => $latest->payrollRun->period_label,
                'gross' => (float) $latest->gross_pay,
                'deductions' => (float) $latest->total_deductions,
                'statutory' => round((float) ($latest->paye + $latest->nssf_employee + $latest->shif + $latest->ahl_employee), 2),
                'other' => (float) $latest->total_other_deductions,
                'net' => (float) $latest->net_pay,
                'paymentStatus' => $latest->payment_status,
            ] : null,
            'payslips' => $payslips->map(fn ($p) => [
                'id' => $p->id,
                'period' => $p->payrollRun->period_label,
                'gross' => (float) $p->gross_pay,
                'deductions' => (float) $p->total_deductions,
                'net' => (float) $p->net_pay,
                'paymentStatus' => $p->payment_status,
            ]),
        ];
    }
}
