<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // ── Permissions ──

        $permissions = [
            // Import
            'import.upload',
            'import.view',

            // Shipments
            'shipments.view',
            'shipments.assign',
            'shipments.auto-assign',

            // Telemarketing
            'telemarketing.view-queue',
            'telemarketing.log-call',
            'telemarketing.view-all-logs',

            // SMS
            'sms.campaigns.view',
            'sms.campaigns.create',
            'sms.campaigns.edit',
            'sms.campaigns.toggle',
            'sms.logs.view',

            // Users
            'users.view',
            'users.create',
            'users.edit',
            'users.toggle',

            // Dashboard
            'dashboard.view',
            'dashboard.reports',

            // Platform Admin
            'platform.manage-companies',
            'platform.manage-all-users',
            'platform.view-global-stats',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        // ── Roles ──

        // Platform Super Admin (no company_id)
        $platformAdmin = Role::firstOrCreate(['name' => 'Platform Admin', 'guard_name' => 'web']);
        $platformAdmin->syncPermissions(Permission::all());

        // Company Owner — full access within their company
        $companyOwner = Role::firstOrCreate(['name' => 'Company Owner', 'guard_name' => 'web']);
        $companyOwner->syncPermissions([
            'import.upload', 'import.view',
            'shipments.view', 'shipments.assign', 'shipments.auto-assign',
            'telemarketing.view-queue', 'telemarketing.log-call', 'telemarketing.view-all-logs',
            'sms.campaigns.view', 'sms.campaigns.create', 'sms.campaigns.edit', 'sms.campaigns.toggle', 'sms.logs.view',
            'users.view', 'users.create', 'users.edit', 'users.toggle',
            'dashboard.view', 'dashboard.reports',
        ]);

        // Company Manager — same as owner minus user management
        $companyManager = Role::firstOrCreate(['name' => 'Company Manager', 'guard_name' => 'web']);
        $companyManager->syncPermissions([
            'import.upload', 'import.view',
            'shipments.view', 'shipments.assign', 'shipments.auto-assign',
            'telemarketing.view-queue', 'telemarketing.log-call', 'telemarketing.view-all-logs',
            'sms.campaigns.view', 'sms.campaigns.create', 'sms.campaigns.edit', 'sms.campaigns.toggle', 'sms.logs.view',
            'dashboard.view', 'dashboard.reports',
        ]);

        // Telemarketer — can only view their queue and log calls
        $telemarketer = Role::firstOrCreate(['name' => 'Telemarketer', 'guard_name' => 'web']);
        $telemarketer->syncPermissions([
            'shipments.view',
            'telemarketing.view-queue',
            'telemarketing.log-call',
            'dashboard.view',
        ]);

        // SMS Operator — can manage SMS campaigns and view logs
        $smsOperator = Role::firstOrCreate(['name' => 'SMS Operator', 'guard_name' => 'web']);
        $smsOperator->syncPermissions([
            'shipments.view',
            'sms.campaigns.view', 'sms.campaigns.create', 'sms.campaigns.edit', 'sms.campaigns.toggle', 'sms.logs.view',
            'dashboard.view',
        ]);

        // Viewer — read-only access
        $viewer = Role::firstOrCreate(['name' => 'Viewer', 'guard_name' => 'web']);
        $viewer->syncPermissions([
            'shipments.view',
            'import.view',
            'sms.campaigns.view', 'sms.logs.view',
            'telemarketing.view-all-logs',
            'dashboard.view',
        ]);
    }
}
