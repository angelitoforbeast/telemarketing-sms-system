<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\ImportJob;
use App\Models\Shipment;
use App\Models\SmsSendLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\PlatformRolePermission;
use App\Models\AiSetting;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class PlatformAdminController extends Controller
{
    /**
     * Platform admin dashboard.
     */
    public function dashboard()
    {
        $totalCompanies = Company::count();
        $activeCompanies = Company::active()->count();
        $totalUsers = User::count();
        $totalShipments = Shipment::count();
        $todaySms = SmsSendLog::where('send_date', today())->count();

        $companies = Company::withCount(['users', 'shipments'])
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        return view('platform.dashboard', compact(
            'totalCompanies',
            'activeCompanies',
            'totalUsers',
            'totalShipments',
            'todaySms',
            'companies'
        ));
    }

    /**
     * List all companies.
     */
    public function companies(Request $request)
    {
        $companies = Company::withCount(['users', 'shipments'])
            ->orderBy('name')
            ->paginate(20);

        return view('platform.companies.index', compact('companies'));
    }

    /**
     * Show a single company detail.
     */
    public function showCompany(Company $company)
    {
        $company->load('users.roles');
        $company->loadCount(['shipments', 'importJobs', 'smsCampaigns']);

        return view('platform.companies.show', compact('company'));
    }

    /**
     * Toggle company status.
     */
    public function toggleCompany(Company $company)
    {
        $newStatus = $company->status === 'active' ? 'suspended' : 'active';
        $company->update(['status' => $newStatus]);

        return back()->with('success', "Company {$newStatus} successfully.");
    }

    /**
     * Show the create company form.
     */
    public function createCompany()
    {
        return view('platform.companies.create');
    }

    /**
     * Update a user's password from the platform admin.
     */
    public function updateUserPassword(Request $request, Company $company, User $user)
    {
        // Ensure the user belongs to this company
        if ($user->company_id !== $company->id) {
            abort(403, 'User does not belong to this company.');
        }

        $request->validate([
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user->update([
            'password' => $request->password,
        ]);

        return back()->with('success', "Password updated successfully for {$user->name}.");
    }

    /**
     * Store a new company with its owner account.
     */
    public function storeCompany(Request $request)
    {
        $request->validate([
            'company_name' => ['required', 'string', 'max:255'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:500'],
            'owner_name' => ['required', 'string', 'max:255'],
            'owner_email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'owner_password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $company = Company::create([
            'name' => $request->company_name,
            'status' => 'active',
            'contact_email' => $request->contact_email,
            'contact_phone' => $request->contact_phone,
            'address' => $request->address,
        ]);

        $owner = User::create([
            'name' => $request->owner_name,
            'email' => $request->owner_email,
            'password' => \Illuminate\Support\Facades\Hash::make($request->owner_password),
            'company_id' => $company->id,
            'is_active' => true,
        ]);

        $owner->assignRole('Company Owner');

        return redirect()->route('platform.companies.index')
            ->with('success', "Company \"{$company->name}\" created successfully with owner account ({$owner->email}).");
    }

    /**
     * Show the global role permissions matrix.
     */
    public function permissions()
    {
        // Get all company-level roles (exclude Platform Admin)
        $roles = Role::where('name', '!=', 'Platform Admin')
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

        // Get current platform settings
        $platformSettings = PlatformRolePermission::all()
            ->groupBy(function ($item) {
                return $item->role_id . '_' . $item->permission_id;
            })
            ->map(function ($items) {
                return $items->first()->allowed;
            });

        return view('platform.permissions', compact('roles', 'grouped', 'platformSettings'));
    }

    /**
     * Update the global role permissions.
     */
    public function updatePermissions(Request $request)
    {
        $roles = Role::where('name', '!=', 'Platform Admin')->get();
        $permissions = Permission::whereNotIn('name', [
            'platform.manage-companies',
            'platform.manage-all-users',
            'platform.view-global-stats',
        ])->get();

        $allowedMap = $request->input('permissions', []);

        foreach ($roles as $role) {
            foreach ($permissions as $perm) {
                $key = $role->id . '_' . $perm->id;
                $allowed = isset($allowedMap[$key]) ? true : false;

                PlatformRolePermission::updateOrCreate(
                    ['role_id' => $role->id, 'permission_id' => $perm->id],
                    ['allowed' => $allowed]
                );

                // If platform disables a permission, also disable it in all company overrides
                if (!$allowed) {
                    \App\Models\CompanyRolePermission::where('role_id', $role->id)
                        ->where('permission_id', $perm->id)
                        ->update(['enabled' => false]);
                }
            }
        }

        // Clear Spatie permission cache
        app()->make(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        return back()->with('success', 'Global role permissions updated successfully.');
    }

    // ────────────────────────────────────────────────────────────────
    //  AI SETTINGS
    // ────────────────────────────────────────────────────────────────

    /**
     * Show the AI settings page.
     */
    public function aiSettings()
    {
        $callAnalysisPrompt = AiSetting::getValue('call_analysis_prompt', '');

        return view('platform.ai-settings', compact('callAnalysisPrompt'));
    }

    /**
     * Update AI settings.
     */
    public function updateAiSettings(Request $request)
    {
        $request->validate([
            'call_analysis_prompt' => 'required|string|min:10',
        ]);

        AiSetting::setValue('call_analysis_prompt', $request->call_analysis_prompt);

        return back()->with('success', 'AI settings updated successfully.');
    }
}
