<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlatformRolePermission extends Model
{
    protected $table = 'platform_role_permissions';

    protected $fillable = ['role_id', 'permission_id', 'allowed'];

    protected $casts = [
        'allowed' => 'boolean',
    ];

    public function role()
    {
        return $this->belongsTo(\Spatie\Permission\Models\Role::class);
    }

    public function permission()
    {
        return $this->belongsTo(\Spatie\Permission\Models\Permission::class);
    }

    /**
     * Check if a specific role+permission combo is allowed at platform level.
     * If no record exists, defaults to the current role_has_permissions setting.
     */
    public static function isAllowed(int $roleId, int $permissionId): bool
    {
        $record = static::where('role_id', $roleId)
            ->where('permission_id', $permissionId)
            ->first();

        if ($record) {
            return $record->allowed;
        }

        // Default: check if role_has_permissions has it (backward compatible)
        return \DB::table('role_has_permissions')
            ->where('role_id', $roleId)
            ->where('permission_id', $permissionId)
            ->exists();
    }
}
