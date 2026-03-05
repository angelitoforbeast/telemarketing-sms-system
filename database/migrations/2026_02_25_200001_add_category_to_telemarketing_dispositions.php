<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('telemarketing_dispositions', function (Blueprint $table) {
            $table->string('category', 50)->default('other')->after('code');
        });

        // Update existing dispositions with categories based on code
        DB::table('telemarketing_dispositions')
            ->where('code', 'like', 'answered_%')
            ->update(['category' => 'answered']);

        DB::table('telemarketing_dispositions')
            ->whereIn('code', ['no_answer', 'busy', 'voicemail'])
            ->update(['category' => 'not_reached']);

        DB::table('telemarketing_dispositions')
            ->whereIn('code', ['wrong_number', 'not_in_service'])
            ->update(['category' => 'invalid']);

        DB::table('telemarketing_dispositions')
            ->where('code', 'other')
            ->update(['category' => 'other']);
    }

    public function down(): void
    {
        Schema::table('telemarketing_dispositions', function (Blueprint $table) {
            $table->dropColumn('category');
        });
    }
};
