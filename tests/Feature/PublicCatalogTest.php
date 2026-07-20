<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\Cohort;
use App\Models\CohortSession;
use App\Models\Person;
use App\Models\Program;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicCatalogTest extends TestCase
{
    use RefreshDatabase;

    /** Active program with an open-window Angkatan: open for registration. */
    private function openProgram(): Program
    {
        $program = Program::factory()->active()->create();
        Cohort::factory()->openWindow()->create(['program_id' => $program->id]);

        return $program;
    }

    public function test_chooser_lists_only_open_programs(): void
    {
        $open = $this->openProgram();
        $open->update(['name' => 'Program Terbuka']);

        $inactive = Program::factory()->inactive()->create(['name' => 'Program Tertutup']);
        Cohort::factory()->openWindow()->create(['program_id' => $inactive->id]);

        Program::factory()->draft()->create(['name' => 'Program Draf']);

        $expired = Program::factory()->active()->create(['name' => 'Program Kedaluwarsa']);
        Cohort::factory()->closedWindow()->create(['program_id' => $expired->id]);

        $this->get('/daftar')
            ->assertOk()
            ->assertSee('Program Terbuka')
            ->assertDontSee('Program Tertutup')
            ->assertDontSee('Program Draf')
            ->assertDontSee('Program Kedaluwarsa')
            ->assertSee('Komunitas');
    }

    public function test_chooser_without_programs_still_offers_community(): void
    {
        $this->get('/daftar')
            ->assertOk()
            ->assertSee('Komunitas');
    }

    public function test_landing_shows_cta_when_open(): void
    {
        $program = $this->openProgram();

        $this->get("/program/{$program->slug}")
            ->assertOk()
            ->assertSee($program->name)
            ->assertSee(route('program.apply', $program), false);
    }

    public function test_landing_shows_closed_state_when_inactive(): void
    {
        $program = Program::factory()->inactive()->create();
        Cohort::factory()->openWindow()->create(['program_id' => $program->id]);

        $this->get("/program/{$program->slug}")
            ->assertOk()
            ->assertSee('Pendaftaran ditutup')
            ->assertDontSee(route('program.apply', $program), false);
    }

    public function test_landing_shows_class_start_date_when_open(): void
    {
        $program = Program::factory()->active()->create();
        Cohort::factory()->openWindow()->create([
            'program_id' => $program->id,
            'start_date' => '2026-08-01',
        ]);

        $this->get("/program/{$program->slug}")
            ->assertOk()
            ->assertSee('Kelas dimulai')
            ->assertSee('1 Agustus 2026');
    }

    public function test_landing_shows_start_time_when_not_midnight(): void
    {
        $program = Program::factory()->active()->create();
        Cohort::factory()->openWindow()->create([
            'program_id' => $program->id,
            'start_date' => '2026-08-01 14:00:00',
        ]);

        $this->get("/program/{$program->slug}")
            ->assertOk()
            ->assertSee('1 Agustus 2026 pukul 14.00 WIB');
    }

    public function test_landing_omits_start_time_for_legacy_midnight_start(): void
    {
        $program = Program::factory()->active()->create();
        Cohort::factory()->openWindow()->create([
            'program_id' => $program->id,
            'start_date' => '2026-08-01 00:00:00',
        ]);

        $this->get("/program/{$program->slug}")
            ->assertOk()
            ->assertSee('1 Agustus 2026')
            ->assertDontSee('pukul');
    }

    public function test_landing_shows_nearest_start_when_two_cohorts_are_open(): void
    {
        $program = Program::factory()->active()->create();
        Cohort::factory()->openWindow()->create(['program_id' => $program->id, 'start_date' => '2026-09-01']);
        Cohort::factory()->openWindow()->create(['program_id' => $program->id, 'start_date' => '2026-08-01']);

        $this->get("/program/{$program->slug}")
            ->assertOk()
            ->assertSee('1 Agustus 2026')
            ->assertDontSee('1 September 2026');
    }

    public function test_chooser_shows_affiliate_section_with_locked_teaser(): void
    {
        Program::factory()->affiliate(1)->active()->create(['name' => 'Affiliate Kelas Satu']);

        $this->get('/daftar')
            ->assertOk()
            ->assertSee('Kheedma Affiliate Community')
            ->assertSee('Affiliate Kelas Satu')
            ->assertSee('Terkunci')
            ->assertSee('data-lock-trigger', false);
    }

    public function test_chooser_hides_inactive_affiliate_classes(): void
    {
        Program::factory()->affiliate(1)->draft()->create(['name' => 'Affiliate Rahasia']);

        $this->get('/daftar')
            ->assertOk()
            ->assertDontSee('Affiliate Rahasia');
    }

    public function test_affiliate_landing_shows_locked_state_not_apply_cta(): void
    {
        $program = Program::factory()->affiliate(1)->active()->create();
        Cohort::factory()->create([
            'program_id' => $program->id,
            'registration_opens_at' => now()->subDay(),
            'registration_closes_at' => now()->addWeek(),
        ]);

        $this->get(route('program.show', $program))
            ->assertOk()
            ->assertSee('Terkunci')
            ->assertDontSee(route('program.apply', $program), false);
    }

    public function test_locked_message_falls_back_to_config_default(): void
    {
        Program::factory()->affiliate(1)->active()->create(['locked_message' => null]);

        $this->get('/daftar')
            ->assertOk()
            ->assertSee(e(config('kheedma.default_locked_message')), false);
    }

    public function test_member_area_lists_affiliate_ladder_with_lock_state(): void
    {
        $this->seed(RoleSeeder::class);

        Program::factory()->affiliate(1)->active()->create(['name' => 'Affiliate Kelas Satu']);

        $user = User::factory()->create();
        $user->assignRole('participant');
        Person::create([
            'name' => 'Member Uji',
            'phone' => '+628111111111',
            'email' => 'member.uji@example.test',
            'user_id' => $user->id,
        ]);

        $this->actingAs($user)
            ->get('/akun?bagian=kelas')
            ->assertOk()
            ->assertSee('Program untuk Anda')
            ->assertSee('Affiliate Kelas Satu')
            ->assertSee('data-lock-trigger', false);
    }

    /** Member login dengan lamaran pending pada program yang diberikan. */
    private function memberWithPendingApplication(Program $program): User
    {
        $this->seed(RoleSeeder::class);

        $user = User::factory()->create();
        $user->assignRole('participant');
        $person = Person::create([
            'name' => 'Member Uji',
            'phone' => '+628999000111',
            'email' => 'member.uji@example.test',
        ]);
        $person->user()->associate($user); // user_id is guarded by design
        $person->save();
        Application::create(['people_id' => $person->id, 'program_id' => $program->id, 'status' => 'pending']);

        return $user;
    }

    public function test_landing_shows_review_pill_instead_of_apply_cta_for_pending_member(): void
    {
        $program = $this->openProgram();
        $user = $this->memberWithPendingApplication($program);

        $this->actingAs($user)
            ->get(route('program.show', $program))
            ->assertOk()
            ->assertSee('sedang direview')
            ->assertDontSee(route('program.apply', $program), false);
    }

    public function test_chooser_marks_applied_program_for_pending_member(): void
    {
        $program = $this->openProgram();
        $program->update(['name' => 'Program Dilamar']);
        $user = $this->memberWithPendingApplication($program);

        $this->actingAs($user)
            ->get('/daftar')
            ->assertOk()
            ->assertSee('menunggu review');
    }

    public function test_landing_shows_flash_notice_after_blocked_duplicate(): void
    {
        $program = $this->openProgram();

        $this->withSession(['_flash' => ['old' => [], 'new' => []], 'application_notice' => 'Kamu sudah terdaftar di program ini.'])
            ->get(route('program.show', $program))
            ->assertOk()
            ->assertSee('Kamu sudah terdaftar di program ini.');
    }

    public function test_chooser_card_shows_class_count(): void
    {
        $program = Program::factory()->active()->create(['name' => 'Program Hitung Kelas']);
        $cohort = Cohort::factory()->openWindow()->create(['program_id' => $program->id]);
        CohortSession::factory()->for($cohort)->count(4)->create();

        $this->get('/daftar')
            ->assertOk()
            ->assertSee('Program Hitung Kelas')
            ->assertSee('4 kelas');
    }
}
