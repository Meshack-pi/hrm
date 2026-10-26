<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->decimal('days_per_year', 5, 2)->nullable();
            $table->enum('accrual', ['monthly', 'upfront'])->default('monthly');
            $table->boolean('is_paid')->default(true);
            $table->decimal('pay_percentage', 5, 2)->default(100);
            $table->boolean('requires_document')->default(false);
            $table->unsignedInteger('min_service_months')->default(0);
            $table->enum('gender_restriction', ['male', 'female'])->nullable();
            $table->json('eligible_employment_types')->nullable();
            $table->decimal('max_carry_forward', 5, 2)->default(0);
            $table->unsignedInteger('carry_forward_expiry_months')->default(0);
            $table->boolean('counts_working_days_only')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('leave_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('leave_type_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->decimal('entitled', 5, 2)->default(0);
            $table->decimal('accrued', 5, 2)->default(0);
            $table->decimal('carried_forward', 5, 2)->default(0);
            $table->decimal('taken', 5, 2)->default(0);
            $table->decimal('pending', 5, 2)->default(0);
            $table->timestamps();

            $table->unique(['employee_id', 'leave_type_id', 'year']);
        });

        Schema::create('leave_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('leave_type_id')->constrained()->cascadeOnDelete();
            $table->date('start_date');
            $table->date('end_date');
            $table->decimal('days', 5, 2);
            $table->text('reason')->nullable();
            $table->string('attachment_path')->nullable();
            $table->foreignId('relief_employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->enum('status', ['pending', 'approved', 'rejected', 'cancelled'])->default('pending');
            $table->foreignId('approver_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();
        });

        Schema::create('public_holidays', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->string('name');
            $table->unsignedSmallInteger('year');
            $table->timestamps();

            $table->unique(['date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('public_holidays');
        Schema::dropIfExists('leave_requests');
        Schema::dropIfExists('leave_balances');
        Schema::dropIfExists('leave_types');
    }
};
