<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SeedPlatformRolePermissions extends Seeder
{
    /**
     * Seed platform_role_permissions based on current role_has_permissions.
     * This sets the initial ceiling to match what roles currently have.
     * Roles that DON'T have a permission get allowed=false.
     */
    public function run(): void
    {
        $roles = DB::table('roles')
            ->where('name', '!=', 'Platform Admin')
            ->get();

        $permissions = DB::table('permissions')
            ->whereNotIn('name', [
                'platform.manage-companies',
                'platform.manage-all-users',
                'platform.view-global-stats',
            ])
            ->get();

        $existing = DB::table('role_has_permissions')->get();
        $existingMap = [];
        foreach ($existing as $row) {
            $existingMap[$row->role_id . '_' . $row->permission_id] = true;
        }

        $now = now();
        $records = [];

        foreach ($roles as $role) {
            foreach ($permissions as $permission) {
                $key = $role->id . '_' . $permission->id;
                $records[] = [
                    'role_id' => $role->id,
                    'permission_id' => $permission->id,
                    'allowed' => isset($existingMap[$key]) ? 1 : 0,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        // Insert in chunks to avoid memory issues
        foreach (array_chunk($records, 100) as $chunk) {
            DB::table('platform_role_permissions')->insert($chunk);
        }

        echo "Seeded " . count($records) . " platform role permission records.\n";
    }
}
