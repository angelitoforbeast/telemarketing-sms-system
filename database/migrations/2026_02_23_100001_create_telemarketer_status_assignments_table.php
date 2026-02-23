<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('telemarketer_status_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('shipment_status_id')->constrained('shipment_statuses')->onDelete('cascade');
            $table->foreignId('company_id')->constrained()->onDelete('cascade');
            $table->timestamps();

            // Each telemarketer can only be assigned to a status once per company
            $table->unique(['user_id', 'shipment_status_id', 'company_id'], 'tm_status_user_company_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telemarketer_status_assignments');
    }
};
