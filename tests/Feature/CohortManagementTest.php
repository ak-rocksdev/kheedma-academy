<?php

namespace Tests\Feature;

use App\Models\Cohort;
use App\Models\Program;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CohortManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
    }

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    public function test_admin_can_create_a_cohort_with_a_mentor(): void
    {
        $mentor = User::factory()->mentor()->create();

        $this->actingAs($this->admin())
            ->postJson('/api/admin/cohorts', [
                'name' => 'Angkatan 1',
                'program_id' => Program::factory()->create()->id,
                'start_date' => now()->addWeek()->toDateString(),
                'end_date' => now()->addMonths(2)->toDateString(),
                'mentor_id' => $mentor->id,
            ])
            ->assertCreated()
            ->assertJsonPath('cohort.name', 'Angkatan 1')
            ->assertJsonPath('cohort.status', 'upcoming')
            ->assertJsonPath('cohort.mentor.id', $mentor->id);
    }

    public function test_admin_can_partially_update_a_cohort(): void
    {
        $mentor = User::factory()->mentor()->create();
        $cohort = Cohort::factory()->create(['mentor_id' => null]);

        $this->actingAs($this->admin())
            ->patchJson("/api/admin/cohorts/{$cohort->id}", ['mentor_id' => $mentor->id])
            ->assertOk()
            ->assertJsonPath('cohort.mentor.id', $mentor->id)
            ->assertJsonPath('cohort.name', $cohort->name);
    }

    public function test_mentor_id_must_reference_a_mentor(): void
    {
        $notMentor = User::factory()->admin()->create();

        $this->actingAs($this->admin())
            ->postJson('/api/admin/cohorts', ['name' => 'X', 'program_id' => Program::factory()->create()->id, 'mentor_id' => $notMentor->id])
            ->assertStatus(422)
            ->assertJsonValidationErrors('mentor_id');
    }

    public function test_cohort_requires_a_program_on_create(): void
    {
        $this->actingAs($this->admin())
            ->postJson('/api/admin/cohorts', ['name' => 'Angkatan 1'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('program_id');
    }

    public function test_cohort_row_includes_its_program(): void
    {
        $program = Program::factory()->active()->create();

        $this->actingAs($this->admin())
            ->postJson('/api/admin/cohorts', ['name' => 'Angkatan 1', 'program_id' => $program->id])
            ->assertCreated()
            ->assertJsonPath('cohort.program.id', $program->id);
    }

    public function test_status_is_derived_from_dates(): void
    {
        $active = Cohort::factory()->create([
            'start_date' => now()->subDay()->toDateString(),
            'end_date' => now()->addMonth()->toDateString(),
        ]);
        $ended = Cohort::factory()->create([
            'start_date' => now()->subMonths(3)->toDateString(),
            'end_date' => now()->subMonth()->toDateString(),
        ]);

        $this->assertSame('active', $active->status);
        $this->assertSame('ended', $ended->status);
    }

    public function test_angkatan_carries_the_registration_window(): void
    {
        $program = Program::factory()->active()->create();

        $this->actingAs($this->admin())
            ->postJson('/api/admin/cohorts', [
                'name' => 'Angkatan 1',
                'program_id' => $program->id,
                'registration_opens_at' => now()->subDay()->toDateTimeString(),
                'registration_closes_at' => now()->addWeek()->toDateTimeString(),
            ])
            ->assertCreated()
            ->assertJsonPath('cohort.registration_open', true);

        $this->assertTrue($program->fresh()->isOpen());
    }

    public function test_date_only_close_date_is_inclusive(): void
    {
        $program = Program::factory()->active()->create();

        $this->actingAs($this->admin())
            ->postJson('/api/admin/cohorts', [
                'name' => 'Angkatan Inklusif',
                'program_id' => $program->id,
                'registration_opens_at' => now()->subDay()->toDateString(),
                'registration_closes_at' => now()->toDateString(),
            ])
            ->assertCreated()
            ->assertJsonPath('cohort.registration_open', true);
    }

    public function test_partial_update_cannot_close_registration_before_stored_open_date(): void
    {
        $cohort = Cohort::factory()->create([
            'registration_opens_at' => now()->addDays(10),
            'registration_closes_at' => now()->addDays(30),
        ]);

        $this->actingAs($this->admin())
            ->patchJson("/api/admin/cohorts/{$cohort->id}", [
                'registration_closes_at' => now()->addDay()->toDateTimeString(),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('registration_closes_at');
    }

    public function test_cohort_with_enrollments_cannot_be_deleted(): void
    {
        $cohort = Cohort::factory()->create();

        $personId = DB::table('people')->insertGetId([
            'name' => 'Peserta Uji',
            'phone' => '+628123456789',
            'email' => 'peserta.uji@example.test',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('enrollments')->insert([
            'people_id' => $personId,
            'cohort_id' => $cohort->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($this->admin())
            ->deleteJson("/api/admin/cohorts/{$cohort->id}")
            ->assertStatus(422);
    }

    public function test_cohort_referenced_by_applications_cannot_be_deleted(): void
    {
        $program = Program::factory()->active()->create();
        $cohort = Cohort::factory()->create(['program_id' => $program->id]);

        $personId = DB::table('people')->insertGetId([
            'name' => 'Pendaftar Uji',
            'phone' => '+628123450000',
            'email' => 'pendaftar.uji@example.test',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('applications')->insert([
            'people_id' => $personId,
            'program_id' => $program->id,
            'cohort_id' => $cohort->id,
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($this->admin())
            ->deleteJson("/api/admin/cohorts/{$cohort->id}")
            ->assertStatus(422);
    }

    public function test_empty_cohort_can_be_deleted(): void
    {
        $cohort = Cohort::factory()->create();

        $this->actingAs($this->admin())
            ->deleteJson("/api/admin/cohorts/{$cohort->id}")
            ->assertNoContent();
    }
}
