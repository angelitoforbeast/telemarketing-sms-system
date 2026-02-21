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
        Schema::create('shipments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->enum('courier', ['jnt', 'flash']);
            $table->string('waybill_no');
            $table->foreignId('normalized_status_id')->nullable()->constrained('shipment_statuses')->nullOnDelete();
            $table->timestamp('last_status_update_at')->nullable();
            $table->string('consignee_name');
            $table->string('consignee_phone_1')->nullable();
            $table->string('consignee_phone_2')->nullable();
            $table->text('consignee_address')->nullable();
            $table->string('consignee_province')->nullable();
            $table->string('consignee_city')->nullable();
            $table->string('consignee_barangay')->nullable();
            $table->string('sender_name')->nullable();
            $table->string('sender_phone')->nullable();
            $table->decimal('cod_amount', 12, 2)->default(0);
            $table->text('item_description')->nullable();
            $table->integer('item_quantity')->nullable();
            $table->decimal('item_weight', 8, 2)->nullable();
            $table->decimal('shipping_cost', 12, 2)->default(0);
            $table->string('payment_method')->nullable();
            $table->string('rts_reason')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamp('submission_time')->nullable();
            $table->timestamp('signing_time')->nullable();
            $table->foreignId('assigned_to_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('assigned_at')->nullable();
            $table->integer('telemarketing_attempt_count')->default(0);
            $table->timestamp('last_contacted_at')->nullable();
            $table->boolean('is_do_not_contact')->default(false);
            $table->string('source_status_text')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'courier', 'waybill_no']);
            $table->index('company_id');
            $table->index('courier');
            $table->index('normalized_status_id');
            $table->index('consignee_phone_1');
            $table->index('consignee_province');
            $table->index('consignee_city');
            $table->index('assigned_to_user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shipments');
    }
};
