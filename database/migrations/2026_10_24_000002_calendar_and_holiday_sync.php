<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('public_holidays', function (Blueprint $table) {
            // Two holidays can share a date (e.g. a gazetted day on top of a fixed one).
            $table->dropUnique(['date']);
            $table->unique(['date', 'name']);

            // system = computed by the seeder, api = synced, manual = entered by an admin.
            // Sync may replace system/api rows but never touches manual ones.
            $table->enum('source', ['system', 'api', 'manual'])->default('manual')->after('year');
            // Lets an admin switch off a wrong holiday without the next sync re-adding it.
            $table->boolean('is_active')->default(true)->after('source');
            // Public Holidays Act: a holiday falling on a Sunday is observed on the Monday.
            $table->boolean('is_substitute')->default(false)->after('is_active');
            $table->timestamp('synced_at')->nullable()->after('is_substitute');
            $table->index(['year', 'is_active']);
        });

        Schema::create('company_events', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->enum('category', ['meeting', 'training', 'farm_activity', 'deadline', 'celebration', 'other'])->default('other');
            $table->date('start_date');
            $table->date('end_date');
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->string('location')->nullable();
            // Null means the event applies to every farm.
            $table->foreignId('farm_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['start_date', 'end_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_events');

        Schema::table('public_holidays', function (Blueprint $table) {
            $table->dropIndex(['year', 'is_active']);
            $table->dropColumn(['source', 'is_active', 'is_substitute', 'synced_at']);
            $table->dropUnique(['date', 'name']);
            $table->unique(['date']);
        });
    }
};
