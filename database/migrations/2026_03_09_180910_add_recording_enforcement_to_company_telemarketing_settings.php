<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_telemarketing_settings', function (Blueprint $table) {
            // Recording enforcement: require recording before saving call log
            $table->boolean('require_recording')->default(false)->after('recording_mode');
            // Upload timeout in seconds before showing manual upload fallback
            $table->unsignedSmallInteger('recording_upload_timeout')->default(30)->after('require_recording');
            // JSON array of disposition IDs that are exempt from recording requirement
            $table->json('recording_exempt_dispositions')->nullable()->after('recording_upload_timeout');
        });
    }

    public function down(): void
    {
        Schema::table('company_telemarketing_settings', function (Blueprint $table) {
            $table->dropColumn(['require_recording', 'recording_upload_timeout', 'recording_exempt_dispositions']);
        });
    }
};
