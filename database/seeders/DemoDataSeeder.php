<?php

namespace Database\Seeders;

use App\Models\CompanyEvent;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeBankDetail;
use App\Models\EmployeeNextOfKin;
use App\Models\EmployeePayComponent;
use App\Models\EmployeeSalary;
use App\Models\Farm;
use App\Models\JobTitle;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\PayComponent;
use App\Models\PayrollRun;
use App\Models\PayrollVariable;
use App\Models\User;
use App\Services\Leave\LeaveDayCounter;
use App\Services\Payroll\PayrollCalculator;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;

/**
 * Realistic demo data for local development: ~60 workers across the farms,
 * a login per role, leave history, and two payroll months produced by the real
 * calculator (one paid, one awaiting approval). Not run in production or tests.
 */
class DemoDataSeeder extends Seeder
{
    private const FIRST_MALE = ['Kiprono', 'Kipchoge', 'Cheruiyot', 'Kibet', 'Kiplagat', 'Otieno', 'Ochieng', 'Omondi', 'Mwangi', 'Kamau', 'Njoroge', 'Kariuki', 'Mutua', 'Musyoka', 'Wafula', 'Barasa', 'Juma', 'Hassan', 'Rotich', 'Langat', 'Korir', 'Biwott', 'Kimutai', 'Ruto'];

    private const FIRST_FEMALE = ['Chebet', 'Jepkosgei', 'Jeptoo', 'Chepkoech', 'Wanjiru', 'Wambui', 'Njeri', 'Akinyi', 'Achieng', 'Atieno', 'Mumbua', 'Nduku', 'Nafula', 'Nekesa', 'Amina', 'Fatuma', 'Cherono', 'Jerotich', 'Wairimu', 'Muthoni'];

    private const LAST = ['Kiprotich', 'Kosgei', 'Tanui', 'Koech', 'Sang', 'Kirui', 'Mutai', 'Odhiambo', 'Onyango', 'Owino', 'Maina', 'Kimani', 'Gitau', 'Ndungu', 'Kilonzo', 'Mbithi', 'Wekesa', 'Simiyu', 'Mohamed', 'Ali', 'Chepkwony', 'Too', 'Bett', 'Keter', 'Yego'];

    private const BANKS = [
        ['Equity Bank', '68'], ['KCB Bank', '01'], ['Co-operative Bank', '11'], ['Family Bank', '70'],
    ];

    private const STRUCTURE = [
        'Crop Production' => ['code' => 'CROP', 'titles' => ['Farm Hand' => [16000, 22000], 'Tractor Driver' => [24000, 30000], 'Crop Supervisor' => [38000, 52000]]],
        'Dairy' => ['code' => 'DAIRY', 'titles' => ['Milker' => [16000, 21000], 'Herdsman' => [18000, 24000], 'Dairy Supervisor' => [40000, 55000]]],
        'Poultry' => ['code' => 'POUL', 'titles' => ['Poultry Attendant' => [15500, 20000], 'Poultry Supervisor' => [36000, 48000]]],
        'Horticulture' => ['code' => 'HORT', 'titles' => ['Greenhouse Worker' => [16000, 21000], 'Agronomist' => [70000, 95000]]],
        'Farm Operations' => ['code' => 'OPS', 'titles' => ['Farm Manager' => [140000, 180000], 'Mechanic' => [32000, 42000], 'Security Guard' => [17000, 20000], 'Storekeeper' => [26000, 34000]]],
        'Finance' => ['code' => 'FIN', 'titles' => ['Accountant' => [80000, 110000], 'Payroll Officer' => [65000, 85000], 'Finance Manager' => [160000, 210000]]],
        'Human Resources' => ['code' => 'HR', 'titles' => ['HR Officer' => [60000, 80000], 'HR Manager' => [150000, 190000]]],
    ];

    private array $usedIds = [];

    private int $seq = 0;

    public function run(): void
    {
        if (Employee::exists()) {
            $this->command?->warn('Employees already exist — skipping demo data. Run migrate:fresh --seed to rebuild.');

            return;
        }

        mt_srand(2026);

        $titles = $this->seedStructure();
        $farms = Farm::orderBy('id')->get();
        $components = PayComponent::pluck('id', 'code');

        // Head office staff sit on the first farm; each farm gets its own manager and crew.
        $hq = $farms->first();
        $staff = [
            'hr_manager' => $this->hire($hq, $titles['HR Manager'], 'female', '2019-03-01', 'permanent'),
            'hr_officer' => $this->hire($hq, $titles['HR Officer'], 'male', '2021-06-14', 'permanent'),
            'finance_manager' => $this->hire($hq, $titles['Finance Manager'], 'male', '2018-09-03', 'permanent'),
            'payroll_officer' => $this->hire($hq, $titles['Payroll Officer'], 'female', '2020-02-17', 'permanent'),
            'accountant' => $this->hire($hq, $titles['Accountant'], 'female', '2022-01-10', 'permanent'),
        ];

        $farmManagers = [];
        $workers = collect();

        foreach ($farms as $farm) {
            $manager = $this->hire($farm, $titles['Farm Manager'], 'male', $this->randomDate('2017-01-01', '2021-12-31'), 'permanent');
            $farmManagers[$farm->id] = $manager;

            $crew = [
                ['Crop Supervisor', 1], ['Dairy Supervisor', 1], ['Poultry Supervisor', 1],
                ['Farm Hand', 5], ['Tractor Driver', 1], ['Milker', 2], ['Herdsman', 1],
                ['Poultry Attendant', 2], ['Greenhouse Worker', 2], ['Agronomist', 1],
                ['Mechanic', 1], ['Security Guard', 2], ['Storekeeper', 1],
            ];

            foreach ($crew as [$title, $count]) {
                for ($i = 0; $i < $count; $i++) {
                    $type = $this->pick(['permanent', 'permanent', 'permanent', 'contract', 'casual']);
                    $workers->push($this->hire($farm, $titles[$title], mt_rand(0, 1) ? 'male' : 'female',
                        $this->randomDate('2018-01-01', '2026-06-30'), $type, $manager));
                }
            }

            // Two new joiners this month — not in any payroll run yet.
            for ($i = 0; $i < 2; $i++) {
                $workers->push($this->hire($farm, $titles['Farm Hand'], mt_rand(0, 1) ? 'male' : 'female',
                    now()->subDays(mt_rand(3, 20))->toDateString(), 'casual', $manager));
            }
        }

        // A few realistic exceptions: someone suspended, someone gone, some missing statutory numbers.
        $workers->random(1)->each->update(['status' => 'suspended']);
        $workers->random(1)->each->update(['status' => 'inactive']);
        $workers->where('status', 'active')->random(3)->each->update(['kra_pin' => null]);
        $workers->where('status', 'active')->random(2)->each->update(['sha_number' => null]);

        $everyone = Employee::all();

        $this->seedAllowancesAndDeductions($everyone, $components);
        $users = $this->seedUsers($staff, $farmManagers, $workers);
        $this->seedLeave($everyone, $users['hr']);
        $this->seedPayroll($components, $users);
        $this->seedEvents($farms, $users['hr']);

        $this->command?->info(sprintf('Demo data: %d employees across %d farms, %d logins (password: "password").',
            Employee::count(), $farms->count(), count($users)));
    }

    private function seedStructure(): array
    {
        $titles = [];

        foreach (self::STRUCTURE as $deptName => $cfg) {
            $dept = Department::updateOrCreate(['code' => $cfg['code']], ['name' => $deptName]);

            foreach ($cfg['titles'] as $title => $range) {
                $titles[$title] = JobTitle::firstOrCreate(['name' => $title, 'department_id' => $dept->id]);
                $titles[$title]->salaryRange = $range;
            }
        }

        return $titles;
    }

    private function hire(Farm $farm, JobTitle $title, string $gender, string $hireDate, string $type, ?Employee $manager = null): Employee
    {
        $this->seq++;
        $first = $this->pick($gender === 'male' ? self::FIRST_MALE : self::FIRST_FEMALE);
        $last = $this->pick(self::LAST);
        $hire = Carbon::parse($hireDate);

        $employee = Employee::create([
            'employee_number' => sprintf('EMP-%04d', $this->seq),
            'first_name' => $first,
            'middle_name' => mt_rand(0, 2) ? null : $this->pick(self::LAST),
            'last_name' => $last,
            'national_id' => $this->uniqueNumber(8),
            'kra_pin' => 'A'.$this->uniqueNumber(9).chr(mt_rand(65, 90)),
            'nssf_number' => $this->uniqueNumber(10),
            'sha_number' => 'SHA'.$this->uniqueNumber(9),
            'date_of_birth' => Carbon::create(mt_rand(1970, 2003), mt_rand(1, 12), mt_rand(1, 28))->toDateString(),
            'gender' => $gender,
            'marital_status' => $this->pick(['single', 'married', 'married', 'divorced']),
            'phone' => '07'.mt_rand(10, 99).mt_rand(100000, 999999),
            'personal_email' => mt_rand(0, 2) ? strtolower("{$first}.{$last}{$this->seq}@gmail.com") : null,
            'work_email' => $title->salaryRange[0] >= 60000 ? strtolower("{$first}.{$last}@yegenfarms.co.ke") : null,
            'physical_address' => 'P.O. Box '.mt_rand(100, 9999).', '.($farm->location ?? 'Eldoret'),
            'county' => $farm->county,
            'farm_id' => $farm->id,
            'department_id' => $title->department_id,
            'job_title_id' => $title->id,
            'manager_id' => $manager?->id,
            'employment_type' => $type,
            'hire_date' => $hire->toDateString(),
            'probation_end_date' => $hire->copy()->addMonths(3)->toDateString(),
            'contract_end_date' => $type === 'contract' ? $this->contractEnd($hire) : null,
            'status' => 'active',
        ]);

        [$min, $max] = $title->salaryRange;
        $basic = round(mt_rand($min, $max) / 500) * 500;

        // Long-serving staff get a raise history so the salary tab isn't a single row.
        if ($hire->lt(Carbon::parse('2024-01-01'))) {
            $raiseDate = Carbon::parse('2024-01-01');
            EmployeeSalary::create([
                'employee_id' => $employee->id, 'basic_salary' => round($basic * 0.9 / 500) * 500,
                'effective_from' => $hire->toDateString(), 'effective_to' => $raiseDate->copy()->subDay()->toDateString(),
                'reason' => 'Starting salary',
            ]);
            EmployeeSalary::create([
                'employee_id' => $employee->id, 'basic_salary' => $basic,
                'effective_from' => $raiseDate->toDateString(), 'reason' => 'Annual review 2024',
            ]);
        } else {
            EmployeeSalary::create([
                'employee_id' => $employee->id, 'basic_salary' => $basic,
                'effective_from' => $hire->toDateString(), 'reason' => 'Starting salary',
            ]);
        }

        // Farm workers are mostly paid by M-Pesa; office staff by bank.
        $mpesa = $basic < 30000 && mt_rand(0, 3) > 0;
        $bank = $this->pick(self::BANKS);
        EmployeeBankDetail::create([
            'employee_id' => $employee->id,
            'payment_method' => $mpesa ? 'mpesa' : 'bank',
            'bank_name' => $mpesa ? null : $bank[0],
            'bank_code' => $mpesa ? null : $bank[1],
            'branch_name' => $mpesa ? null : 'Eldoret',
            'account_name' => "{$first} {$last}",
            'account_number' => $mpesa ? null : (string) mt_rand(1000000000, 1999999999),
            'mpesa_number' => $mpesa ? $employee->phone : null,
            'is_primary' => true,
        ]);

        EmployeeNextOfKin::create([
            'employee_id' => $employee->id,
            'name' => $this->pick(array_merge(self::FIRST_MALE, self::FIRST_FEMALE))." {$last}",
            'relationship' => $this->pick(['Spouse', 'Parent', 'Sibling']),
            'phone' => '07'.mt_rand(10, 99).mt_rand(100000, 999999),
            'is_emergency_contact' => true,
        ]);

        return $employee;
    }

    private function seedAllowancesAndDeductions($employees, $components): void
    {
        foreach ($employees as $e) {
            $basic = (float) $e->salaries()->whereNull('effective_to')->value('basic_salary');
            $from = $e->hire_date->toDateString();

            if ($e->employment_type !== 'casual') {
                EmployeePayComponent::create([
                    'employee_id' => $e->id, 'pay_component_id' => $components['HOUSE'],
                    'amount_or_rate' => round($basic * 0.15 / 100) * 100, 'effective_from' => $from,
                ]);
            }

            if ($basic >= 30000) {
                EmployeePayComponent::create([
                    'employee_id' => $e->id, 'pay_component_id' => $components['TRANSPORT'],
                    'amount_or_rate' => $basic >= 100000 ? 10000 : 3000, 'effective_from' => $from,
                ]);
            }

            if (mt_rand(0, 2) === 0) {
                EmployeePayComponent::create([
                    'employee_id' => $e->id, 'pay_component_id' => $components['SACCO'],
                    'amount_or_rate' => $this->pick([500, 1000, 2000, 3000]), 'effective_from' => $from,
                ]);
            }

            if ($e->employment_type === 'permanent' && $basic < 30000 && mt_rand(0, 1)) {
                EmployeePayComponent::create([
                    'employee_id' => $e->id, 'pay_component_id' => $components['UNION'],
                    'amount_or_rate' => 200, 'effective_from' => $from,
                ]);
            }

            if ($basic >= 60000 && mt_rand(0, 2) === 0) {
                EmployeePayComponent::create([
                    'employee_id' => $e->id, 'pay_component_id' => $components['HELB'],
                    'amount_or_rate' => 2500, 'effective_from' => $from,
                ]);
            }
        }
    }

    private function seedUsers(array $staff, array $farmManagers, $workers): array
    {
        $make = function (string $email, string $role, ?Employee $employee, string $fallbackName) {
            $user = User::firstOrCreate(['email' => $email], [
                'name' => $employee?->full_name ?? $fallbackName,
                'password' => bcrypt('password'),
                'employee_id' => $employee?->id,
                'is_active' => true,
                'email_verified_at' => now(),
            ]);
            $user->syncRoles([$role]);

            return $user;
        };

        $worker = $workers->where('status', 'active')->where('employment_type', 'permanent')->first();
        $manager = reset($farmManagers);

        // Everyone with a login also needs the base Employee role to apply for their own leave.
        $users = [
            'hr' => $make('hr@yegenfarms.co.ke', 'HR Manager', $staff['hr_manager'], 'HR Manager'),
            'payroll' => $make('payroll@yegenfarms.co.ke', 'Payroll Officer', $staff['payroll_officer'], 'Payroll Officer'),
            'finance' => $make('finance@yegenfarms.co.ke', 'Finance Approver', $staff['finance_manager'], 'Finance Approver'),
            'manager' => $make('manager@yegenfarms.co.ke', 'Line Manager', $manager, 'Line Manager'),
            'employee' => $make('employee@yegenfarms.co.ke', 'Employee', $worker, 'Employee'),
            'auditor' => $make('auditor@yegenfarms.co.ke', 'Auditor', null, 'External Auditor'),
        ];

        foreach (['hr', 'payroll', 'finance', 'manager'] as $key) {
            $users[$key]->assignRole('Employee');
        }

        return $users;
    }

    private function seedLeave($employees, User $approver): void
    {
        $year = (int) now()->year;
        $monthsElapsed = now()->month - 1;
        $counter = app(LeaveDayCounter::class);
        $types = LeaveType::pluck('id', 'code');
        $typeModels = LeaveType::all()->keyBy('code');

        foreach ($employees as $e) {
            $serviceMonths = $e->hire_date->diffInMonths(now());

            if ($serviceMonths >= 12) {
                LeaveBalance::create([
                    'employee_id' => $e->id, 'leave_type_id' => $types['ANNUAL'], 'year' => $year,
                    'entitled' => 21, 'accrued' => round(1.75 * $monthsElapsed, 2),
                    'carried_forward' => $e->hire_date->year < $year - 1 ? mt_rand(0, 5) : 0,
                ]);
            }

            if ($serviceMonths >= 2) {
                LeaveBalance::create([
                    'employee_id' => $e->id, 'leave_type_id' => $types['SICK_FULL'], 'year' => $year,
                    'entitled' => 7, 'accrued' => 7,
                ]);
            }
        }

        $eligible = $employees->filter(fn ($e) => $e->hire_date->diffInMonths(now()) >= 12 && $e->status === 'active');

        $request = function (Employee $e, string $code, Carbon $start, int $span, string $status, ?string $reason = null) use ($counter, $typeModels, $approver) {
            $type = $typeModels[$code];
            $end = $start->copy()->addDays($span - 1);
            $days = $counter->count($start->toDateString(), $end->toDateString(), $type);

            if ($days <= 0) {
                return;
            }

            LeaveRequest::create([
                'employee_id' => $e->id, 'leave_type_id' => $type->id,
                'start_date' => $start->toDateString(), 'end_date' => $end->toDateString(), 'days' => $days,
                'reason' => $reason ?? $this->pick(['Family matters', 'Personal time off', 'Travel upcountry', 'Rest']),
                'status' => $status,
                'approver_id' => in_array($status, ['approved', 'rejected']) ? $approver->id : null,
                'approved_at' => in_array($status, ['approved', 'rejected']) ? $start->copy()->subDays(5) : null,
                'rejection_reason' => $status === 'rejected' ? 'Peak harvest period — please reschedule.' : null,
            ]);

            $balance = LeaveBalance::where('employee_id', $e->id)->where('leave_type_id', $type->id)
                ->where('year', $start->year)->first();

            if ($balance && $type->is_paid) {
                if ($status === 'approved') {
                    $balance->increment('taken', $days);
                } elseif ($status === 'pending') {
                    $balance->increment('pending', $days);
                }
            }
        };

        foreach ($eligible->random(min(18, $eligible->count())) as $e) {
            $start = Carbon::create(now()->year, mt_rand(1, max(1, now()->month - 1)), mt_rand(1, 20));
            $request($e, 'ANNUAL', $start, mt_rand(3, 10), $this->pick(['approved', 'approved', 'approved', 'rejected']));
        }

        // Someone on leave right now, so "on leave today" isn't zero.
        foreach ($eligible->random(min(3, $eligible->count())) as $e) {
            $request($e, 'ANNUAL', now()->subDays(2), 7, 'approved');
            $e->update(['status' => 'on_leave']);
        }

        foreach ($eligible->random(min(6, $eligible->count())) as $e) {
            $request($e, 'ANNUAL', now()->addDays(mt_rand(7, 40)), mt_rand(2, 6), 'pending');
        }

        foreach ($employees->random(min(6, $employees->count())) as $e) {
            $start = Carbon::create(now()->year, mt_rand(1, max(1, now()->month - 1)), mt_rand(1, 25));
            $request($e, 'SICK_FULL', $start, mt_rand(1, 3), 'approved', 'Unwell — medical certificate attached');
        }

        // Unpaid leave inside last month, so the payroll run shows an unpaid-leave deduction.
        $last = now()->subMonthNoOverflow()->startOfMonth();
        foreach ($eligible->where('status', 'active')->random(min(2, $eligible->count())) as $e) {
            $request($e, 'UNPAID', $last->copy()->addDays(9), 3, 'approved', 'Personal matters (unpaid)');
        }
    }

    private function seedPayroll($components, array $users): void
    {
        $calculator = app(PayrollCalculator::class);
        $lastMonth = now()->subMonthNoOverflow();

        // Four closed months so payslip history and year-to-date totals have substance.
        foreach ([5, 4, 3, 2] as $monthsAgo) {
            $period = now()->subMonthsNoOverflow($monthsAgo);
            $paidOn = $period->copy()->endOfMonth()->addDays(5);

            // Calculated by the payroll officer so maker-checker applies when someone tries to approve.
            Auth::setUser($users['payroll']);
            $paid = PayrollRun::create(['period_month' => $period->month, 'period_year' => $period->year]);
            $this->addVariables($paid, $components);
            $calculator->calculateRun($paid);

            $paid->update([
                'status' => 'paid', 'approved_by' => $users['finance']->id, 'approved_at' => $paidOn->copy()->subDays(2),
                'locked_at' => $paidOn->copy()->subDays(2), 'paid_marked_by' => $users['finance']->id, 'paid_at' => $paidOn,
            ]);
            $paid->payslips()->each(fn ($p) => $p->update([
                'payment_status' => 'paid',
                'payment_method' => $p->employee_snapshot['payment_method'] ?? 'bank',
                'payment_reference' => ($p->employee_snapshot['payment_method'] ?? 'bank') === 'mpesa'
                    ? 'S'.strtoupper(substr(md5($p->id.'mp'), 0, 9))
                    : 'FT'.mt_rand(10000000, 99999999),
                'paid_at' => $paidOn,
            ]));
        }

        Auth::setUser($users['payroll']);
        $pending = PayrollRun::create([
            'period_month' => $lastMonth->month, 'period_year' => $lastMonth->year,
            'notes' => 'Includes harvest bonus for Crop Production.',
        ]);
        $this->addVariables($pending, $components, withBonus: true);
        $calculator->calculateRun($pending);

        Auth::logout();
    }

    private function addVariables(PayrollRun $run, $components, bool $withBonus = false): void
    {
        $pool = Employee::where('status', 'active')->where('hire_date', '<', now()->subMonths(3))->get();

        foreach ($pool->random(min(12, $pool->count())) as $e) {
            PayrollVariable::create([
                'payroll_run_id' => $run->id, 'employee_id' => $e->id, 'pay_component_id' => $components['OVERTIME'],
                'amount' => mt_rand(8, 40) * 100, 'description' => mt_rand(4, 20).' hours overtime',
            ]);
        }

        foreach ($pool->random(min(4, $pool->count())) as $e) {
            PayrollVariable::create([
                'payroll_run_id' => $run->id, 'employee_id' => $e->id, 'pay_component_id' => $components['ADVANCE'],
                'amount' => mt_rand(2, 6) * 1000, 'description' => 'Salary advance recovery',
            ]);
        }

        if ($withBonus) {
            $crop = Department::where('code', 'CROP')->value('id');
            foreach ($pool->where('department_id', $crop) as $e) {
                PayrollVariable::create([
                    'payroll_run_id' => $run->id, 'employee_id' => $e->id, 'pay_component_id' => $components['BONUS'],
                    'amount' => 2500, 'description' => 'Maize harvest bonus',
                ]);
            }
        }
    }

    private function seedEvents($farms, User $hr): void
    {
        $m = now()->startOfMonth();
        $event = fn (array $e) => CompanyEvent::create($e + ['created_by' => $hr->id]);

        $event(['title' => 'Monthly safety briefing', 'category' => 'meeting', 'start_date' => $m->copy()->addDays(6), 'end_date' => $m->copy()->addDays(6),
            'start_time' => '08:00', 'end_time' => '09:00', 'location' => 'Each farm yard', 'description' => 'Chemical handling and PPE refresher for all field staff.']);
        $event(['title' => 'Dairy hygiene training', 'category' => 'training', 'start_date' => $m->copy()->addDays(13), 'end_date' => $m->copy()->addDays(14),
            'start_time' => '09:00', 'end_time' => '15:00', 'location' => 'Sugoi Farm milking parlour', 'farm_id' => $farms[1]->id ?? null,
            'description' => 'County veterinary officer leads two days of practical training for milkers and herdsmen.']);
        $event(['title' => 'Maize harvest begins', 'category' => 'farm_activity', 'start_date' => $m->copy()->addDays(20), 'end_date' => $m->copy()->addDays(27),
            'farm_id' => $farms[0]->id, 'description' => 'Casual hire peaks this week; no annual leave for Crop Production.']);
        $event(['title' => 'Payroll variables cut-off', 'category' => 'deadline', 'start_date' => $m->copy()->addDays(21), 'end_date' => $m->copy()->addDays(21),
            'description' => 'Overtime, bonuses and advances for this month must reach Payroll by 5pm.']);
        $event(['title' => 'End of year staff party', 'category' => 'celebration', 'start_date' => now()->setDate(now()->year, 12, 18), 'end_date' => now()->setDate(now()->year, 12, 18),
            'start_time' => '13:00', 'end_time' => '18:00', 'location' => 'Kosachei Farm grounds']);
        $event(['title' => 'Quarterly board meeting', 'category' => 'meeting', 'start_date' => $m->copy()->addMonth()->addDays(4), 'end_date' => $m->copy()->addMonth()->addDays(4),
            'start_time' => '10:00', 'end_time' => '13:00', 'location' => 'Head office, Eldoret']);
    }

    private function contractEnd(Carbon $hire): string
    {
        // Rolling one-year contracts; some land in the next 60 days so expiry alerts have data.
        $end = $hire->copy()->addYear();
        while ($end->lt(now())) {
            $end->addYear();
        }

        return $end->toDateString();
    }

    private function uniqueNumber(int $digits): string
    {
        do {
            $n = (string) mt_rand(10 ** ($digits - 1), (10 ** $digits) - 1);
        } while (isset($this->usedIds[$n]));

        $this->usedIds[$n] = true;

        return $n;
    }

    private function randomDate(string $from, string $to): string
    {
        return date('Y-m-d', mt_rand(strtotime($from), strtotime($to)));
    }

    private function pick(array $items): mixed
    {
        return $items[mt_rand(0, count($items) - 1)];
    }
}
