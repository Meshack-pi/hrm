<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->string('employee_number')->unique();
            $table->string('first_name');
            $table->string('middle_name')->nullable();
            $table->string('last_name');
            $table->string('national_id')->unique()->nullable();
            $table->string('passport_number')->unique()->nullable();
            $table->string('kra_pin')->unique()->nullable();
            $table->string('nssf_number')->nullable();
            $table->string('sha_number')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->enum('gender', ['male', 'female', 'other'])->nullable();
            $table->enum('marital_status', ['single', 'married', 'divorced', 'widowed'])->nullable();
            $table->string('phone')->nullable();
            $table->string('personal_email')->nullable();
            $table->string('work_email')->nullable();
            $table->text('physical_address')->nullable();
            $table->string('county')->nullable();
            $table->foreignId('farm_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('job_title_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('manager_id')->nullable();
            $table->enum('employment_type', ['permanent', 'contract', 'casual', 'intern'])->default('permanent');
            $table->date('hire_date');
            $table->date('probation_end_date')->nullable();
            $table->date('contract_end_date')->nullable();
            $table->enum('status', ['active', 'on_leave', 'suspended', 'inactive'])->default('active');
            $table->boolean('has_disability_exemption')->default(false);
            $table->date('disability_certificate_expiry')->nullable();
            $table->string('photo_path')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('manager_id')->references('id')->on('employees')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
