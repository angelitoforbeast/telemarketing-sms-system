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
        Schema::create('telemarketing_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId("user_id")->constrained("users");
            $table->foreignId("assigned_to_user_id")->constrained("users");
            $table->string("status_filter")->nullable();
            $table->string("date_range_filter")->nullable();
            $table->integer("limit_filter")->nullable();
            $table->integer("total_shipments");
            $table->integer("assigned_shipments");
            $table->date("disposition_date")->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('telemarketing_assignments');
    }
};
