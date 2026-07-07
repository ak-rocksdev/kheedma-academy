<?php

namespace Tests\Feature;

use App\Models\Cohort;
use App\Models\Program;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ProgramModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_program_open_state_derives_from_cohort_windows(): void
    {
        $open = Program::factory()->active()->create();
        Cohort::factory()->openWindow()->create(['program_id' => $open->id]);

        $activeNoCohort = Program::factory()->active()->create();

        $activeClosedWindow = Program::factory()->active()->create();
        Cohort::factory()->closedWindow()->create(['program_id' => $activeClosedWindow->id]);

        $activeWindowlessCohort = Program::factory()->active()->create();
        Cohort::factory()->create(['program_id' => $activeWindowlessCohort->id]);

        $inactive = Program::factory()->inactive()->create();
        Cohort::factory()->openWindow()->create(['program_id' => $inactive->id]);

        $this->assertTrue($open->isOpen());
        $this->assertFalse($activeNoCohort->isOpen());
        $this->assertFalse($activeClosedWindow->isOpen());
        $this->assertFalse($activeWindowlessCohort->isOpen());
        $this->assertFalse($inactive->isOpen());

        $this->assertSame([$open->id], Program::openForRegistration()->pluck('id')->all());
    }

    public function test_open_cohort_returns_the_open_window_batch(): void
    {
        $program = Program::factory()->active()->create();
        Cohort::factory()->closedWindow()->create(['program_id' => $program->id]);
        $openBatch = Cohort::factory()->openWindow()->create(['program_id' => $program->id]);

        $this->assertTrue($program->openCohort()->is($openBatch));
        $this->assertNull(Program::factory()->active()->create()->openCohort());
    }

    public function test_cohort_window_open_logic(): void
    {
        $this->assertTrue(Cohort::factory()->openWindow()->create()->isOpenForRegistration());
        $this->assertFalse(Cohort::factory()->closedWindow()->create()->isOpenForRegistration());
        $this->assertFalse(Cohort::factory()->create()->isOpenForRegistration()); // both nulls = not open

        $openEnded = Cohort::factory()->create(['registration_opens_at' => now()->subDay()]);
        $this->assertTrue($openEnded->isOpenForRegistration()); // opens set, no close = open

        $future = Cohort::factory()->create(['registration_opens_at' => now()->addWeek()]);
        $this->assertFalse($future->isOpenForRegistration());
    }

    public function test_admin_role_gets_programs_manage_permission(): void
    {
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);

        $this->assertTrue(Role::findByName('admin', 'web')->hasPermissionTo('programs.manage'));
        $this->assertFalse(Role::findByName('mentor', 'web')->hasPermissionTo('programs.manage'));
    }
}
