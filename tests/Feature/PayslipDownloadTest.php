<?php

namespace Tests\Feature;

use App\Models\PayrollRun;
use App\Models\Payslip;
use App\Models\User;
use App\Services\Payroll\PayslipDocument;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class PayslipDownloadTest extends TestCase
{
    use RefreshDatabase;

    private User $employee;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->seed(DemoDataSeeder::class);
        $this->employee = User::where('email', 'employee@yegenfarms.co.ke')->firstOrFail();
    }

    private function slip(string $runStatus, ?int $employeeId = null, bool $notEmployee = false): Payslip
    {
        return Payslip::whereHas('payrollRun', fn ($q) => $q->where('status', $runStatus))
            ->when($employeeId, fn ($q) => $q->where('employee_id', $employeeId))
            ->when($notEmployee, fn ($q) => $q->where('employee_id', '!=', $this->employee->employee_id))
            ->firstOrFail();
    }

    private function as(string $email)
    {
        return $this->actingAs(User::where('email', "{$email}@yegenfarms.co.ke")->firstOrFail());
    }

    public function test_employee_downloads_own_approved_payslip_as_pdf(): void
    {
        $slip = $this->slip('paid', $this->employee->employee_id);

        $response = $this->actingAs($this->employee)->get(route('payslips.download', $slip));

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('content-type'));
        $this->assertStringStartsWith('%PDF', $response->getContent());
        $response->assertDownload(app(PayslipDocument::class)->filename($slip));

        $this->assertTrue(Activity::where('event', 'downloaded')->where('subject_id', $slip->id)
            ->where('causer_id', $this->employee->id)->exists(), 'Downloads must be audited.');
    }

    public function test_inline_view_streams_instead_of_downloading(): void
    {
        $slip = $this->slip('paid', $this->employee->employee_id);

        $response = $this->actingAs($this->employee)->get(route('payslips.download', ['payslip' => $slip, 'inline' => 1]));

        $response->assertOk();
        $this->assertStringStartsWith('inline', $response->headers->get('content-disposition'));
    }

    public function test_employee_cannot_see_payslip_before_run_is_approved(): void
    {
        $slip = $this->slip('calculated', $this->employee->employee_id);

        $this->actingAs($this->employee)->get(route('payslips.download', $slip))->assertForbidden();
    }

    public function test_employee_cannot_download_someone_elses_payslip(): void
    {
        $other = $this->slip('paid', notEmployee: true);

        $this->actingAs($this->employee)->get(route('payslips.download', $other))->assertForbidden();
    }

    public function test_line_manager_cannot_download_team_payslips(): void
    {
        $manager = User::where('email', 'manager@yegenfarms.co.ke')->first();
        $teamSlip = Payslip::whereHas('employee', fn ($q) => $q->where('manager_id', $manager->employee_id))
            ->whereHas('payrollRun', fn ($q) => $q->where('status', 'paid'))->firstOrFail();

        $this->actingAs($manager)->get(route('payslips.download', $teamSlip))->assertForbidden();
    }

    public function test_payroll_and_hr_staff_can_download_any_payslip_including_unapproved(): void
    {
        $slip = $this->slip('calculated', notEmployee: true);

        foreach (['payroll', 'hr', 'finance', 'auditor'] as $who) {
            $this->as($who)->get(route('payslips.download', $slip))->assertOk();
        }
    }

    public function test_every_payslip_document_reconciles_and_lists_each_deduction(): void
    {
        $doc = app(PayslipDocument::class);
        $checked = 0;

        foreach (Payslip::with('lines', 'payrollRun')->get() as $slip) {
            $data = $doc->build($slip);

            $earnings = collect($data['earnings'])->sum('amount');
            $this->assertEqualsWithDelta($data['gross'], $earnings - $data['unpaidLeave']['amount'], 0.01, "Earnings don't reach gross on payslip #{$slip->id}");
            $this->assertEqualsWithDelta($data['totalDeductions'], $data['statutoryTotal'] + $data['otherTotal'], 0.01, "Deduction lines don't add up on payslip #{$slip->id}");
            $this->assertEqualsWithDelta($data['net'], $data['gross'] - $data['totalDeductions'], 0.01, "Net is wrong on payslip #{$slip->id}");
            $this->assertCount(4, $data['statutory'], 'PAYE, NSSF, SHIF and Housing Levy are always listed.');
            $checked++;
        }

        $this->assertGreaterThan(300, $checked);

        // A payslip with other deductions and unpaid leave renders every line by name.
        $rich = Payslip::where('total_other_deductions', '>', 0)->where('unpaid_leave_days', '>', 0)->first()
            ?? Payslip::where('total_other_deductions', '>', 0)->firstOrFail();
        $html = view('pdf.payslip', $doc->build($rich))->render();

        foreach ($rich->lines as $line) {
            if ($line->name === PayslipDocument::UNPAID_LEAVE_LINE) {
                $this->assertStringContainsString('Less: unpaid leave', $html);
            } elseif (! $line->is_statutory) {
                $this->assertStringContainsString(e($line->name), $html);
            }
        }

        foreach (['PAYE (income tax)', 'NSSF pension', 'SHIF (health insurance)', 'Affordable Housing Levy', 'Taxable pay', 'Personal relief'] as $label) {
            $this->assertStringContainsStringIgnoringCase($label, $html);
        }

        // The payslip covers the current period only, with no running totals or payment status.
        foreach (['Year to date', 'Payment status'] as $label) {
            $this->assertStringNotContainsStringIgnoringCase($label, $html);
        }
    }

    public function test_masked_account_only_on_payslip_and_snapshot_never_serialised(): void
    {
        $slip = $this->slip('paid', $this->employee->employee_id);
        $account = $slip->employee_snapshot['account_number'] ?? $slip->employee_snapshot['mpesa_number'];

        $html = view('pdf.payslip', app(PayslipDocument::class)->build($slip))->render();
        $this->assertStringNotContainsString($account, $html);
        $this->assertStringContainsString(substr($account, -4), $html);

        $this->assertStringNotContainsString('employee_snapshot', $slip->toJson());

        $run = PayrollRun::where('status', 'paid')->first();
        $this->as('payroll')->get(route('payroll.runs.show', $run))
            ->assertInertia(fn (AssertableInertia $p) => $p->missing('payslips.data.0.employee_snapshot'))
            ->assertDontSee($account);
    }

    public function test_dashboard_lists_own_approved_payslips_with_download_ids(): void
    {
        $approved = Payslip::where('employee_id', $this->employee->employee_id)
            ->whereHas('payrollRun', fn ($q) => $q->whereIn('status', Payslip::VISIBLE_RUN_STATUSES))->count();

        $this->actingAs($this->employee)->get(route('dashboard'))
            ->assertInertia(fn (AssertableInertia $p) => $p
                ->has('me.payslips', $approved)
                ->has('me.latestPayslip.id')
                ->where('me.latestPayslip.id', fn ($id) => Payslip::find($id)->employee_id === $this->employee->employee_id)
            );
    }
}
