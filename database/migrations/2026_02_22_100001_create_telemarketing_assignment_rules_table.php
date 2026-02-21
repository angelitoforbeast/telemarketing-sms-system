<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('telemarketing_assignment_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('name');                           // e.g., "Return Shipments", "Delivered 7+ Days"
            $table->string('rule_type');                      // 'status_based', 'delivered_age', 'custom'
            $table->unsignedBigInteger('status_id')->nullable(); // For status_based rules
            $table->integer('days_threshold')->nullable();    // For delivered_age rules (e.g., 7 days)
            $table->string('assignment_method')->default('round_robin'); // 'round_robin', 'workload_based'
            $table->boolean('is_active')->default(true);
            $table->integer('priority')->default(0);          // Higher = processed first
            $table->integer('max_attempts')->default(5);      // Max call attempts before skipping
            $table->timestamps();

            $table->index('company_id');
        });

        // Add is_active column to users for telemarketing availability tracking
        if (!Schema::hasColumn('users', 'telemarketing_available')) {
            Schema::table('users', function (Blueprint $table) {
                $table->boolean('telemarketing_available')->default(true)->after('is_active');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('telemarketing_assignment_rules');

        if (Schema::hasColumn('users', 'telemarketing_available')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('telemarketing_available');
            });
        }
    }
};
