<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CompanyRolePermission extends Model
{
    protected $table = 'company_role_permissions';

    protected $fillable = ['company_id', 'role_id', 'permission_id', 'enabled'];

    protected $casts = [
        'enabled' => 'boolean',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function role()
    {
        return $this->belongsTo(\Spatie\Permission\Models\Role::class);
    }

    public function permission()
    {
        return $this->belongsTo(\Spatie\Permission\Models\Permission::class);
    }

    /**
     * Check if a specific role+permission combo is enabled for a company.
     * If no company-level record exists, falls back to platform level.
     */
    public static function isEnabled(int $companyId, int $roleId, int $permissionId): bool
    {
        // First check platform ceiling
        if (!PlatformRolePermission::isAllowed($roleId, $permissionId)) {
            return false;
        }

        // Then check company-level override
        $record = static::where('company_id', $companyId)
            ->where('role_id', $roleId)
            ->where('permission_id', $permissionId)
            ->first();

        if ($record) {
            return $record->enabled;
        }

        // Default: if platform allows and no company override, use platform setting
        return true;
    }

    /**
     * Get the effective permissions for a user based on their role and company.
     * Returns an array of permission names that are actually enabled.
     */
    public static function getEffectivePermissions(int $companyId, int $roleId): array
    {
        $role = \Spatie\Permission\Models\Role::find($roleId);
        if (!$role) return [];

        $allPermissions = \Spatie\Permission\Models\Permission::all();
        $effective = [];

        foreach ($allPermissions as $permission) {
            if (static::isEnabled($companyId, $roleId, $permission->id)) {
                $effective[] = $permission->name;
            }
        }

        return $effective;
    }
}
