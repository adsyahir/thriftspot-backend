<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolePermissionSeeder extends Seeder
{
    public function run()
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Create Permissions
        $permissions = [
            // Post permissions
            'view-posts',
            'create-posts',
            'edit-posts',
            'delete-posts',
            'publish-posts',

            // User permissions
            'view-users',
            'create-users',
            'edit-users',
            'delete-users',

            // Settings
            'manage-settings',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'api']);
        }

        // Create Roles and Assign Permissions

        // Admin - has all permissions
        $admin = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'api']);
        $admin->syncPermissions(Permission::all());

        // User - basic permissions only
        $user = Role::firstOrCreate(['name' => 'user', 'guard_name' => 'api']);
        $user->syncPermissions([
            'view-posts',
            'create-posts',
        ]);
    }
}
