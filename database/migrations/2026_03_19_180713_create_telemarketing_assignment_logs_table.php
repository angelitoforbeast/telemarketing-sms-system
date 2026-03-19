<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('telemarketing_assignment_logs', function (Blueprint $blueprint) {
            $blueprint->id();
            $blueprint->foreignId('company_id')->constrained()->onDelete('cascade');
            $blueprint->foreignId('assigned_by_user_id')->constrained('users')->onDelete('cascade');
            $blueprint->foreignId('assigned_to_user_id')->constrained('users')->onDelete('cascade');
            $blueprint->integer('shipment_count')->default(0);
            $blueprint->json('shipment_ids')->nullable(); // Optional: store IDs if needed
            $blueprint->string('status_filters')->nullable(); // Optional: filters used
            $blueprint->timestamp('assigned_at')->useCurrent();
            $blueprint->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telemarketing_assignment_logs');
    }
};
