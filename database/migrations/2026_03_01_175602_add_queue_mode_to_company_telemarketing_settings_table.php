<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('company_telemarketing_settings', function (Blueprint $table) {
            $table->string('queue_mode', 20)->default('pre_assigned')->after('auto_call_delay');
            //
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('company_telemarketing_settings', function (Blueprint $table) {
            $table->string('queue_mode', 20)->default('pre_assigned')->after('auto_call_delay');
            $table->dropColumn("queue_mode");
        });
    }
};
