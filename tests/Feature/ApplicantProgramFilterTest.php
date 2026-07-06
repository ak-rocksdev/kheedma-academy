<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\Person;
use App\Models\Program;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApplicantProgramFilterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
    }

    private function makeApplication(Program $program, string $phone): Application
    {
        $person = Person::create([
            'name' => 'Uji '.$phone, 'phone' => $phone, 'email' => $phone.'@example.test',
        ]);

        return Application::create([
            'people_id' => $person->id, 'status' => 'pending',
            'program_id' => $program->id, 'referral_source' => 'instagram',
        ]);
    }

    public function test_rows_include_program_and_filter_works(): void
    {
        $a = Program::factory()->active()->create(['name' => 'Program A']);
        $b = Program::factory()->active()->create(['name' => 'Program B']);
        $this->makeApplication($a, '+628111111111');
        $this->makeApplication($b, '+628222222222');

        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->getJson('/api/admin/applications')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->actingAs($admin)
            ->getJson("/api/admin/applications?program={$a->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.program', 'Program A')
            ->assertJsonPath('data.0.referral_source', 'instagram');
    }
}
