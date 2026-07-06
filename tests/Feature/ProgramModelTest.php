<?php

namespace Tests\Feature;

use App\Models\Program;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ProgramModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_open_state_follows_status_and_window(): void
    {
        $open = Program::factory()->active()->create();
        $inactive = Program::factory()->inactive()->create();
        $draft = Program::factory()->draft()->create();
        $windowClosed = Program::factory()->windowClosed()->create();

        $this->assertTrue($open->isOpen());
        $this->assertFalse($inactive->isOpen());
        $this->assertFalse($draft->isOpen());
        $this->assertFalse($windowClosed->isOpen());

        $this->assertSame([$open->id], Program::openForRegistration()->pluck('id')->all());
    }

    public function test_admin_role_gets_programs_manage_permission(): void
    {
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);

        $this->assertTrue(Role::findByName('admin', 'web')->hasPermissionTo('programs.manage'));
        $this->assertFalse(Role::findByName('mentor', 'web')->hasPermissionTo('programs.manage'));
    }
}
