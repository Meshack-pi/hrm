<?php

namespace App\Http\Controllers;

use App\Models\CompanyEvent;
use App\Models\Farm;
use App\Models\LeaveRequest;
use App\Models\PublicHoliday;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;

class CalendarController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $month = $this->month($request->query('month'));
        $farmId = $request->integer('farm_id') ?: null;

        // The grid shows whole weeks (Mon–Sun), so fetch the days spilling into adjacent months too.
        $from = $month->startOfMonth()->startOfWeek(CarbonImmutable::MONDAY);
        $to = $month->endOfMonth()->endOfWeek(CarbonImmutable::SUNDAY);

        $events = $this->eventsBetween($user, $from, $to, $farmId);

        $today = CarbonImmutable::today();
        $upcoming = $this->eventsBetween($user, $today, $today->addDays(45), $farmId)
            ->filter(fn ($e) => $e['end'] >= $today->toDateString())
            ->sortBy('start')->take(8)->values();

        return Inertia::render('Calendar/Index', [
            'month' => $month->format('Y-m'),
            'range' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'events' => $events->values(),
            'upcoming' => $upcoming,
            'farms' => Farm::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'filters' => ['farm_id' => $farmId],
            'categories' => CompanyEvent::CATEGORIES,
            'leaveScope' => $this->leaveScope($user),
            'holidaysSyncedAt' => PublicHoliday::max('synced_at'),
            'can' => [
                'manageEvents' => $user->can('calendar.manage'),
                'manageHolidays' => $user->can('holidays.manage'),
                'seePayroll' => $this->seesPayroll($user),
            ],
        ]);
    }

    private function month(?string $value): CarbonImmutable
    {
        if ($value && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $value)) {
            return CarbonImmutable::createFromFormat('!Y-m', $value);
        }

        return CarbonImmutable::today()->startOfMonth();
    }

    private function eventsBetween(User $user, CarbonImmutable $from, CarbonImmutable $to, ?int $farmId): Collection
    {
        return collect()
            ->concat($this->holidays($from, $to))
            ->concat($this->companyEvents($user, $from, $to, $farmId))
            ->concat($this->leave($user, $from, $to, $farmId))
            ->concat($this->seesPayroll($user) ? $this->payrollDeadlines($from, $to) : []);
    }

    private function holidays(CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        return PublicHoliday::active()->whereBetween('date', [$from, $to])->orderBy('date')->get()
            ->map(fn ($h) => [
                'id' => "holiday-{$h->id}",
                'type' => 'holiday',
                'title' => $h->name,
                'start' => $h->date->toDateString(),
                'end' => $h->date->toDateString(),
                'meta' => ['substitute' => $h->is_substitute, 'source' => $h->source],
            ]);
    }

    private function companyEvents(User $user, CarbonImmutable $from, CarbonImmutable $to, ?int $farmId): Collection
    {
        $canManage = $user->can('calendar.manage');

        return CompanyEvent::with('farm:id,name', 'creator:id,name')
            ->where('start_date', '<=', $to)->where('end_date', '>=', $from)
            // An event for one farm still shows when filtering by that farm; company-wide ones always show.
            ->when($farmId, fn ($q) => $q->where(fn ($q) => $q->whereNull('farm_id')->orWhere('farm_id', $farmId)))
            ->orderBy('start_date')->get()
            ->map(fn ($e) => [
                'id' => "event-{$e->id}",
                'type' => 'event',
                'title' => $e->title,
                'start' => $e->start_date->toDateString(),
                'end' => $e->end_date->toDateString(),
                'time' => $e->start_time ? substr($e->start_time, 0, 5).($e->end_time ? '–'.substr($e->end_time, 0, 5) : '') : null,
                'meta' => [
                    'eventId' => $e->id,
                    'category' => $e->category,
                    'description' => $e->description,
                    'location' => $e->location,
                    'farm' => $e->farm?->name,
                    'farmId' => $e->farm_id,
                    'startTime' => $e->start_time ? substr($e->start_time, 0, 5) : null,
                    'endTime' => $e->end_time ? substr($e->end_time, 0, 5) : null,
                    'createdBy' => $e->creator?->name,
                    'editable' => $canManage,
                ],
            ]);
    }

    /** 'all' for HR, 'team' for line managers, 'own' for everyone else. */
    private function leaveScope(User $user): string
    {
        if ($user->can('leave.view_all')) {
            return 'all';
        }

        return $user->can('leave.view_team') && $user->employee_id ? 'team' : 'own';
    }

    private function leave(User $user, CarbonImmutable $from, CarbonImmutable $to, ?int $farmId): Collection
    {
        $scope = $this->leaveScope($user);

        if ($scope === 'own' && ! $user->employee_id) {
            return collect();
        }

        return LeaveRequest::with('employee:id,first_name,last_name,farm_id', 'leaveType:id,name')
            ->where('start_date', '<=', $to)->where('end_date', '>=', $from)
            ->where(function ($q) use ($scope, $user) {
                // Others' leave only once approved; your own pending requests show too.
                $q->where(fn ($q) => $q->where('status', 'approved')->when($scope !== 'all', fn ($q) => $scope === 'team'
                    ? $q->whereHas('employee', fn ($e) => $e->where(fn ($e) => $e->where('manager_id', $user->employee_id)->orWhere('id', $user->employee_id)))
                    : $q->where('employee_id', $user->employee_id)));

                if ($user->employee_id) {
                    $q->orWhere(fn ($q) => $q->where('employee_id', $user->employee_id)->where('status', 'pending'));
                }
            })
            ->when($farmId, fn ($q) => $q->whereHas('employee', fn ($e) => $e->where('farm_id', $farmId)))
            ->orderBy('start_date')->get()
            ->map(fn ($r) => [
                'id' => "leave-{$r->id}",
                'type' => 'leave',
                'title' => $r->employee_id === $user->employee_id
                    ? "Your leave · {$r->leaveType?->name}"
                    : "{$r->employee?->full_name} · {$r->leaveType?->name}",
                'start' => $r->start_date->toDateString(),
                'end' => $r->end_date->toDateString(),
                'meta' => [
                    'status' => $r->status,
                    'days' => (float) $r->days,
                    'mine' => $r->employee_id === $user->employee_id,
                ],
            ]);
    }

    private function seesPayroll(User $user): bool
    {
        return $user->canAny(['payroll.create', 'payroll.calculate', 'payroll.approve', 'payroll.mark_paid']);
    }

    /** KRA, NSSF, SHA and the Housing Levy are all due by the 9th of the following month. */
    private function payrollDeadlines(CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        return collect(CarbonPeriod::create($from->startOfMonth(), '1 month', $to))
            ->map(fn ($m) => CarbonImmutable::parse($m)->setDay(9))
            ->filter(fn ($d) => $d->between($from, $to))
            ->map(fn ($d) => [
                'id' => 'payroll-'.$d->format('Y-m'),
                'type' => 'payroll',
                'title' => 'Statutory remittances due',
                'start' => $d->toDateString(),
                'end' => $d->toDateString(),
                'meta' => [
                    'description' => sprintf('PAYE, NSSF, SHIF and Housing Levy for %s. If the 9th is a weekend or holiday, pay on the previous working day.',
                        $d->subMonth()->format('F Y')),
                ],
            ])
            ->values();
    }
}
