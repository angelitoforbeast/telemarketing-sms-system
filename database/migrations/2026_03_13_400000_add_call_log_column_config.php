<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_telemarketing_settings', function (Blueprint $table) {
            $table->json('call_log_columns')->nullable()->after('recording_exempt_dispositions');
        });
    }

    public function down(): void
    {
        Schema::table('company_telemarketing_settings', function (Blueprint $table) {
            $table->dropColumn('call_log_columns');
        });
    }
};
