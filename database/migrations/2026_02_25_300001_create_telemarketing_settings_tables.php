<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Company-level telemarketing settings (auto-call mode, delay, etc.)
        Schema::create('company_telemarketing_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->onDelete('cascade');
            $table->boolean('auto_call_enabled')->default(false);
            $table->unsignedInteger('auto_call_delay')->default(5); // seconds before auto-dial
            $table->timestamps();

            $table->unique('company_id');
        });

        // Status → Disposition mapping (which dispositions are available per shipment status)
        Schema::create('status_disposition_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained()->onDelete('cascade');
            $table->foreignId('shipment_status_id')->constrained('shipment_statuses')->onDelete('cascade');
            $table->foreignId('disposition_id')->constrained('telemarketing_dispositions')->onDelete('cascade');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            // company_id NULL = system default, company_id set = company override
            $table->unique(['company_id', 'shipment_status_id', 'disposition_id'], 'sdm_unique');
        });

        // Seed default mappings (company_id = NULL = system defaults)
        $this->seedDefaults();
    }

    private function seedDefaults(): void
    {
        // Get disposition IDs by code
        $dispositions = DB::table('telemarketing_dispositions')->pluck('id', 'code')->toArray();
        // Get status IDs by code
        $statuses = DB::table('shipment_statuses')->pluck('id', 'code')->toArray();

        // Default mapping: status_code => [disposition_codes]
        $mapping = [
            'picked_up' => ['no_answer', 'busy', 'voicemail', 'answered_callback', 'wrong_number', 'not_in_service', 'other'],
            'in_transit' => ['no_answer', 'busy', 'voicemail', 'answered_callback', 'answered_accept', 'wrong_number', 'not_in_service', 'other'],
            'delivering' => ['answered_accept', 'answered_redeliver', 'answered_refused', 'answered_callback', 'no_answer', 'busy', 'voicemail', 'wrong_number', 'other'],
            'delivered' => ['answered_accept', 'answered_reorder', 'answered_callback', 'no_answer', 'busy', 'other'],
            'failed_delivery' => ['answered_accept', 'answered_redeliver', 'answered_refused', 'answered_callback', 'no_answer', 'busy', 'voicemail', 'wrong_number', 'other'],
            'for_return' => ['answered_accept', 'answered_redeliver', 'answered_refused', 'answered_callback', 'no_answer', 'busy', 'voicemail', 'wrong_number', 'not_in_service', 'other'],
            'returned' => ['answered_reorder', 'answered_callback', 'no_answer', 'busy', 'wrong_number', 'not_in_service', 'other'],
            'closed' => ['answered_reorder', 'answered_callback', 'no_answer', 'other'],
            'unknown' => ['no_answer', 'busy', 'voicemail', 'answered_callback', 'wrong_number', 'not_in_service', 'other'],
        ];

        $rows = [];
        $now = now();

        foreach ($mapping as $statusCode => $dispCodes) {
            if (!isset($statuses[$statusCode])) continue;
            $statusId = $statuses[$statusCode];

            foreach ($dispCodes as $sortOrder => $dispCode) {
                if (!isset($dispositions[$dispCode])) continue;
                $rows[] = [
                    'company_id' => null,
                    'shipment_status_id' => $statusId,
                    'disposition_id' => $dispositions[$dispCode],
                    'sort_order' => $sortOrder,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        if (!empty($rows)) {
            DB::table('status_disposition_mappings')->insert($rows);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('status_disposition_mappings');
        Schema::dropIfExists('company_telemarketing_settings');
    }
};
