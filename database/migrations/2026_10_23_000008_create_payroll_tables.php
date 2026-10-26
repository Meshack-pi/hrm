<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_runs', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('period_month');
            $table->unsignedSmallInteger('period_year');
            $table->enum('status', ['draft', 'calculated', 'approved', 'paid', 'partially_paid'])->default('draft');
            $table->decimal('total_gross', 14, 2)->default(0);
            $table->decimal('total_deductions', 14, 2)->default(0);
            $table->decimal('total_net', 14, 2)->default(0);
            $table->decimal('total_employer_cost', 14, 2)->default(0);
            $table->foreignId('calculated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('calculated_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('paid_marked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('locked_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['period_month', 'period_year']);
        });

        Schema::create('payroll_variables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_run_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pay_component_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('description')->nullable();
            $table->timestamps();
        });

        Schema::create('payslips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_run_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->decimal('basic_salary', 12, 2);
            $table->decimal('gross_pay', 12, 2);
            $table->decimal('taxable_pay', 12, 2);
            $table->decimal('nssf_employee', 12, 2)->default(0);
            $table->decimal('nssf_employer', 12, 2)->default(0);
            $table->decimal('shif', 12, 2)->default(0);
            $table->decimal('ahl_employee', 12, 2)->default(0);
            $table->decimal('ahl_employer', 12, 2)->default(0);
            $table->decimal('nita_employer', 12, 2)->default(0);
            $table->decimal('paye_before_relief', 12, 2)->default(0);
            $table->decimal('personal_relief', 12, 2)->default(0);
            $table->decimal('insurance_relief', 12, 2)->default(0);
            $table->decimal('paye', 12, 2)->default(0);
            $table->decimal('total_other_deductions', 12, 2)->default(0);
            $table->decimal('total_deductions', 12, 2)->default(0);
            $table->decimal('net_pay', 12, 2);
            $table->decimal('days_worked', 5, 2)->nullable();
            $table->decimal('unpaid_leave_days', 5, 2)->default(0);
            $table->enum('payment_status', ['pending', 'paid', 'failed'])->default('pending');
            $table->string('payment_method')->nullable();
            $table->string('payment_reference')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->string('failure_reason')->nullable();
            $table->json('employee_snapshot')->nullable();
            $table->timestamps();

            $table->unique(['payroll_run_id', 'employee_id']);
        });

        Schema::create('payslip_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payslip_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pay_component_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->enum('type', ['earning', 'deduction']);
            $table->decimal('amount', 12, 2);
            $table->boolean('is_statutory')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payslip_lines');
        Schema::dropIfExists('payslips');
        Schema::dropIfExists('payroll_variables');
        Schema::dropIfExists('payroll_runs');
    }
};
