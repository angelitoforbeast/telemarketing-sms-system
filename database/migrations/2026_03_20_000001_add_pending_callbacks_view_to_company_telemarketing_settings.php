<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_telemarketing_settings', function (Blueprint $table) {
            $table->enum('pending_callbacks_view', ['callbacks_only', 'all_shipments'])
                  ->default('callbacks_only')
                  ->after('queue_mode');
        });
    }

    public function down(): void
    {
        Schema::table('company_telemarketing_settings', function (Blueprint $table) {
            $table->dropColumn('pending_callbacks_view');
        });
    }
};
