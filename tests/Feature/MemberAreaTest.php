<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\Cohort;
use App\Models\Enrollment;
use App\Models\Person;
use App\Models\Program;
use App\Models\StatusEvent;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MemberAreaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    /** @return array{0: User, 1: Person} */
    private function member(): array
    {
        $user = User::factory()->create();
        $user->assignRole('participant');
        $person = Person::create([
            'name' => 'Member '.fake()->unique()->numberBetween(1, 99999),
            'phone' => '+628'.fake()->unique()->numerify('##########'),
            'email' => fake()->unique()->safeEmail(),
        ]);
        $person->user()->associate($user); // user_id is guarded by design
        $person->save();

        return [$user, $person];
    }

    public function test_member_area_lists_open_classes_with_register_link(): void
    {
        [$user] = $this->member();
        $program = Program::factory()->active()->create(['name' => 'Kelas Terbuka Uji']);
        Cohort::factory()->openWindow()->create(['program_id' => $program->id, 'start_date' => '2026-09-01']);

        $this->actingAs($user)
            ->get('/akun?bagian=kelas')
            ->assertOk()
            ->assertSee('Kelas Dibuka')
            ->assertSee('Kelas Terbuka Uji')
            ->assertSee('1 September 2026')
            ->assertSee(route('program.apply', $program), false);
    }

    public function test_applied_class_shows_status_chip_instead_of_register_link(): void
    {
        [$user, $person] = $this->member();
        $program = Program::factory()->active()->create();
        $cohort = Cohort::factory()->openWindow()->create(['program_id' => $program->id]);
        Application::create(['people_id' => $person->id, 'program_id' => $program->id, 'cohort_id' => $cohort->id, 'status' => 'pending']);

        $this->actingAs($user)
            ->get('/akun?bagian=kelas')
            ->assertOk()
            ->assertSee('Menunggu review')
            ->assertDontSee(route('program.apply', $program), false);
    }

    public function test_account_tabs_split_the_sections(): void
    {
        [$user] = $this->member();
        $program = Program::factory()->active()->create(['name' => 'Kelas Tab Uji']);
        Cohort::factory()->openWindow()->create(['program_id' => $program->id]);

        // Default = tab Profil (tab pertama): data diri tampil, seksi lain tidak.
        $this->actingAs($user)->get('/akun')
            ->assertOk()
            ->assertSee('Nomor HP')
            ->assertDontSee('Status Pendaftaran')
            ->assertDontSee('Kelas Tab Uji');

        // Tab Pendaftaran: status ada, daftar kelas tidak.
        $this->actingAs($user)->get('/akun?bagian=pendaftaran')
            ->assertOk()
            ->assertSee('Status Pendaftaran')
            ->assertDontSee('Kelas Tab Uji');

        // Tab Kelas & Program: kelas ada, status tidak.
        $this->actingAs($user)->get('/akun?bagian=kelas')
            ->assertOk()
            ->assertSee('Kelas Tab Uji')
            ->assertDontSee('Status Pendaftaran');

        // Nilai bagian tak dikenal jatuh kembali ke tab default.
        $this->actingAs($user)->get('/akun?bagian=ngawur')
            ->assertOk()
            ->assertSee('Nomor HP');

        // Navigasi utama ke beranda tetap tersedia dari halaman akun.
        $this->actingAs($user)->get('/akun')->assertSee('Beranda');
    }

    public function test_profile_tab_shows_community_membership_state(): void
    {
        [$user] = $this->member();

        $this->actingAs($user)->get('/akun?bagian=profil')
            ->assertOk()
            ->assertSee('belum bergabung dengan komunitas')
            ->assertSee(route('komunitas'), false);
    }

    public function test_status_card_names_the_cohort(): void
    {
        [$user, $person] = $this->member();
        $program = Program::factory()->active()->create();
        $cohort = Cohort::factory()->create(['program_id' => $program->id, 'name' => 'Angkatan Uji 9']);
        Application::create(['people_id' => $person->id, 'program_id' => $program->id, 'cohort_id' => $cohort->id, 'status' => 'pending']);

        $this->actingAs($user)
            ->get('/akun?bagian=pendaftaran')
            ->assertOk()
            ->assertSee('Status Pendaftaran')
            ->assertSee('Angkatan Uji 9');
    }

    public function test_rejected_application_shows_the_review_note_and_retry_copy(): void
    {
        [$user, $person] = $this->member();
        $program = Program::factory()->active()->create();
        Application::create([
            'people_id' => $person->id,
            'program_id' => $program->id,
            'status' => 'rejected',
            'review_note' => 'Fokus dulu ke followers minimal 500 ya.',
        ]);

        $this->actingAs($user)
            ->get('/akun?bagian=pendaftaran')
            ->assertOk()
            ->assertSee('Belum lolos')
            ->assertSee('Fokus dulu ke followers minimal 500 ya.')
            ->assertSee('boleh mendaftar lagi');
    }

    public function test_rejected_without_note_shows_no_note_block(): void
    {
        [$user, $person] = $this->member();
        $program = Program::factory()->active()->create();
        Application::create(['people_id' => $person->id, 'program_id' => $program->id, 'status' => 'rejected']);

        $this->actingAs($user)
            ->get('/akun?bagian=pendaftaran')
            ->assertOk()
            ->assertSee('Belum lolos')
            ->assertDontSee('Catatan dari tim');
    }

    public function test_enrolled_member_sees_kelasmu_with_location_and_maps_link(): void
    {
        [$user, $person] = $this->member();
        $program = Program::factory()->active()->create(['name' => 'Kelas Offline Uji']);
        $cohort = Cohort::factory()->atLocation()->create([
            'program_id' => $program->id,
            'name' => 'Angkatan Offline 1',
            'start_date' => '2026-08-01 09:00:00',
        ]);
        $enrollment = Enrollment::create(['people_id' => $person->id, 'cohort_id' => $cohort->id]);
        StatusEvent::create(['enrollment_id' => $enrollment->id, 'status' => 'accepted', 'occurred_at' => now()]);

        $this->actingAs($user)
            ->get('/akun?bagian=kelas')
            ->assertOk()
            ->assertSee('Kelasmu')
            ->assertSee('Kelas Offline Uji')
            ->assertSee('Angkatan Offline 1')
            ->assertSee('1 Agustus 2026 pukul 09.00 WIB')
            ->assertSee($cohort->location_name)
            ->assertSee($cohort->location_address)
            ->assertSee('Lihat lokasi di peta')
            ->assertSee('Petunjuk arah')
            ->assertSee($cohort->mapsEmbedUrl())
            ->assertSee($cohort->mapsDirectionsUrl())
            ->assertSee($cohort->mapsUrl());
    }

    public function test_enrolled_member_sees_meeting_link_for_online_cohort(): void
    {
        [$user, $person] = $this->member();
        $program = Program::factory()->active()->create();
        $cohort = Cohort::factory()->online()->create(['program_id' => $program->id]);
        $enrollment = Enrollment::create(['people_id' => $person->id, 'cohort_id' => $cohort->id]);
        StatusEvent::create(['enrollment_id' => $enrollment->id, 'status' => 'accepted', 'occurred_at' => now()]);

        $this->actingAs($user)
            ->get('/akun?bagian=kelas')
            ->assertOk()
            ->assertSee('Kelas online')
            ->assertSee('Gabung meeting')
            ->assertSee($cohort->meeting_url, false);
    }

    public function test_enrolled_online_cohort_without_meeting_url_shows_placeholder(): void
    {
        [$user, $person] = $this->member();
        $program = Program::factory()->active()->create();
        $cohort = Cohort::factory()->create(['program_id' => $program->id, 'type' => 'online', 'meeting_url' => null]);
        $enrollment = Enrollment::create(['people_id' => $person->id, 'cohort_id' => $cohort->id]);
        StatusEvent::create(['enrollment_id' => $enrollment->id, 'status' => 'accepted', 'occurred_at' => now()]);

        $this->actingAs($user)
            ->get('/akun?bagian=kelas')
            ->assertOk()
            ->assertSee('Kelas online')
            ->assertSee('Link meeting akan dibagikan sebelum kelas dimulai.')
            ->assertDontSee('Gabung meeting');
    }

    public function test_enrolled_cohort_with_materials_shows_materials_link(): void
    {
        [$user, $person] = $this->member();
        $program = Program::factory()->active()->create();
        $cohort = Cohort::factory()->create([
            'program_id' => $program->id,
            'materials_url' => 'https://drive.google.com/materi-uji',
        ]);
        $enrollment = Enrollment::create(['people_id' => $person->id, 'cohort_id' => $cohort->id]);
        StatusEvent::create(['enrollment_id' => $enrollment->id, 'status' => 'accepted', 'occurred_at' => now()]);

        $this->actingAs($user)
            ->get('/akun?bagian=kelas')
            ->assertOk()
            ->assertSee('Buka materi kelas')
            ->assertSee('https://drive.google.com/materi-uji', false);
    }

    public function test_dropped_enrollment_does_not_show_in_kelasmu(): void
    {
        [$user, $person] = $this->member();
        $program = Program::factory()->active()->create(['name' => 'Kelas Dropped Uji']);
        $cohort = Cohort::factory()->create(['program_id' => $program->id]);
        $enrollment = Enrollment::create(['people_id' => $person->id, 'cohort_id' => $cohort->id]);
        StatusEvent::create(['enrollment_id' => $enrollment->id, 'status' => 'dropped', 'occurred_at' => now()]);

        $this->actingAs($user)
            ->get('/akun?bagian=kelas')
            ->assertOk()
            ->assertDontSee('Kelasmu')
            ->assertDontSee('Kelas Dropped Uji');
    }

    public function test_member_not_enrolled_sees_no_kelasmu_section(): void
    {
        [$user] = $this->member();
        $program = Program::factory()->active()->create();
        Cohort::factory()->openWindow()->create(['program_id' => $program->id]);

        $this->actingAs($user)
            ->get('/akun?bagian=kelas')
            ->assertOk()
            ->assertDontSee('Kelasmu');
    }
}
