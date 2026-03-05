<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('telemarketing_logs', function (Blueprint $table) {
            $table->longText('transcription')->nullable()->after('recording_url');
            $table->text('ai_summary')->nullable()->after('transcription');
            $table->timestamp('ai_analyzed_at')->nullable()->after('ai_summary');
        });
    }

    public function down(): void
    {
        Schema::table('telemarketing_logs', function (Blueprint $table) {
            $table->dropColumn(['transcription', 'ai_summary', 'ai_analyzed_at']);
        });
    }
};
