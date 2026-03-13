<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Add recording_mode column to company_telemarketing_settings
        Schema::table('company_telemarketing_settings', function (Blueprint $table) {
            $table->enum('recording_mode', ['auto', 'manual', 'both'])->default('both')->after('queue_mode');
        });

        // Add new permission for managing recording mode
        $now = now();
        DB::table('permissions')->insert([
            'name' => 'telemarketing.manage-recording-mode',
            'guard_name' => 'web',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // Enable this permission for CEO and Company Owner roles at platform level
        $permId = DB::table('permissions')->where('name', 'telemarketing.manage-recording-mode')->value('id');
        if ($permId) {
            $roles = DB::table('roles')->whereIn('name', ['CEO', 'Company Owner'])->pluck('id');
            foreach ($roles as $roleId) {
                // Add to platform_role_permissions (enabled by default for CEO and Owner)
                DB::table('platform_role_permissions')->insertOrIgnore([
                    'role_id' => $roleId,
                    'permission_id' => $permId,
                    'is_enabled' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('company_telemarketing_settings', function (Blueprint $table) {
            $table->dropColumn('recording_mode');
        });

        $permId = DB::table('permissions')->where('name', 'telemarketing.manage-recording-mode')->value('id');
        if ($permId) {
            DB::table('platform_role_permissions')->where('permission_id', $permId)->delete();
            DB::table('company_role_permissions')->where('permission_id', $permId)->delete();
        }
        DB::table('permissions')->where('name', 'telemarketing.manage-recording-mode')->delete();
    }
};
