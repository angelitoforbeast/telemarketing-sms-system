<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\PlatformRolePermission;
use App\Models\CompanyRolePermission;
use Spatie\Permission\Models\Permission;
use Symfony\Component\HttpFoundation\Response;

class CheckCompanyPermission
{
    /**
     * Handle an incoming request.
     *
     * Checks the two-level RBAC:
     * 1. Platform Admin ceiling (global) — is this permission allowed for this role?
     * 2. Company Owner setting — is this permission enabled for this role in this company?
     *
     * If either says NO, access is denied.
     * Platform Admin users bypass all checks.
     */
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();

        if (!$user) {
            abort(403, 'Unauthorized.');
        }

        // Platform Admin bypasses all permission checks
        if ($user->hasRole('Platform Admin')) {
            return $next($request);
        }

        // Company Owner bypasses company-level checks (they manage permissions)
        // But still respect platform ceiling
        if ($user->hasRole('Company Owner')) {
            // Check platform ceiling for owner role
            $role = $user->roles->first();
            $perm = Permission::where('name', $permission)->first();

            if ($role && $perm) {
                $platformAllowed = PlatformRolePermission::isAllowed($role->id, $perm->id);
                if (!$platformAllowed) {
                    abort(403, 'This feature has been restricted by the platform administrator.');
                }
            }

            return $next($request);
        }

        // For all other roles: check both levels
        $role = $user->roles->first();
        $perm = Permission::where('name', $permission)->first();

        if (!$role || !$perm) {
            abort(403, 'Unauthorized.');
        }

        // Level 1: Platform ceiling
        $platformAllowed = PlatformRolePermission::isAllowed($role->id, $perm->id);
        if (!$platformAllowed) {
            abort(403, 'This feature has been restricted by the platform administrator.');
        }

        // Level 2: Company setting
        $companyId = $user->company_id;
        if ($companyId) {
            $companySetting = CompanyRolePermission::where('company_id', $companyId)
                ->where('role_id', $role->id)
                ->where('permission_id', $perm->id)
                ->first();

            // If company has explicitly set this permission, use that setting
            if ($companySetting && !$companySetting->enabled) {
                abort(403, 'This feature has been disabled by your company administrator.');
            }
        }

        // Level 3: Original Spatie permission check (role_has_permissions table)
        if (!$user->can($permission)) {
            abort(403, 'Unauthorized.');
        }

        return $next($request);
    }
}
