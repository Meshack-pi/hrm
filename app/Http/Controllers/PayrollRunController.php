<?php

namespace App\Http\Controllers;

use App\Models\PayrollRun;
use App\Services\Payroll\PayrollCalculator;
use App\Services\Payroll\PayrollValidator;
use App\Services\Payroll\PayslipDocument;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PayrollRunController extends Controller
{
    public function __construct(
        private PayrollCalculator $calculator,
        private PayrollValidator $validator,
    ) {}

    public function index()
    {
        return Inertia::render('Payroll/Runs/Index', [
            'runs' => PayrollRun::with(['calculatedByUser:id,name', 'approvedByUser:id,name'])
                ->withCount('payslips')
                ->orderByDesc('period_year')
                ->orderByDesc('period_month')
                ->paginate(15),
        ]);
    }

    public function create()
    {
        return Inertia::render('Payroll/Runs/Create', [
            'missingDetails' => $this->validator->checkMissingDetails(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'period_month' => 'required|integer|min:1|max:12',
            'period_year' => 'required|integer|min:2020|max:2100',
            'notes' => 'nullable|string',
        ]);

        $exists = PayrollRun::where('period_month', $validated['period_month'])
            ->where('period_year', $validated['period_year'])
            ->exists();

        if ($exists) {
            return back()->with('error', 'A payroll run already exists for this period.');
        }

        $run = PayrollRun::create($validated);

        return redirect()->route('payroll.runs.show', $run)
            ->with('success', 'Payroll run created. You can now enter variables and calculate.');
    }

    public function show(Request $request, PayrollRun $run, PayslipDocument $document)
    {
        $run->load(['calculatedByUser:id,name', 'approvedByUser:id,name']);

        $payslips = $run->payslips()
            ->with('employee:id,employee_number,first_name,last_name,farm_id,department_id', 'employee.farm:id,name', 'employee.department:id,name')
            ->paginate(50);

        return Inertia::render('Payroll/Runs/Show', [
            'run' => $run,
            'payslips' => $payslips,
            'validation' => $run->status === 'calculated'
                ? $this->validator->validateForApproval($run)
                : null,
            'canApprove' => $this->userCanApprove($run),
            'canViewBreakdown' => $request->user()->canAny(['salaries.view', 'payroll.export']),
            // Loaded only when someone opens an employee's breakdown (?payslip=<id>).
            'breakdown' => Inertia::optional(fn () => $this->breakdown($run, $request->integer('payslip'), $document)),
        ]);
    }

    public function calculate(PayrollRun $run)
    {
        if ($run->isLocked()) {
            return back()->with('error', 'This payroll run is locked and cannot be recalculated.');
        }

        if (! in_array($run->status, ['draft', 'calculated'])) {
            return back()->with('error', 'Only draft or calculated runs can be calculated.');
        }

        $this->calculator->calculateRun($run);

        $issues = $this->validator->validateForApproval($run);
        $breachCount = count($issues['two_thirds_breaches']) + count($issues['negative_net_pay']);

        if ($breachCount > 0) {
            return back()->with('warning', "Payroll calculated, but {$breachCount} employee(s) have issues that must be resolved before approval.");
        }

        return back()->with('success', 'Payroll calculated successfully.');
    }

    public function approve(Request $request, PayrollRun $run)
    {
        if ($run->status !== 'calculated') {
            return back()->with('error', 'Only a calculated payroll run can be approved.');
        }

        // Maker-checker: whoever calculated the run cannot approve it.
        if ($run->calculated_by === auth()->id()) {
            return back()->with('error', 'You calculated this payroll run, so you cannot approve it. Approval must come from a different user.');
        }

        if (! $this->validator->canApprove($run)) {
            return back()->with('error', 'This run has unresolved validation issues and cannot be approved.');
        }

        $run->update([
            'status' => 'approved',
            'approved_by' => auth()->id(),
            'approved_at' => now(),
            'locked_at' => now(),
        ]);

        return back()->with('success', 'Payroll run approved and locked.');
    }

    public function destroy(PayrollRun $run)
    {
        if ($run->isLocked()) {
            return back()->with('error', 'A locked payroll run cannot be deleted.');
        }

        $run->delete();

        return redirect()->route('payroll.runs.index')->with('success', 'Payroll run deleted.');
    }

    /**
     * One employee's pay for this run, laid out like their payslip, plus what they cost the company in total.
     *
     * @return array<string, mixed>
     */
    private function breakdown(PayrollRun $run, int $payslipId, PayslipDocument $document): array
    {
        $payslip = $run->payslips()->findOrFail($payslipId);

        $this->authorize('view', $payslip);

        $doc = collect($document->build($payslip))->except(['company', 'generatedAt'])->all();
        $employerTotal = round(collect($doc['employer'])->sum('amount'), 2);

        return [
            ...$doc,
            'payslipId' => $payslip->id,
            'employerTotal' => $employerTotal,
            'costToCompany' => round($doc['gross'] + $employerTotal, 2),
        ];
    }

    private function userCanApprove(PayrollRun $run): bool
    {
        return auth()->user()->can('payroll.approve')
            && $run->status === 'calculated'
            && $run->calculated_by !== auth()->id();
    }
}
