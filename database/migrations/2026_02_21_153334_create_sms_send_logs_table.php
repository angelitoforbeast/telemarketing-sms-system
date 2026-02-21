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
        Schema::create('sms_send_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('shipment_id')->constrained('shipments')->cascadeOnDelete();
            $table->foreignId('campaign_id')->constrained('sms_campaigns')->cascadeOnDelete();
            $table->string('phone_number');
            $table->string('message_body', 500)->nullable();
            $table->date('send_date');
            $table->enum('status', ['queued', 'sent', 'failed', 'skipped_duplicate'])->default('queued');
            $table->text('provider_response')->nullable();
            $table->string('provider_message_id')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->integer('retry_count')->default(0);
            $table->text('error_message')->nullable();
            $table->timestamps();

            // CORE DEDUPE CONSTRAINT: one SMS per shipment per campaign per day
            $table->unique(['shipment_id', 'campaign_id', 'send_date'], 'sms_dedupe_unique');
            $table->index('company_id');
            $table->index('send_date');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sms_send_logs');
    }
};
