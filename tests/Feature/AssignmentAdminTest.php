<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\Cohort;
use App\Models\CohortSession;
use App\Models\Program;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssignmentAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
    }

    public function mentor(): User
    {
        $user = User::factory()->create();
        $user->assignRole('mentor');

        return $user;
    }

    public function cohortSession(): CohortSession
    {
        $cohort = Cohort::factory()->create(['program_id' => Program::factory()->active()->create()->id]);

        return CohortSession::factory()->for($cohort)->create();
    }

    public function test_mentor_can_create_and_update_the_assignment(): void
    {
        $session = $this->cohortSession();
        $mentor = $this->mentor();

        $this->actingAs($mentor)
            ->putJson("/api/admin/sessions/{$session->id}/assignment", [
                'title' => 'Tugas Riset Produk',
                'body' => 'Cari 3 produk winning dan tulis alasannya.',
            ])
            ->assertOk()
            ->assertJsonPath('assignment.title', 'Tugas Riset Produk')
            ->assertJsonPath('assignment.updated_by', $mentor->name)
            ->assertJsonPath('assignment.pending_count', 0);

        $editor = $this->mentor();
        $this->actingAs($editor)
            ->putJson("/api/admin/sessions/{$session->id}/assignment", [
                'title' => 'Tugas Riset Produk v2',
                'body' => 'Cari 5 produk winning.',
            ])
            ->assertOk()
            ->assertJsonPath('assignment.title', 'Tugas Riset Produk v2');

        $assignment = Assignment::sole();
        $this->assertSame($mentor->id, $assignment->created_by, 'creator must survive edits');
        $this->assertSame($editor->id, $assignment->updated_by);
    }

    public function test_client_cannot_spoof_authorship_fields(): void
    {
        $session = $this->cohortSession();
        $mentor = $this->mentor();

        $this->actingAs($mentor)
            ->putJson("/api/admin/sessions/{$session->id}/assignment", [
                'title' => 'Tugas',
                'body' => 'Isi tugas.',
                'created_by' => 999,
                'updated_by' => 999,
            ])
            ->assertOk();

        $this->assertSame($mentor->id, Assignment::sole()->created_by);
        $this->assertSame($mentor->id, Assignment::sole()->updated_by);
    }

    public function test_title_and_body_are_required(): void
    {
        $session = $this->cohortSession();

        $this->actingAs($this->mentor())
            ->putJson("/api/admin/sessions/{$session->id}/assignment", ['title' => '', 'body' => ''])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['title', 'body']);
    }

    public function test_participant_cannot_manage_assignments(): void
    {
        $session = $this->cohortSession();
        $user = User::factory()->create();
        $user->assignRole('participant');

        $this->actingAs($user)
            ->putJson("/api/admin/sessions/{$session->id}/assignment", ['title' => 'X', 'body' => 'Y'])
            ->assertForbidden();
    }
}
