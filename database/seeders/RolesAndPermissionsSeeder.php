<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            // Users & roles
            'users.view', 'users.create', 'users.edit', 'users.delete',
            'roles.manage',

            // Settings
            'settings.statutory_rates', 'settings.company', 'settings.farms',

            // Employees
            'employees.view_all', 'employees.view_team', 'employees.view_own',
            'employees.create', 'employees.edit', 'employees.delete',

            // Salaries
            'salaries.view', 'salaries.edit',

            // Payroll
            'payroll.create', 'payroll.calculate', 'payroll.approve',
            'payroll.mark_paid', 'payroll.export',

            // Leave
            'leave.types_manage', 'leave.apply',
            'leave.approve_all', 'leave.approve_team',
            'leave.view_all', 'leave.view_team',

            // Profile changes
            'profile_changes.approve',

            // Reports & audit
            'reports.view', 'audit.view',

            // Calendar
            'calendar.manage', 'holidays.manage',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        $superAdmin = Role::firstOrCreate(['name' => 'Super Admin']);
        $superAdmin->givePermissionTo(Permission::all());

        $hrManager = Role::firstOrCreate(['name' => 'HR Manager']);
        $hrManager->givePermissionTo([
            'employees.view_all', 'employees.create', 'employees.edit',
            'salaries.view', 'salaries.edit',
            'leave.types_manage', 'leave.apply', 'leave.approve_all', 'leave.view_all',
            'profile_changes.approve',
            'audit.view', 'reports.view',
            'calendar.manage', 'holidays.manage',
        ]);

        $payrollOfficer = Role::firstOrCreate(['name' => 'Payroll Officer']);
        $payrollOfficer->givePermissionTo([
            'employees.view_all', 'salaries.view', 'salaries.edit',
            'payroll.create', 'payroll.calculate', 'payroll.export',
            'leave.apply',
        ]);

        $financeApprover = Role::firstOrCreate(['name' => 'Finance Approver']);
        $financeApprover->givePermissionTo([
            'employees.view_all', 'salaries.view',
            'payroll.approve', 'payroll.mark_paid', 'payroll.export',
            'leave.apply',
        ]);

        $lineManager = Role::firstOrCreate(['name' => 'Line Manager']);
        $lineManager->givePermissionTo([
            'employees.view_team',
            'leave.apply', 'leave.approve_team', 'leave.view_team',
        ]);

        $employee = Role::firstOrCreate(['name' => 'Employee']);
        $employee->givePermissionTo([
            'employees.view_own', 'leave.apply',
        ]);

        Role::firstOrCreate(['name' => 'Auditor']);
        $auditor = Role::findByName('Auditor');
        $auditor->givePermissionTo([
            'employees.view_all', 'salaries.view',
            'payroll.export', 'leave.view_all',
            'audit.view', 'reports.view',
        ]);
    }
}
