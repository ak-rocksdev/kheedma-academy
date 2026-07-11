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

    public function test_rows_carry_the_person_application_count(): void
    {
        $program = Program::factory()->active()->create();
        $application = $this->makeApplication($program, '+628333333333');
        Application::create([
            'people_id' => $application->people_id, 'status' => 'rejected',
            'program_id' => $program->id, 'referral_source' => 'tiktok',
        ]);

        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->getJson('/api/admin/applications')
            ->assertOk()
            ->assertJsonPath('data.0.person.applications_count', 2);
    }

    public function test_person_detail_history_names_the_program_and_attempt(): void
    {
        $program = Program::factory()->active()->create(['name' => 'Program Detail']);
        $application = $this->makeApplication($program, '+628444444444');
        $application->update(['motivation' => 'Ingin serius belajar affiliate.']);
        Application::create([
            'people_id' => $application->people_id, 'status' => 'pending',
            'program_id' => $program->id, 'referral_source' => 'teman',
        ]);

        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->getJson("/api/admin/people/{$application->people_id}")
            ->assertOk()
            ->assertJsonPath('person.applications.0.program', 'Program Detail')
            ->assertJsonPath('person.applications.0.attempt', 2)
            ->assertJsonPath('person.applications.0.motivation', 'Ingin serius belajar affiliate.')
            ->assertJsonPath('person.applications.1.attempt', 1);
    }

    public function test_per_page_can_raise_the_page_size_beyond_the_default(): void
    {
        $program = Program::factory()->active()->create();
        foreach (range(1, 20) as $i) {
            $this->makeApplication($program, "+62890000{$i}");
        }

        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->getJson('/api/admin/applications?per_page=50')
            ->assertOk()
            ->assertJsonCount(20, 'data');

        $this->actingAs($admin)
            ->getJson('/api/admin/applications?per_page=201')
            ->assertStatus(422);
    }
}
