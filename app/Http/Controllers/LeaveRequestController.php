<?php

namespace App\Http\Controllers;

use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Services\Leave\LeaveAccrualService;
use App\Services\Leave\LeaveDayCounter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class LeaveRequestController extends Controller
{
    public function __construct(
        private LeaveDayCounter $dayCounter,
        private LeaveAccrualService $accrual,
    ) {}

    public function index(Request $request)
    {
        $user = $request->user();

        $query = LeaveRequest::with([
            'employee:id,employee_number,first_name,last_name',
            'leaveType:id,name,code',
            'approver:id,name',
            'reliefEmployee:id,first_name,last_name',
        ]);

        // Scope what each user may see: everything, their team, or just their own.
        if ($user->can('leave.view_all')) {
            // no restriction
        } elseif ($user->can('leave.view_team') && $user->employee_id) {
            $query->whereHas('employee', fn ($q) => $q->where('manager_id', $user->employee_id));
        } else {
            $query->where('employee_id', $user->employee_id);
        }

        $requests = $query
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->when($request->leave_type_id, fn ($q, $t) => $q->where('leave_type_id', $t))
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Leave/Requests/Index', [
            'requests' => $requests,
            'leaveTypes' => LeaveType::where('is_active', true)->orderBy('name')->get(),
            'filters' => $request->only(['status', 'leave_type_id']),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'leave_type_id' => 'required|exists:leave_types,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'reason' => 'nullable|string|max:1000',
            'relief_employee_id' => 'nullable|exists:employees,id',
        ]);

        $employeeId = $request->user()->employee_id;

        if (! $employeeId) {
            return back()->with('error', 'Your user account is not linked to an employee record.');
        }

        $leaveType = LeaveType::findOrFail($validated['leave_type_id']);
        $days = $this->dayCounter->count($validated['start_date'], $validated['end_date'], $leaveType);

        if ($days <= 0) {
            return back()->with('error', 'The selected dates contain no working days.');
        }

        if ($this->overlapsExistingRequest($employeeId, $validated['start_date'], $validated['end_date'])) {
            return back()->with('error', 'You already have a leave request covering these dates.');
        }

        // Unpaid leave has no balance to draw down, so only check balances for paid types.
        if ($leaveType->is_paid) {
            $balance = $this->currentBalance($employeeId, $leaveType->id);

            if ($balance !== null && $days > $balance) {
                return back()->with('error', sprintf(
                    'You have %.2f day(s) of %s available but requested %.2f.',
                    $balance,
                    $leaveType->name,
                    $days
                ));
            }
        }

        DB::transaction(function () use ($validated, $employeeId, $days, $leaveType) {
            LeaveRequest::create([
                ...$validated,
                'employee_id' => $employeeId,
                'days' => $days,
                'status' => 'pending',
            ]);

            if ($leaveType->is_paid) {
                LeaveBalance::where('employee_id', $employeeId)
                    ->where('leave_type_id', $leaveType->id)
                    ->where('year', (int) date('Y', strtotime($validated['start_date'])))
                    ->increment('pending', $days);
            }
        });

        return back()->with('success', 'Leave request submitted for approval.');
    }

    public function approve(Request $request, LeaveRequest $leaveRequest)
    {
        if ($leaveRequest->status !== 'pending') {
            return back()->with('error', 'Only a pending request can be approved.');
        }

        if (! $this->canApprove($request->user(), $leaveRequest)) {
            return back()->with('error', 'You are not authorised to approve this request.');
        }

        DB::transaction(function () use ($leaveRequest) {
            $leaveRequest->update([
                'status' => 'approved',
                'approver_id' => auth()->id(),
                'approved_at' => now(),
            ]);

            // Move the days from pending into taken.
            if ($leaveRequest->leaveType->is_paid) {
                LeaveBalance::where('employee_id', $leaveRequest->employee_id)
                    ->where('leave_type_id', $leaveRequest->leave_type_id)
                    ->where('year', $leaveRequest->start_date->year)
                    ->update([
                        'pending' => DB::raw('GREATEST(pending - '.$leaveRequest->days.', 0)'),
                        'taken' => DB::raw('taken + '.$leaveRequest->days),
                    ]);
            }
        });

        return back()->with('success', 'Leave request approved.');
    }

    public function reject(Request $request, LeaveRequest $leaveRequest)
    {
        $validated = $request->validate([
            'rejection_reason' => 'required|string|max:1000',
        ]);

        if ($leaveRequest->status !== 'pending') {
            return back()->with('error', 'Only a pending request can be rejected.');
        }

        if (! $this->canApprove($request->user(), $leaveRequest)) {
            return back()->with('error', 'You are not authorised to reject this request.');
        }

        DB::transaction(function () use ($leaveRequest, $validated) {
            $leaveRequest->update([
                'status' => 'rejected',
                'approver_id' => auth()->id(),
                'approved_at' => now(),
                'rejection_reason' => $validated['rejection_reason'],
            ]);

            $this->releasePendingDays($leaveRequest);
        });

        return back()->with('success', 'Leave request rejected.');
    }

    public function cancel(Request $request, LeaveRequest $leaveRequest)
    {
        if ($leaveRequest->employee_id !== $request->user()->employee_id) {
            return back()->with('error', 'You can only cancel your own leave requests.');
        }

        if ($leaveRequest->status !== 'pending') {
            return back()->with('error', 'Only a pending request can be cancelled.');
        }

        DB::transaction(function () use ($leaveRequest) {
            $leaveRequest->update(['status' => 'cancelled']);
            $this->releasePendingDays($leaveRequest);
        });

        return back()->with('success', 'Leave request cancelled.');
    }

    private function releasePendingDays(LeaveRequest $leaveRequest): void
    {
        if (! $leaveRequest->leaveType->is_paid) {
            return;
        }

        LeaveBalance::where('employee_id', $leaveRequest->employee_id)
            ->where('leave_type_id', $leaveRequest->leave_type_id)
            ->where('year', $leaveRequest->start_date->year)
            ->update(['pending' => DB::raw('GREATEST(pending - '.$leaveRequest->days.', 0)')]);
    }

    private function canApprove($user, LeaveRequest $leaveRequest): bool
    {
        if ($user->can('leave.approve_all')) {
            return true;
        }

        return $user->can('leave.approve_team')
            && $user->employee_id
            && $leaveRequest->employee->manager_id === $user->employee_id;
    }

    private function overlapsExistingRequest(int $employeeId, string $start, string $end): bool
    {
        return LeaveRequest::where('employee_id', $employeeId)
            ->whereIn('status', ['pending', 'approved'])
            ->where('start_date', '<=', $end)
            ->where('end_date', '>=', $start)
            ->exists();
    }

    private function currentBalance(int $employeeId, int $leaveTypeId): ?float
    {
        $balance = LeaveBalance::where('employee_id', $employeeId)
            ->where('leave_type_id', $leaveTypeId)
            ->where('year', (int) date('Y'))
            ->first();

        return $balance?->balance;
    }
}
