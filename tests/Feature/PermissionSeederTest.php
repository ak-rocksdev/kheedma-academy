<?php

namespace Tests\Feature;

use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PermissionSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_maps_permissions_to_roles(): void
    {
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);

        $admin = Role::findByName('admin', 'web');
        $mentor = Role::findByName('mentor', 'web');

        $this->assertTrue($admin->hasPermissionTo('users.manage'));
        $this->assertTrue($admin->hasPermissionTo('cohorts.manage'));
        $this->assertTrue($mentor->hasPermissionTo('applications.view'));
        $this->assertFalse($mentor->hasPermissionTo('users.manage'));
    }
}
