<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add telemarketing-specific columns to shipments
        Schema::table('shipments', function (Blueprint $table) {
            $table->enum('telemarketing_status', ['pending', 'in_progress', 'completed', 'do_not_call'])
                  ->default('pending')
                  ->after('is_do_not_contact');
            $table->foreignId('last_disposition_id')
                  ->nullable()
                  ->after('telemarketing_status')
                  ->constrained('telemarketing_dispositions')
                  ->nullOnDelete();
            $table->timestamp('callback_scheduled_at')->nullable()->after('last_disposition_id');
        });

        // Add call_duration and call_started_at to telemarketing_logs
        Schema::table('telemarketing_logs', function (Blueprint $table) {
            $table->integer('call_duration_seconds')->nullable()->after('phone_called');
            $table->timestamp('call_started_at')->nullable()->after('call_duration_seconds');
        });

        // Add color and description to telemarketing_dispositions for flexibility
        Schema::table('telemarketing_dispositions', function (Blueprint $table) {
            $table->string('color', 20)->default('gray')->after('is_system');
            $table->string('description')->nullable()->after('color');
            $table->boolean('requires_callback')->default(false)->after('description');
            $table->boolean('marks_do_not_call')->default(false)->after('requires_callback');
        });
    }

    public function down(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->dropForeign(['last_disposition_id']);
            $table->dropColumn(['telemarketing_status', 'last_disposition_id', 'callback_scheduled_at']);
        });

        Schema::table('telemarketing_logs', function (Blueprint $table) {
            $table->dropColumn(['call_duration_seconds', 'call_started_at']);
        });

        Schema::table('telemarketing_dispositions', function (Blueprint $table) {
            $table->dropColumn(['color', 'description', 'requires_callback', 'marks_do_not_call']);
        });
    }
};
