<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('telemarketing_logs', function (Blueprint $table) {
            $table->string('ai_sentiment', 20)->nullable()->after('ai_summary'); // positive, neutral, negative
            $table->unsignedTinyInteger('ai_agent_score')->nullable()->after('ai_sentiment'); // 1-10
            $table->string('ai_customer_intent', 50)->nullable()->after('ai_agent_score'); // reorder, complaint, inquiry, refusal, other
            $table->text('ai_key_issues')->nullable()->after('ai_customer_intent');
            $table->text('ai_action_items')->nullable()->after('ai_key_issues');
        });
    }

    public function down(): void
    {
        Schema::table('telemarketing_logs', function (Blueprint $table) {
            $table->dropColumn([
                'ai_sentiment',
                'ai_agent_score',
                'ai_customer_intent',
                'ai_key_issues',
                'ai_action_items',
            ]);
        });
    }
};
