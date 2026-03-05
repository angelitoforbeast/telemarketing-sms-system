<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->longText('value')->nullable();
            $table->timestamps();
        });

        // Insert default prompt
        DB::table('ai_settings')->insert([
            'key' => 'call_analysis_prompt',
            'value' => "You are analyzing a telemarketing call transcript. The conversation is primarily in Filipino/Tagalog and may include some English.\n\nPlease provide a concise summary that includes:\n1. **Purpose of the call** — Why was the customer contacted?\n2. **Customer's response** — What was the customer's reaction, concern, or feedback?\n3. **Resolution / Next steps** — What was agreed upon or what action items remain?\n4. **Overall sentiment** — Was the customer positive, negative, or neutral?\n\nKeep the summary in 3-5 sentences. Write in English.",
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_settings');
    }
};
