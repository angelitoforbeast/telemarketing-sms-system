<?php
// Run from /var/www/telesms: php add_delete_permission.php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

// Create the shipments.delete permission
$perm = Permission::firstOrCreate(['name' => 'shipments.delete', 'guard_name' => 'web']);
echo "Permission created: {$perm->name}\n";

// Assign to CEO and Company Owner
$roles = ['CEO', 'Company Owner'];
foreach ($roles as $roleName) {
    $role = Role::where('name', $roleName)->first();
    if ($role) {
        if (!$role->hasPermissionTo('shipments.delete')) {
            $role->givePermissionTo('shipments.delete');
            echo "Assigned to: {$roleName}\n";
        } else {
            echo "Already assigned to: {$roleName}\n";
        }
    }
}

// Clear permission cache
app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
echo "Permission cache cleared.\nDone!\n";
