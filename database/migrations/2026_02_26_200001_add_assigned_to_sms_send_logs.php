<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sms_send_logs', function (Blueprint $table) {
            $table->unsignedBigInteger('assigned_to')->nullable()->after('device_id');
            $table->foreign('assigned_to')->references('id')->on('users')->onDelete('set null');
        });

        // Migrate any existing device_id assignments to assigned_to via the device's user_id
        \DB::statement("
            UPDATE sms_send_logs sl
            JOIN sms_devices sd ON sl.device_id = sd.id
            SET sl.assigned_to = sd.user_id
            WHERE sd.user_id IS NOT NULL
        ");
    }

    public function down(): void
    {
        Schema::table('sms_send_logs', function (Blueprint $table) {
            $table->dropForeign(['assigned_to']);
            $table->dropColumn('assigned_to');
        });
    }
};
