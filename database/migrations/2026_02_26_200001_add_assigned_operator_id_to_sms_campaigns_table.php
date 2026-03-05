<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sms_campaigns', function (Blueprint $table) {
            $table->unsignedBigInteger('assigned_operator_id')->nullable()->after('sending_method');
            $table->foreign('assigned_operator_id')->references('id')->on('users')->nullOnDelete();
        });
    }
    public function down(): void
    {
        Schema::table('sms_campaigns', function (Blueprint $table) {
            $table->dropForeign(['assigned_operator_id']);
            $table->dropColumn('assigned_operator_id');
        });
    }
};
