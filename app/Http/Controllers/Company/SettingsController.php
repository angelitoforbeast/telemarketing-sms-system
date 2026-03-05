<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Models\CompanyTelemarketingSetting;
use App\Models\ShipmentStatus;
use App\Models\StatusDispositionMapping;
use App\Models\TelemarketingDisposition;
use Illuminate\Http\Request;
use App\Models\PlatformRolePermission;
use App\Models\CompanyRolePermission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class SettingsController extends Controller
{
    public function edit(Request $request)
    {
        $company = $request->user()->company;
        return view('settings.edit', compact('company'));
    }

    public function update(Request $request)
    {
        $request->validate([
            'cod_fee_rate' => 'required|numeric|min:0|max:1',
            'cod_vat_rate' => 'required|numeric|min:0|max:1',
        ]);

        $company = $request->user()->company;
        $company->update([
            'cod_fee_rate' => $request->cod_fee_rate,
            'cod_vat_rate' => $request->cod_vat_rate,
        ]);

        return redirect()->route('settings.edit')->with('success', 'Settings updated successfully.');
    }

    // ────────────────────────────────────────────────────────────────
    //  TELEMARKETING SETTINGS
    // ────────────────────────────────────────────────────────────────

    public function telemarketingSettings(Request $request)
    {
        $companyId = $request->user()->company_id;
        $settings = CompanyTelemarketingSetting::getOrCreate($companyId);
        $statuses = ShipmentStatus::orderBy('sort_order')->get();
        $dispositions = TelemarketingDisposition::forCompany($companyId)->orderBy('category')->orderBy('sort_order')->get();

        // Get current mapping (company-specific, or defaults if none)
        $companyMapping = StatusDispositionMapping::getMappingForCompany($companyId);
        $defaultMapping = StatusDispositionMapping::getMappingForCompany(null);

        // If company has no custom mapping, use defaults as current
        $currentMapping = !empty($companyMapping) ? $companyMapping : $defaultMapping;

        return view('settings.telemarketing', compact('settings', 'statuses', 'dispositions', 'currentMapping', 'defaultMapping'));
    }

    public function updateTelemarketingSettings(Request $request)
    {
        $request->validate([
            'auto_call_enabled' => 'required|boolean',
            'auto_call_delay' => 'required|integer|in:3,5,7,10,15',
            'queue_mode' => 'required|in:pre_assigned,shared_queue,hybrid',
        ]);

        $companyId = $request->user()->company_id;
        $settings = CompanyTelemarketingSetting::getOrCreate($companyId);
        $settings->update([
            'auto_call_enabled' => $request->auto_call_enabled,
            'auto_call_delay' => $request->auto_call_delay,
            'queue_mode' => $request->queue_mode,
        ]);

        return redirect()->route('settings.telemarketing')->with('success', 'Auto-call settings updated successfully.');
    }

    public function updateDispositionMapping(Request $request)
    {
        $request->validate([
            'mapping' => 'nullable|array',
            'mapping.*' => 'nullable|array',
            'mapping.*.*' => 'integer|exists:telemarketing_dispositions,id',
        ]);

        $companyId = $request->user()->company_id;
        $mapping = $request->input('mapping', []);

        StatusDispositionMapping::saveMappingForCompany($companyId, $mapping);

        return redirect()->route('settings.telemarketing')->with('success', 'Disposition mapping updated successfully.');
    }

    // ────────────────────────────────────────────────────────────────
    //  ROLE PERMISSIONS
    // ────────────────────────────────────────────────────────────────

    public function rolePermissions(Request $request)
    {
        $company = $request->user()->company;
        $companyId = $company->id;

        // Get company-level roles (exclude Platform Admin and Company Owner)
        $roles = Role::whereNotIn('name', ['Platform Admin', 'Company Owner'])
            ->orderBy('id')
            ->get();

        // Get all non-platform permissions
        $permissions = Permission::whereNotIn('name', [
            'platform.manage-companies',
            'platform.manage-all-users',
            'platform.view-global-stats',
        ])->orderBy('id')->get();

        // Group permissions by module
        $grouped = [];
        foreach ($permissions as $perm) {
            $parts = explode('.', $perm->name);
            $module = ucfirst(str_replace('-', ' ', $parts[0]));
            if (!isset($grouped[$module])) {
                $grouped[$module] = [];
            }
            $grouped[$module][] = $perm;
        }

        // Get platform ceiling (what's allowed globally)
        $platformSettings = PlatformRolePermission::all()
            ->groupBy(function ($item) {
                return $item->role_id . '_' . $item->permission_id;
            })
            ->map(function ($items) {
                return $items->first()->allowed;
            });

        // Get company-level settings
        $companySettings = CompanyRolePermission::where('company_id', $companyId)
            ->get()
            ->groupBy(function ($item) {
                return $item->role_id . '_' . $item->permission_id;
            })
            ->map(function ($items) {
                return $items->first()->enabled;
            });

        return view('settings.role-permissions', compact(
            'company', 'roles', 'grouped', 'platformSettings', 'companySettings'
        ));
    }

    public function updateRolePermissions(Request $request)
    {
        $companyId = $request->user()->company_id;

        $roles = Role::whereNotIn('name', ['Platform Admin', 'Company Owner'])->get();
        $permissions = Permission::whereNotIn('name', [
            'platform.manage-companies',
            'platform.manage-all-users',
            'platform.view-global-stats',
        ])->get();

        $enabledMap = $request->input('permissions', []);

        foreach ($roles as $role) {
            foreach ($permissions as $perm) {
                $key = $role->id . '_' . $perm->id;

                // Check platform ceiling first
                $platformAllowed = PlatformRolePermission::isAllowed($role->id, $perm->id);

                // If platform doesn't allow, force disabled
                $enabled = $platformAllowed && isset($enabledMap[$key]);

                CompanyRolePermission::updateOrCreate(
                    [
                        'company_id' => $companyId,
                        'role_id' => $role->id,
                        'permission_id' => $perm->id,
                    ],
                    ['enabled' => $enabled]
                );
            }
        }

        // Clear Spatie permission cache
        app()->make(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        return back()->with('success', 'Role permissions updated successfully.');
    }
}
