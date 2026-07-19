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
        $this->assertTrue($admin->hasPermissionTo('enrollments.manage'));
        $this->assertTrue($mentor->hasPermissionTo('applications.view'));
        $this->assertTrue($mentor->hasPermissionTo('attendance.record'));
        $this->assertFalse($mentor->hasPermissionTo('users.manage'));
        $this->assertFalse($mentor->hasPermissionTo('enrollments.manage'));
    }

    public function test_content_manage_granted_to_admin_only(): void
    {
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);

        $this->assertTrue(Role::findByName('admin', 'web')->hasPermissionTo('content.manage'));
        $this->assertFalse(Role::findByName('mentor', 'web')->hasPermissionTo('content.manage'));
    }

    public function test_assignment_permissions_exist_for_admin_and_mentor(): void
    {
        $this->seed(PermissionSeeder::class);

        foreach (['admin', 'mentor'] as $role) {
            $roleModel = Role::findByName($role, 'web');
            $this->assertTrue($roleModel->hasPermissionTo('assignments.manage'));
            $this->assertTrue($roleModel->hasPermissionTo('assignments.grade'));
        }
    }
}
