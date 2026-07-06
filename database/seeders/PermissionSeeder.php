<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class PermissionSeeder extends Seeder
{
    /**
     * Define granular permissions and attach them to roles. Idempotent.
     * Runs after RoleSeeder, since the roles must exist first.
     */
    public function run(): void
    {
        $permissions = [
            'applications.view',
            'applications.review',
            'people.view',
            'people.merge',
            'cohorts.view',
            'cohorts.manage',
            'users.manage',
            'data.export',
        ];

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        Role::findOrCreate('admin', 'web')->syncPermissions($permissions);
        Role::findOrCreate('mentor', 'web')->syncPermissions([
            'applications.view',
            'people.view',
            'cohorts.view',
        ]);
        Role::findOrCreate('participant', 'web')->syncPermissions([]);

        // Spatie caches the permission map; without this flush newly seeded
        // permissions can be missed by cached lookups.
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
