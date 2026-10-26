<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Employees are addressed by UUID in URLs so sequential ids aren't exposed or guessable.
     */
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->uuid('uuid')->nullable()->after('id');
        });

        DB::table('employees')->whereNull('uuid')->orderBy('id')->lazyById()->each(function ($employee) {
            DB::table('employees')->where('id', $employee->id)->update(['uuid' => (string) Str::uuid7()]);
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->uuid('uuid')->nullable(false)->unique()->change();
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropUnique(['uuid']);
            $table->dropColumn('uuid');
        });
    }
};
