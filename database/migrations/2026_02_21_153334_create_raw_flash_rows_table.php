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
        Schema::create('raw_flash_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_job_id')->constrained('import_jobs')->cascadeOnDelete();
            $table->boolean('is_processed')->default(false);
            $table->json('data');
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index('import_job_id');
            $table->index('is_processed');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('raw_flash_rows');
    }
};
