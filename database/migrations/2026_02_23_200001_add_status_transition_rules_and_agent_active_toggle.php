<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── Status Transition Rules ──
        // Configurable rules for what happens when a shipment's status changes
        Schema::create('status_transition_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();

            // From → To status mapping (nullable = wildcard / any)
            $table->foreignId('from_status_id')->nullable()->constrained('shipment_statuses')->nullOnDelete();
            $table->foreignId('to_status_id')->nullable()->constrained('shipment_statuses')->nullOnDelete();

            // What action to take
            $table->enum('action', [
                'auto_reassign',    // Unassign + put back in pool for re-assignment
                'auto_unassign',    // Just unassign, no re-assignment (e.g., In Transit — no call needed)
                'mark_completed',   // Mark telemarketing as completed, unassign
                'no_action',        // Do nothing, keep current assignment
            ])->default('auto_reassign');

            // Whether to reset attempt count on reassign
            $table->boolean('reset_attempts')->default(true);

            // Cooldown in days before the shipment becomes callable again (0 = immediate)
            $table->unsignedInteger('cooldown_days')->default(0);

            // Whether this rule is active
            $table->boolean('is_active')->default(true);

            // Priority (higher = evaluated first)
            $table->unsignedInteger('priority')->default(10);

            // Human-readable description
            $table->string('description')->nullable();

            $table->timestamps();

            // Unique constraint: one rule per from→to combination per company
            $table->unique(['company_id', 'from_status_id', 'to_status_id'], 'str_company_from_to_unique');
        });

        // ── Agent Active Toggle for Telemarketing ──
        // Separate from is_active (account active), this controls telemarketing availability
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_telemarketing_active')->default(true)->after('is_active');
        });

        // ── Disposition: is_recallable_on_status_change ──
        // If true, shipment can be re-assigned when status changes even if this disposition was logged
        // If false (final dispositions), shipment stays completed regardless of status change
        Schema::table('telemarketing_dispositions', function (Blueprint $table) {
            $table->boolean('is_recallable_on_status_change')->default(true)->after('marks_do_not_call');
        });

        // ── Shipment: cooldown_until field ──
        // When a status transition has a cooldown, this tracks when the shipment becomes callable again
        Schema::table('shipments', function (Blueprint $table) {
            $table->timestamp('telemarketing_cooldown_until')->nullable()->after('callback_scheduled_at');
            // Track the previous status for transition detection
            $table->foreignId('previous_status_id')->nullable()->after('telemarketing_cooldown_until')
                  ->constrained('shipment_statuses')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('status_transition_rules');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_telemarketing_active');
        });

        Schema::table('telemarketing_dispositions', function (Blueprint $table) {
            $table->dropColumn('is_recallable_on_status_change');
        });

        Schema::table('shipments', function (Blueprint $table) {
            $table->dropForeign(['previous_status_id']);
            $table->dropColumn(['telemarketing_cooldown_until', 'previous_status_id']);
        });
    }
};
