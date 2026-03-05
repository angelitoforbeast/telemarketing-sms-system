<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('telemarketing_logs', function (Blueprint $table) {
            // 'draft' = created when user clicks call button, before form submission
            // 'completed' = finalized via Save & Next Call form
            $table->string('status', 20)->default('completed')->after('id');
        });

        // Make disposition_id nullable so draft rows can exist without a disposition
        Schema::table('telemarketing_logs', function (Blueprint $table) {
            $table->unsignedBigInteger('disposition_id')->nullable()->change();
        });

        // Set all existing rows to 'completed' (they were already finalized)
        DB::table('telemarketing_logs')->update(['status' => 'completed']);
    }

    public function down(): void
    {
        Schema::table('telemarketing_logs', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};
