<?php

namespace App\Policies;

use App\Models\Payslip;
use App\Models\User;

class PayslipPolicy
{
    public function view(User $user, Payslip $payslip): bool
    {
        if ($user->canAny(['salaries.view', 'payroll.export'])) {
            return true;
        }

        // Employees see their own payslip only after the run is approved; a calculated
        // run can still change and must not reach them yet.
        return $user->employee_id
            && $payslip->employee_id === $user->employee_id
            && in_array($payslip->payrollRun->status, Payslip::VISIBLE_RUN_STATUSES, true);
    }
}
