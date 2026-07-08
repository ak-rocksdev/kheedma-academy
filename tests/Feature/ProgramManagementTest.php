<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\Cohort;
use App\Models\Person;
use App\Models\Program;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProgramManagementTest extends TestCase
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

    public function test_admin_can_create_a_program(): void
    {
        $this->actingAs($this->admin())
            ->postJson('/api/admin/programs', [
                'name' => 'Program Affiliate Pemula',
                'slug' => 'affiliate-pemula',
                'tagline' => 'Dari nol jadi affiliator amanah.',
                'status' => 'active',
                'selection_mode' => 'selective',
            ])
            ->assertCreated()
            ->assertJsonPath('program.slug', 'affiliate-pemula')
            ->assertJsonPath('program.is_open', false);
    }

    public function test_slug_must_be_unique_and_kebab(): void
    {
        Program::factory()->create(['slug' => 'affiliate-pemula']);

        $this->actingAs($this->admin())
            ->postJson('/api/admin/programs', [
                'name' => 'X', 'slug' => 'affiliate-pemula', 'status' => 'draft', 'selection_mode' => 'selective',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('slug');

        $this->actingAs($this->admin())
            ->postJson('/api/admin/programs', [
                'name' => 'X', 'slug' => 'Bukan Slug!', 'status' => 'draft', 'selection_mode' => 'selective',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('slug');
    }

    public function test_mentor_is_forbidden(): void
    {
        $mentor = User::factory()->mentor()->create();

        $this->actingAs($mentor)->getJson('/api/admin/programs')->assertForbidden();
    }

    public function test_partial_update_changes_only_sent_fields(): void
    {
        $program = Program::factory()->active()->create(['name' => 'Lama']);

        $this->actingAs($this->admin())
            ->patchJson("/api/admin/programs/{$program->id}", ['name' => 'Baru'])
            ->assertOk()
            ->assertJsonPath('program.name', 'Baru')
            ->assertJsonPath('program.slug', $program->slug);
    }

    public function test_program_with_applications_cannot_be_deleted(): void
    {
        $program = Program::factory()->create();
        $person = Person::create([
            'name' => 'Peserta Uji', 'phone' => '+628123456700', 'email' => 'uji@example.test',
        ]);
        Application::create(['people_id' => $person->id, 'status' => 'pending', 'program_id' => $program->id]);

        $this->actingAs($this->admin())
            ->deleteJson("/api/admin/programs/{$program->id}")
            ->assertStatus(422);
    }

    public function test_program_with_cohorts_cannot_be_deleted(): void
    {
        $program = Program::factory()->create();
        Cohort::factory()->create(['program_id' => $program->id]);

        $this->actingAs($this->admin())
            ->deleteJson("/api/admin/programs/{$program->id}")
            ->assertStatus(422);
    }

    public function test_empty_program_can_be_deleted(): void
    {
        $program = Program::factory()->create();

        $this->actingAs($this->admin())
            ->deleteJson("/api/admin/programs/{$program->id}")
            ->assertNoContent();
    }

    public function test_index_derives_is_open_without_per_row_queries(): void
    {
        $open = Program::factory()->active()->create(['name' => 'Terbuka']);
        Cohort::factory()->openWindow()->create(['program_id' => $open->id]);
        Program::factory()->active()->create(['name' => 'Tanpa Batch']);

        $response = $this->actingAs($this->admin())->getJson('/api/admin/programs')->assertOk();

        $rows = collect($response->json('data'))->keyBy('name');
        $this->assertTrue($rows['Terbuka']['is_open']);
        $this->assertFalse($rows['Tanpa Batch']['is_open']);
    }

    public function test_affiliate_program_requires_level(): void
    {
        $this->actingAs($this->admin())
            ->postJson('/api/admin/programs', [
                'name' => 'Affiliate Tanpa Level',
                'slug' => 'affiliate-tanpa-level',
                'status' => 'draft',
                'selection_mode' => 'selective',
                'type' => 'affiliate_community',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('level');
    }

    public function test_general_program_rejects_level(): void
    {
        $this->actingAs($this->admin())
            ->postJson('/api/admin/programs', [
                'name' => 'Umum Berlevel',
                'slug' => 'umum-berlevel',
                'status' => 'draft',
                'selection_mode' => 'selective',
                'type' => 'general',
                'level' => 2,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('level');
    }

    public function test_segmentation_fields_round_trip_through_the_api(): void
    {
        $res = $this->actingAs($this->admin())
            ->postJson('/api/admin/programs', [
                'name' => 'Affiliate Level Dua',
                'slug' => 'affiliate-level-dua',
                'status' => 'active',
                'selection_mode' => 'selective',
                'type' => 'affiliate_community',
                'level' => 2,
                'locked_message' => 'Selesaikan Level 1 dulu.',
            ])
            ->assertCreated();

        $res->assertJsonPath('program.type', 'affiliate_community')
            ->assertJsonPath('program.level', 2)
            ->assertJsonPath('program.locked_message', 'Selesaikan Level 1 dulu.');
    }

    public function test_partial_update_of_affiliate_program_does_not_require_level(): void
    {
        $program = Program::factory()->affiliate(2)->create();

        $this->actingAs($this->admin())
            ->patchJson("/api/admin/programs/{$program->id}", ['locked_message' => 'Copy baru.'])
            ->assertOk()
            ->assertJsonPath('program.level', 2)
            ->assertJsonPath('program.locked_message', 'Copy baru.');
    }

    public function test_switching_type_to_general_clears_level(): void
    {
        $program = Program::factory()->affiliate(2)->create();

        $this->actingAs($this->admin())
            ->patchJson("/api/admin/programs/{$program->id}", ['type' => 'general'])
            ->assertOk()
            ->assertJsonPath('program.type', 'general')
            ->assertJsonPath('program.level', null);
    }
}
