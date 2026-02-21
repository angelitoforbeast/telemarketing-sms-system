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
        Schema::create('shipment_status_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_id')->constrained('shipments')->cascadeOnDelete();
            $table->foreignId('status_id')->constrained('shipment_statuses')->cascadeOnDelete();
            $table->string('source_status_text')->nullable();
            $table->foreignId('import_job_id')->nullable()->constrained('import_jobs')->nullOnDelete();
            $table->timestamp('logged_at')->useCurrent();
            $table->timestamps();

            $table->index('shipment_id');
            $table->index('status_id');
            $table->index('logged_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shipment_status_logs');
    }
};
