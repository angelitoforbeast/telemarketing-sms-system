<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Order Types (REORDER, DISTRIBUTORSHIP, etc.) - managed by CEO/Owner
        Schema::create('order_types', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->nullable();
            $table->string('name');
            $table->string('code')->nullable();
            $table->string('color', 20)->default('blue');
            $table->boolean('is_active')->default(true);
            $table->boolean('is_system')->default(false);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index('company_id');
        });

        // Orders - new order/reorder linked to customer by phone
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('shipment_id')->nullable();
            $table->unsignedBigInteger('telemarketing_log_id')->nullable();
            $table->unsignedBigInteger('order_type_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();

            // Customer info
            $table->string('customer_phone', 20);
            $table->string('customer_name')->nullable();

            // Delivery address (structured)
            $table->string('province')->nullable();
            $table->string('city')->nullable();
            $table->string('barangay')->nullable();
            $table->text('address_details')->nullable();

            // Order details
            $table->decimal('total_amount', 10, 2)->default(0);
            $table->date('process_date')->nullable();
            $table->string('status', 30)->default('pending');
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index('company_id');
            $table->index('customer_phone');
            $table->index('shipment_id');
            $table->index('status');
            $table->index('process_date');
            $table->index('created_by');
        });

        // Order Items - multiple items per order
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id');
            $table->string('item_name');
            $table->integer('quantity')->default(1);
            $table->decimal('unit_price', 10, 2)->default(0);
            $table->decimal('subtotal', 10, 2)->default(0);
            $table->timestamps();

            $table->foreign('order_id')->references('id')->on('orders')->onDelete('cascade');
            $table->index('order_id');
        });

        // J&T Address Database for cascading dropdown
        Schema::create('jnt_addresses', function (Blueprint $table) {
            $table->id();
            $table->string('province');
            $table->string('city');
            $table->string('barangay');

            $table->index('province');
            $table->index(['province', 'city']);
            $table->index(['province', 'city', 'barangay']);
        });

        // Add triggers_order flag to telemarketing_dispositions
        Schema::table('telemarketing_dispositions', function (Blueprint $table) {
            $table->boolean('triggers_order')->default(false)->after('is_recallable_on_status_change');
        });
    }

    public function down(): void
    {
        Schema::table('telemarketing_dispositions', function (Blueprint $table) {
            $table->dropColumn('triggers_order');
        });
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('order_types');
        Schema::dropIfExists('jnt_addresses');
    }
};
