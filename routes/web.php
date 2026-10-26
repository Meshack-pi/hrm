<?php

use App\Http\Controllers\Admin\DepartmentController;
use App\Http\Controllers\Admin\FarmController;
use App\Http\Controllers\Admin\PublicHolidayController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\CalendarController;
use App\Http\Controllers\CompanyEventController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\LeaveRequestController;
use App\Http\Controllers\PayrollRunController;
use App\Http\Controllers\PayslipController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReportController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('login');
});

Route::get('/dashboard', DashboardController::class)->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    // Profile
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // User Management
    Route::middleware('permission:users.view')->group(function () {
        Route::get('users', [UserController::class, 'index'])->name('users.index');
        Route::get('users/create', [UserController::class, 'create'])
            ->middleware('permission:users.create')->name('users.create');
        Route::post('users', [UserController::class, 'store'])
            ->middleware('permission:users.create')->name('users.store');
        Route::get('users/{user}/edit', [UserController::class, 'edit'])
            ->middleware('permission:users.edit')->name('users.edit');
        Route::match(['put', 'patch'], 'users/{user}', [UserController::class, 'update'])
            ->middleware('permission:users.edit')->name('users.update');
        Route::delete('users/{user}', [UserController::class, 'destroy'])
            ->middleware('permission:users.delete')->name('users.destroy');
    });

    // Employees
    Route::resource('employees', EmployeeController::class)->except('edit');

    // Payroll
    // Runs expose every employee's pay, so viewing them needs a payroll permission, not just a login.
    Route::prefix('payroll')->name('payroll.')
        ->middleware('permission:payroll.create|payroll.calculate|payroll.approve|payroll.mark_paid|payroll.export')
        ->group(function () {
            Route::get('runs', [PayrollRunController::class, 'index'])->name('runs.index');
            Route::get('runs/create', [PayrollRunController::class, 'create'])
                ->middleware('permission:payroll.create')->name('runs.create');
            Route::post('runs', [PayrollRunController::class, 'store'])
                ->middleware('permission:payroll.create')->name('runs.store');
            Route::get('runs/{run}', [PayrollRunController::class, 'show'])->name('runs.show');
            Route::delete('runs/{run}', [PayrollRunController::class, 'destroy'])
                ->middleware('permission:payroll.create')->name('runs.destroy');
            Route::post('runs/{run}/calculate', [PayrollRunController::class, 'calculate'])
                ->middleware('permission:payroll.calculate')->name('runs.calculate');
            Route::post('runs/{run}/approve', [PayrollRunController::class, 'approve'])
                ->middleware('permission:payroll.approve')->name('runs.approve');
        });

    // Calendar — everyone can view; what they see is scoped in the controller.
    Route::get('calendar', [CalendarController::class, 'index'])->name('calendar.index');
    Route::middleware('permission:calendar.manage')->prefix('calendar/events')->name('calendar.events.')->group(function () {
        Route::post('/', [CompanyEventController::class, 'store'])->name('store');
        Route::put('{event}', [CompanyEventController::class, 'update'])->name('update');
        Route::delete('{event}', [CompanyEventController::class, 'destroy'])->name('destroy');
    });

    // Payslips (authorised per payslip by PayslipPolicy)
    Route::get('payslips/{payslip}/download', [PayslipController::class, 'download'])->name('payslips.download');

    // Leave
    Route::prefix('leave')->name('leave.')->group(function () {
        Route::get('requests', [LeaveRequestController::class, 'index'])->name('requests.index');
        Route::post('requests', [LeaveRequestController::class, 'store'])->name('requests.store');
        Route::post('requests/{leaveRequest}/approve', [LeaveRequestController::class, 'approve'])->name('requests.approve');
        Route::post('requests/{leaveRequest}/reject', [LeaveRequestController::class, 'reject'])->name('requests.reject');
        Route::post('requests/{leaveRequest}/cancel', [LeaveRequestController::class, 'cancel'])->name('requests.cancel');
    });

    // Reports
    Route::prefix('reports')->name('reports.')->group(function () {
        Route::get('/', [ReportController::class, 'index'])->name('index');

        foreach (ReportController::SECTIONS as $key => $section) {
            Route::get($key, [ReportController::class, $key])
                ->middleware('permission:'.implode('|', $section['permissions']))->name($key);
        }

        Route::get('export/employees', [ReportController::class, 'exportEmployees'])
            ->middleware('permission:reports.view')->name('export.employees');
        Route::get('export/payroll-register/{run}', [ReportController::class, 'exportPayrollRegister'])
            ->middleware('permission:payroll.export')->name('export.payroll-register');
    });

    // Settings
    Route::prefix('settings')->name('settings.')->group(function () {
        Route::resource('farms', FarmController::class)->except(['show', 'create', 'edit'])
            ->middleware('permission:settings.farms');
        Route::resource('departments', DepartmentController::class)->except(['show', 'create', 'edit'])
            ->middleware('permission:settings.company|employees.create');

        Route::middleware('permission:holidays.manage')->prefix('holidays')->name('holidays.')->group(function () {
            Route::get('/', [PublicHolidayController::class, 'index'])->name('index');
            Route::post('/', [PublicHolidayController::class, 'store'])->name('store');
            Route::post('sync', [PublicHolidayController::class, 'sync'])->middleware('throttle:6,1')->name('sync');
            Route::patch('{holiday}', [PublicHolidayController::class, 'update'])->name('update');
            Route::delete('{holiday}', [PublicHolidayController::class, 'destroy'])->name('destroy');
        });
    });
});

require __DIR__.'/auth.php';
