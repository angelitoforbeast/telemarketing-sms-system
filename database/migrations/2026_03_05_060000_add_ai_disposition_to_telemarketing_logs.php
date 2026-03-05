<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('telemarketing_logs', function (Blueprint $table) {
            $table->foreignId('ai_disposition_id')
                  ->nullable()
                  ->after('ai_analyzed_at')
                  ->constrained('telemarketing_dispositions')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('telemarketing_logs', function (Blueprint $table) {
            $table->dropForeign(['ai_disposition_id']);
            $table->dropColumn('ai_disposition_id');
        });
    }
};
