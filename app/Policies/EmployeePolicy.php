<?php

namespace App\Policies;

use App\Models\Employee;
use App\Models\User;

class EmployeePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canAny(['employees.view_all', 'employees.view_team', 'employees.view_own']);
    }

    public function view(User $user, Employee $employee): bool
    {
        if ($user->can('employees.view_all')) {
            return true;
        }

        if ($user->can('employees.view_team') && $user->employee_id) {
            return $employee->manager_id === $user->employee_id
                || $employee->id === $user->employee_id;
        }

        return $user->can('employees.view_own') && $employee->id === $user->employee_id;
    }

    public function create(User $user): bool
    {
        return $user->can('employees.create');
    }

    public function update(User $user, Employee $employee): bool
    {
        return $user->can('employees.edit');
    }

    public function delete(User $user, Employee $employee): bool
    {
        return $user->can('employees.delete');
    }

    /**
     * Bank and M-Pesa details decrypt when serialised, so viewing them is
     * gated separately from viewing the employee record itself.
     */
    public function viewBankDetails(User $user, Employee $employee): bool
    {
        if ($user->can('salaries.view')) {
            return true;
        }

        return $employee->id === $user->employee_id;
    }

    public function viewSalary(User $user, Employee $employee): bool
    {
        if ($user->can('salaries.view')) {
            return true;
        }

        return $employee->id === $user->employee_id;
    }
}
