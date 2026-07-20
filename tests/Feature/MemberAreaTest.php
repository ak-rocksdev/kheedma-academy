<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Attendance;
use App\Models\Cohort;
use App\Models\CohortSession;
use App\Models\Enrollment;
use App\Models\Person;
use App\Models\Program;
use App\Models\SessionConfirmation;
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

    public function test_enrolled_member_lands_on_kelas_tab_by_default(): void
    {
        [$user, $person] = $this->member();
        $program = Program::factory()->active()->create(['name' => 'Kelas Default Uji']);
        $cohort = Cohort::factory()->create(['program_id' => $program->id]);
        Enrollment::create(['people_id' => $person->id, 'cohort_id' => $cohort->id]);

        // No ?bagian: the enrolled member's destination is their class.
        $this->actingAs($user)->get('/akun')
            ->assertOk()
            ->assertSee('Kelas Default Uji')
            ->assertDontSee('Nomor HP');

        // An explicit ?bagian always wins over the smart default.
        $this->actingAs($user)->get('/akun?bagian=profil')
            ->assertOk()
            ->assertSee('Nomor HP');
    }

    public function test_countdown_shows_and_map_stays_collapsed_near_class_day(): void
    {
        [$user, $person] = $this->member();
        $program = Program::factory()->active()->create();
        $cohort = Cohort::factory()->create(['program_id' => $program->id]);
        CohortSession::factory()->for($cohort)->atLocation()->create([
            'scheduled_at' => now()->addDay()->setTime(9, 30),
        ]);
        $enrollment = Enrollment::create(['people_id' => $person->id, 'cohort_id' => $cohort->id]);
        StatusEvent::create(['enrollment_id' => $enrollment->id, 'status' => 'accepted', 'occurred_at' => now()]);

        // PO decision 2026-07-20: the map never auto-opens, even on class day.
        $this->actingAs($user)->get('/akun?bagian=kelas')
            ->assertOk()
            ->assertSee('Besok')
            ->assertSee('<details class="kh-collapsible group', false)
            ->assertDontSee('<details open', false);
    }

    public function test_kelas_tab_explains_pending_and_awaiting_placement_states(): void
    {
        [$user, $person] = $this->member();
        $program = Program::factory()->active()->create();

        $application = Application::create(['people_id' => $person->id, 'program_id' => $program->id, 'status' => 'pending']);
        $this->actingAs($user)->get('/akun?bagian=kelas')
            ->assertOk()
            ->assertSee('sedang ditinjau');

        $application->update(['status' => 'accepted']);
        $this->actingAs($user)->get('/akun?bagian=kelas')
            ->assertOk()
            ->assertSee('Pendaftaranmu diterima');
    }

    public function test_kelas_card_names_the_mentor(): void
    {
        [$user, $person] = $this->member();
        $mentor = User::factory()->create(['name' => 'Ustadz Mentor Uji']);
        $mentor->assignRole('mentor');
        $program = Program::factory()->active()->create();
        $cohort = Cohort::factory()->create(['program_id' => $program->id, 'mentor_id' => $mentor->id]);
        Enrollment::create(['people_id' => $person->id, 'cohort_id' => $cohort->id]);

        $this->actingAs($user)->get('/akun?bagian=kelas')
            ->assertOk()
            ->assertSee('Ustadz Mentor Uji');
    }

    public function test_ended_class_becomes_a_personal_record(): void
    {
        [$user, $person] = $this->member();
        $program = Program::factory()->active()->create();
        $cohort = Cohort::factory()->create([
            'program_id' => $program->id,
            'start_date' => now()->subDays(10)->setTime(9, 30),
            'end_date' => now()->subDays(10),
        ]);
        $session = CohortSession::create(['cohort_id' => $cohort->id, 'title' => 'Pertemuan 1', 'scheduled_at' => $cohort->start_date, 'position' => 1]);
        $enrollment = Enrollment::create(['people_id' => $person->id, 'cohort_id' => $cohort->id]);
        Attendance::create(['cohort_session_id' => $session->id, 'enrollment_id' => $enrollment->id]);

        // Attended + ended: a record, not a stale invite — and the meeting
        // button for a finished class must be gone.
        $this->actingAs($user)->get('/akun?bagian=kelas')
            ->assertOk()
            ->assertSee('Selesai')
            ->assertSee('Kamu hadir di kelas ini')
            ->assertDontSee('Gabung meeting');
    }

    public function test_ended_class_without_attendance_shows_neutral_closure(): void
    {
        [$user, $person] = $this->member();
        $program = Program::factory()->active()->create();
        $cohort = Cohort::factory()->create([
            'program_id' => $program->id,
            'start_date' => now()->subDays(10)->setTime(9, 30),
            'end_date' => now()->subDays(10),
        ]);
        Enrollment::create(['people_id' => $person->id, 'cohort_id' => $cohort->id]);

        $this->actingAs($user)->get('/akun?bagian=kelas')
            ->assertOk()
            ->assertSee('Kelas telah selesai')
            ->assertDontSee('Kamu hadir di kelas ini');
    }

    public function test_enrolled_member_sees_kelasmu_with_location_and_maps_link(): void
    {
        [$user, $person] = $this->member();
        $program = Program::factory()->active()->create(['name' => 'Kelas Offline Uji']);
        $cohort = Cohort::factory()->create([
            'program_id' => $program->id,
            'name' => 'Angkatan Offline 1',
            'start_date' => '2026-08-01 09:00:00',
        ]);
        $session = CohortSession::factory()->for($cohort)->atLocation()->create([
            'title' => 'Kelas 1: Riset Produk',
            'scheduled_at' => '2026-08-01 09:00:00',
        ]);
        $enrollment = Enrollment::create(['people_id' => $person->id, 'cohort_id' => $cohort->id]);
        StatusEvent::create(['enrollment_id' => $enrollment->id, 'status' => 'accepted', 'occurred_at' => now()]);

        $this->actingAs($user)
            ->get('/akun?bagian=kelas')
            ->assertOk()
            ->assertSee('Kelas Saya')
            ->assertSee('Kelas Offline Uji')
            ->assertSee('Angkatan Offline 1')
            ->assertSee('1 Agustus 2026 pukul 09.00 WIB')
            ->assertSee($session->location_name)
            ->assertSee($session->location_address)
            ->assertSee('Tatap muka')
            ->assertSee('Tambahkan ke Google Calendar')
            ->assertSee('Petunjuk arah')
            ->assertSee($session->mapsEmbedUrl())
            ->assertSee($session->mapsDirectionsUrl())
            ->assertSee($session->mapsUrl());
    }

    public function test_enrolled_member_sees_meeting_link_for_online_cohort(): void
    {
        [$user, $person] = $this->member();
        $program = Program::factory()->active()->create();
        $cohort = Cohort::factory()->create(['program_id' => $program->id]);
        $session = CohortSession::factory()->for($cohort)->online()->create();
        $enrollment = Enrollment::create(['people_id' => $person->id, 'cohort_id' => $cohort->id]);
        StatusEvent::create(['enrollment_id' => $enrollment->id, 'status' => 'accepted', 'occurred_at' => now()]);

        $this->actingAs($user)
            ->get('/akun?bagian=kelas')
            ->assertOk()
            ->assertSee('Kelas online')
            ->assertSee('Gabung meeting')
            ->assertSee($session->meeting_url, false);
    }

    public function test_enrolled_online_cohort_without_meeting_url_shows_placeholder(): void
    {
        [$user, $person] = $this->member();
        $program = Program::factory()->active()->create();
        $cohort = Cohort::factory()->create(['program_id' => $program->id]);
        CohortSession::factory()->for($cohort)->create(['type' => 'online', 'meeting_url' => null]);
        $enrollment = Enrollment::create(['people_id' => $person->id, 'cohort_id' => $cohort->id]);
        StatusEvent::create(['enrollment_id' => $enrollment->id, 'status' => 'accepted', 'occurred_at' => now()]);

        $this->actingAs($user)
            ->get('/akun?bagian=kelas')
            ->assertOk()
            ->assertSee('Kelas online')
            ->assertSee('Link meeting akan dibagikan sebelum kelas dimulai.')
            ->assertDontSee('Gabung meeting');
    }

    public function test_enrolled_member_sees_each_class_of_the_batch(): void
    {
        [$user, $person] = $this->member();
        $cohort = Cohort::factory()->create(['program_id' => Program::factory()->active()->create()->id]);
        CohortSession::factory()->for($cohort)->atLocation()->create(['title' => 'Kelas 1: Riset Produk']);
        CohortSession::factory()->for($cohort)->online()->create(['title' => 'Kelas 2: Konten']);
        Enrollment::create(['people_id' => $person->id, 'cohort_id' => $cohort->id]);

        $this->actingAs($user)->get('/akun?bagian=kelas')
            ->assertOk()
            ->assertSee('Kelas 1: Riset Produk')
            ->assertSee('Kelas 2: Konten');
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

        // "Kelas Saya" itself always appears in the account menu, so the
        // negative check targets the dropped cohort's own content.
        $this->actingAs($user)
            ->get('/akun?bagian=kelas')
            ->assertOk()
            ->assertDontSee('Kelas Dropped Uji');
    }

    public function test_member_not_enrolled_sees_no_kelasmu_section(): void
    {
        [$user] = $this->member();
        $program = Program::factory()->active()->create(['name' => 'Kelas Belum Diikuti']);
        Cohort::factory()->openWindow()->create(['program_id' => $program->id, 'materials_url' => 'https://drive.google.com/rahasia']);

        // The open class is listed for registration, but none of its
        // enrolled-only logistics leak to a non-enrolled member.
        $this->actingAs($user)
            ->get('/akun?bagian=kelas')
            ->assertOk()
            ->assertSee('Kelas Belum Diikuti')
            ->assertDontSee('Buka materi kelas')
            ->assertDontSee('https://drive.google.com/rahasia', false);
    }

    public function test_member_sees_assignment_with_score_and_feedback(): void
    {
        [$user, $person] = $this->member();
        $program = Program::factory()->active()->create(['min_average_score' => 75]);
        $cohort = Cohort::factory()->create(['program_id' => $program->id]);
        $session = CohortSession::factory()->for($cohort)->create(['title' => 'Kelas 1: Riset']);
        $assignment = Assignment::factory()->for($session, 'session')->create([
            'title' => 'Riset 3 Produk',
            'body' => 'Cari 3 produk winning.',
        ]);
        $enrollment = Enrollment::create(['people_id' => $person->id, 'cohort_id' => $cohort->id]);
        StatusEvent::create(['enrollment_id' => $enrollment->id, 'status' => 'accepted', 'occurred_at' => now()]);
        Attendance::create(['cohort_session_id' => $session->id, 'enrollment_id' => $enrollment->id]);
        AssignmentSubmission::factory()->graded(82)->create([
            'assignment_id' => $assignment->id,
            'enrollment_id' => $enrollment->id,
            'feedback' => 'Bagus, riset kamu tajam.',
        ]);

        $this->actingAs($user)->get('/akun?bagian=tugas')
            ->assertOk()
            ->assertSee('Riset 3 Produk')
            ->assertSee('Cari 3 produk winning.')
            ->assertSee('Bagus, riset kamu tajam.')
            ->assertSee('82')
            ->assertSee('Kirim ulang untuk perbaiki nilai')
            ->assertSee('Rata-ratamu')
            ->assertSee('Kamu memenuhi syarat! Lanjut ke kelas komunitas');
    }

    public function test_member_sees_submit_form_when_not_yet_submitted(): void
    {
        [$user, $person] = $this->member();
        $program = Program::factory()->active()->create();
        $cohort = Cohort::factory()->create(['program_id' => $program->id]);
        $session = CohortSession::factory()->for($cohort)->create();
        Assignment::factory()->for($session, 'session')->create(['title' => 'Tugas Konten']);
        $enrollment = Enrollment::create(['people_id' => $person->id, 'cohort_id' => $cohort->id]);
        StatusEvent::create(['enrollment_id' => $enrollment->id, 'status' => 'accepted', 'occurred_at' => now()]);
        Attendance::create(['cohort_session_id' => $session->id, 'enrollment_id' => $enrollment->id]);

        $this->actingAs($user)->get('/akun?bagian=tugas')
            ->assertOk()
            ->assertSee('Tugas Konten')
            ->assertSee('Kirim jawaban')
            // No threshold on this program -> no progress card.
            ->assertDontSee('Rata-ratamu');
    }

    public function test_assignment_stays_locked_until_marked_hadir(): void
    {
        [$user, $person] = $this->member();
        $program = Program::factory()->active()->create();
        $cohort = Cohort::factory()->create(['program_id' => $program->id]);
        $session = CohortSession::factory()->for($cohort)->create();
        Assignment::factory()->for($session, 'session')->create([
            'title' => 'Tugas Terkunci',
            'body' => 'Isi soal rahasia sebelum hadir.',
        ]);
        $enrollment = Enrollment::create(['people_id' => $person->id, 'cohort_id' => $cohort->id]);
        StatusEvent::create(['enrollment_id' => $enrollment->id, 'status' => 'accepted', 'occurred_at' => now()]);

        $this->actingAs($user)->get('/akun?bagian=tugas')
            ->assertOk()
            ->assertSee('Tugas Terkunci')
            ->assertSee('Soal dan pengiriman tugas terbuka setelah kehadiranmu ditandai mentor')
            ->assertDontSee('Isi soal rahasia sebelum hadir.')
            ->assertDontSee('Kirim jawaban');
    }

    public function test_waiting_state_shows_confirmation_and_fix_link(): void
    {
        [$user, $person] = $this->member();
        $program = Program::factory()->active()->create(['min_average_score' => 75]);
        $cohort = Cohort::factory()->create(['program_id' => $program->id]);
        $session = CohortSession::factory()->for($cohort)->create();
        $assignment = Assignment::factory()->for($session, 'session')->create();
        $enrollment = Enrollment::create(['people_id' => $person->id, 'cohort_id' => $cohort->id]);
        StatusEvent::create(['enrollment_id' => $enrollment->id, 'status' => 'accepted', 'occurred_at' => now()]);
        AssignmentSubmission::factory()->create([
            'assignment_id' => $assignment->id,
            'enrollment_id' => $enrollment->id,
        ]);

        $this->actingAs($user)->get('/akun?bagian=tugas')
            ->assertOk()
            ->assertSee('Jawabanmu sudah terkirim, menunggu dinilai mentor.')
            ->assertSee('Edit kiriman')
            ->assertDontSee('Perbaiki kiriman')
            ->assertSee('Capai rata-rata 75 untuk membuka kelas komunitas');
    }

    public function test_rich_soal_renders_formatted_and_legacy_plain_text_keeps_breaks(): void
    {
        [$user, $person] = $this->member();
        $program = Program::factory()->active()->create();
        $cohort = Cohort::factory()->create(['program_id' => $program->id]);

        $richSession = CohortSession::factory()->for($cohort)->create(['title' => 'Kelas Rich']);
        Assignment::factory()->for($richSession, 'session')->create([
            'title' => 'Tugas Rich',
            'body' => '<p>Langkah <strong>satu</strong></p><ul><li>Poin A</li></ul>',
        ]);

        $legacySession = CohortSession::factory()->for($cohort)->create(['title' => 'Kelas Legacy']);
        Assignment::factory()->for($legacySession, 'session')->create([
            'title' => 'Tugas Legacy',
            'body' => "Baris satu\nBaris dua",
        ]);

        $enrollment = Enrollment::create(['people_id' => $person->id, 'cohort_id' => $cohort->id]);
        StatusEvent::create(['enrollment_id' => $enrollment->id, 'status' => 'accepted', 'occurred_at' => now()]);
        Attendance::create(['cohort_session_id' => $richSession->id, 'enrollment_id' => $enrollment->id]);
        Attendance::create(['cohort_session_id' => $legacySession->id, 'enrollment_id' => $enrollment->id]);

        $this->actingAs($user)->get('/akun?bagian=tugas')
            ->assertOk()
            ->assertSee('<strong>satu</strong>', false)
            ->assertSee('<li>Poin A</li>', false)
            ->assertSee('Baris satu<br', false);
    }

    public function test_waiting_submission_locks_after_the_edit_window(): void
    {
        [$user, $person] = $this->member();
        $program = Program::factory()->active()->create(['min_average_score' => 75]);
        $cohort = Cohort::factory()->create(['program_id' => $program->id]);
        $session = CohortSession::factory()->for($cohort)->create();
        $assignment = Assignment::factory()->for($session, 'session')->create();
        $enrollment = Enrollment::create(['people_id' => $person->id, 'cohort_id' => $cohort->id]);
        StatusEvent::create(['enrollment_id' => $enrollment->id, 'status' => 'accepted', 'occurred_at' => now()]);
        AssignmentSubmission::factory()->create([
            'assignment_id' => $assignment->id,
            'enrollment_id' => $enrollment->id,
            'created_at' => now()->subHours(7),
        ]);

        $this->actingAs($user)->get('/akun?bagian=tugas')
            ->assertOk()
            ->assertSee('Kirimanmu terkunci dan masuk antrean penilaian mentor.')
            ->assertDontSee('Edit kiriman');
    }

    public function test_upcoming_class_shows_confirmation_prompt(): void
    {
        [$user, $person] = $this->member();
        $program = Program::factory()->active()->create();
        $cohort = Cohort::factory()->create(['program_id' => $program->id]);
        CohortSession::factory()->for($cohort)->create(['scheduled_at' => now()->addDays(3)]);
        $enrollment = Enrollment::create(['people_id' => $person->id, 'cohort_id' => $cohort->id]);
        StatusEvent::create(['enrollment_id' => $enrollment->id, 'status' => 'accepted', 'occurred_at' => now()]);

        $this->actingAs($user)->get('/akun?bagian=kelas')
            ->assertOk()
            ->assertSee('Bisa hadir di kelas ini?')
            ->assertSee('Bisa hadir')
            ->assertSee('Konfirmasi hadir?')
            ->assertSee('Berhalangan');
    }

    public function test_confirmed_class_shows_choice_as_changeable_chip(): void
    {
        [$user, $person] = $this->member();
        $program = Program::factory()->active()->create();
        $cohort = Cohort::factory()->create(['program_id' => $program->id]);
        $session = CohortSession::factory()->for($cohort)->create(['scheduled_at' => now()->addDays(3)]);
        $enrollment = Enrollment::create(['people_id' => $person->id, 'cohort_id' => $cohort->id]);
        StatusEvent::create(['enrollment_id' => $enrollment->id, 'status' => 'accepted', 'occurred_at' => now()]);
        SessionConfirmation::factory()->cannotAttend('Ada acara keluarga.')->create([
            'cohort_session_id' => $session->id,
            'enrollment_id' => $enrollment->id,
        ]);

        $this->actingAs($user)->get('/akun?bagian=kelas')
            ->assertOk()
            ->assertSee('Kamu berhalangan hadir')
            ->assertSee('Ada acara keluarga.')
            ->assertSee('Ubah konfirmasi')
            ->assertDontSee('Bisa hadir di kelas ini?');
    }

    public function test_started_class_shows_no_confirmation_prompt(): void
    {
        [$user, $person] = $this->member();
        $program = Program::factory()->active()->create();
        $cohort = Cohort::factory()->create(['program_id' => $program->id]);
        CohortSession::factory()->for($cohort)->create(['scheduled_at' => now()->subHours(3)]);
        $enrollment = Enrollment::create(['people_id' => $person->id, 'cohort_id' => $cohort->id]);
        StatusEvent::create(['enrollment_id' => $enrollment->id, 'status' => 'accepted', 'occurred_at' => now()]);

        $this->actingAs($user)->get('/akun?bagian=kelas')
            ->assertOk()
            ->assertDontSee('Bisa hadir di kelas ini?')
            ->assertDontSee('Konfirmasi hadir?');
    }

    public function test_prompt_hidden_once_marked_hadir(): void
    {
        [$user, $person] = $this->member();
        $program = Program::factory()->active()->create();
        $cohort = Cohort::factory()->create(['program_id' => $program->id]);
        $session = CohortSession::factory()->for($cohort)->create(['scheduled_at' => now()->addDays(3)]);
        $enrollment = Enrollment::create(['people_id' => $person->id, 'cohort_id' => $cohort->id]);
        StatusEvent::create(['enrollment_id' => $enrollment->id, 'status' => 'accepted', 'occurred_at' => now()]);
        Attendance::create(['cohort_session_id' => $session->id, 'enrollment_id' => $enrollment->id]);

        $this->actingAs($user)->get('/akun?bagian=kelas')
            ->assertOk()
            ->assertDontSee('Bisa hadir di kelas ini?');
    }

    public function test_offline_class_shows_benefit_block(): void
    {
        [$user, $person] = $this->member();
        $program = Program::factory()->active()->create();
        $cohort = Cohort::factory()->create(['program_id' => $program->id]);
        CohortSession::factory()->for($cohort)->create([
            'scheduled_at' => now()->addDays(3),
            'type' => 'offline',
            'location_name' => 'Kantor Kheedma',
        ]);
        $enrollment = Enrollment::create(['people_id' => $person->id, 'cohort_id' => $cohort->id]);
        StatusEvent::create(['enrollment_id' => $enrollment->id, 'status' => 'accepted', 'occurred_at' => now()]);

        $this->actingAs($user)->get('/akun?bagian=kelas')
            ->assertOk()
            ->assertSee('Kenapa hadir offline?')
            ->assertSee('Pemantauan langsung dari mentor')
            ->assertSee('Review editing videomu oleh mentor')
            ->assertSee('Praktik posting sosmed langsung di kelas');
    }

    public function test_online_class_shows_no_offline_benefits(): void
    {
        [$user, $person] = $this->member();
        $program = Program::factory()->active()->create();
        $cohort = Cohort::factory()->create(['program_id' => $program->id]);
        CohortSession::factory()->for($cohort)->create([
            'scheduled_at' => now()->addDays(3),
            'type' => 'online',
        ]);
        $enrollment = Enrollment::create(['people_id' => $person->id, 'cohort_id' => $cohort->id]);
        StatusEvent::create(['enrollment_id' => $enrollment->id, 'status' => 'accepted', 'occurred_at' => now()]);

        $this->actingAs($user)->get('/akun?bagian=kelas')
            ->assertOk()
            ->assertDontSee('Kenapa hadir offline?');
    }
}
