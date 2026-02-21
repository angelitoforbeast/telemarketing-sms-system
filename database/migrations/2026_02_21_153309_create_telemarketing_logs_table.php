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
        Schema::create('telemarketing_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_id')->constrained('shipments')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('disposition_id')->constrained('telemarketing_dispositions')->cascadeOnDelete();
            $table->text('notes')->nullable();
            $table->integer('attempt_no')->default(1);
            $table->timestamp('callback_at')->nullable();
            $table->string('phone_called')->nullable();
            $table->timestamps();

            $table->index('shipment_id');
            $table->index('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('telemarketing_logs');
    }
};
