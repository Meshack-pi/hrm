<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The snapshot holds the bank account / M-Pesa number paid at the time, which the
 * bank-details table encrypts. Storing it here as plain JSON undid that, so the
 * column becomes text holding an encrypted:array value.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payslips', function (Blueprint $table) {
            $table->longText('employee_snapshot')->nullable()->change();
        });

        DB::table('payslips')->whereNotNull('employee_snapshot')->orderBy('id')
            ->each(fn ($row) => DB::table('payslips')->where('id', $row->id)
                ->update(['employee_snapshot' => Crypt::encryptString($row->employee_snapshot)]));
    }

    public function down(): void
    {
        DB::table('payslips')->whereNotNull('employee_snapshot')->orderBy('id')
            ->each(fn ($row) => DB::table('payslips')->where('id', $row->id)
                ->update(['employee_snapshot' => Crypt::decryptString($row->employee_snapshot)]));

        Schema::table('payslips', function (Blueprint $table) {
            $table->json('employee_snapshot')->nullable()->change();
        });
    }
};
