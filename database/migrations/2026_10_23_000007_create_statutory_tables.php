<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('statutory_rates', function (Blueprint $table) {
            $table->id();
            $table->string('code');
            $table->string('name');
            $table->decimal('employee_rate', 8, 4)->nullable();
            $table->decimal('employer_rate', 8, 4)->nullable();
            $table->decimal('fixed_amount', 12, 2)->nullable();
            $table->decimal('min_amount', 12, 2)->nullable();
            $table->decimal('max_amount', 12, 2)->nullable();
            $table->decimal('lower_limit', 12, 2)->nullable();
            $table->decimal('upper_limit', 12, 2)->nullable();
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->timestamps();
        });

        Schema::create('tax_bands', function (Blueprint $table) {
            $table->id();
            $table->decimal('lower', 12, 2);
            $table->decimal('upper', 12, 2)->nullable();
            $table->decimal('rate', 8, 4);
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tax_bands');
        Schema::dropIfExists('statutory_rates');
    }
};
