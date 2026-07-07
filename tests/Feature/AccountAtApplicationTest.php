<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\Cohort;
use App\Models\CommunityMembership;
use App\Models\Person;
use App\Models\Program;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AccountAtApplicationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        DB::table('indonesia_provinces')->insert([
            'code' => '32', 'name' => 'JAWA BARAT', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('indonesia_cities')->insert([
            'code' => '3273', 'province_code' => '32', 'name' => 'KOTA BANDUNG', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function openProgram(): Program
    {
        $program = Program::factory()->active()->create();
        Cohort::factory()->openWindow()->create(['program_id' => $program->id]);

        return $program;
    }

    /** @return array<string, string> */
    private function guestPayload(): array
    {
        return [
            'name' => 'Budi Santoso',
            'phone' => '081234567890',
            'email' => 'budi@example.test',
            'password' => 'rahasia-kuat',
            'province_code' => '32',
            'city_code' => '3273',
            'referral_source' => 'instagram',
        ];
    }

    public function test_guest_application_creates_account_and_logs_in(): void
    {
        $program = $this->openProgram();

        $this->post("/program/{$program->slug}/daftar", $this->guestPayload())
            ->assertRedirect(route('daftar.thankyou'));

        $person = Person::sole();
        $this->assertNotNull($person->user_id);
        $this->assertTrue($person->user->hasRole('participant'));
        $this->assertSame(1, Application::count());
        $this->assertTrue(Auth::check());
        $this->assertSame(0, CommunityMembership::count());
    }

    public function test_guest_password_is_required(): void
    {
        $program = $this->openProgram();

        $this->from("/program/{$program->slug}/daftar")
            ->post("/program/{$program->slug}/daftar", [...$this->guestPayload(), 'password' => ''])
            ->assertSessionHasErrors('password');
    }

    public function test_guest_with_account_carrying_phone_is_told_to_login(): void
    {
        $program = $this->openProgram();
        $this->post("/program/{$program->slug}/daftar", $this->guestPayload());
        Auth::logout();

        $this->from("/program/{$program->slug}/daftar")
            ->post("/program/{$program->slug}/daftar", [...$this->guestPayload(), 'email' => 'lain@example.test'])
            ->assertSessionHasErrors('phone');

        $this->assertSame(1, User::role('participant')->count());
        $this->assertSame(1, Application::count());
    }

    public function test_authenticated_participant_applies_without_password_and_updates_identity(): void
    {
        $program = $this->openProgram();
        $this->post("/program/{$program->slug}/daftar", $this->guestPayload());
        $user = Auth::user();

        $second = $this->openProgram();

        $this->actingAs($user)
            ->post("/program/{$second->slug}/daftar", [
                'name' => 'Budi Santoso Baru',
                'phone' => '081234567890',
                'email' => 'budi@example.test',
                'province_code' => '32',
                'city_code' => '3273',
                'referral_source' => 'teman',
            ])
            ->assertRedirect(route('daftar.thankyou'));

        $this->assertSame(2, Application::count());
        $this->assertSame('Budi Santoso Baru', Person::sole()->name);
        $this->assertSame('Budi Santoso Baru', $user->fresh()->name);
    }

    public function test_authenticated_participant_can_change_phone_keeping_email(): void
    {
        $program = $this->openProgram();
        $this->post("/program/{$program->slug}/daftar", $this->guestPayload());
        $user = Auth::user();

        $second = $this->openProgram();

        $this->actingAs($user)
            ->post("/program/{$second->slug}/daftar", [
                'name' => 'Budi Santoso',
                'phone' => '081299998888',
                'email' => 'budi@example.test',
                'province_code' => '32',
                'city_code' => '3273',
                'referral_source' => 'teman',
            ])
            ->assertRedirect(route('daftar.thankyou'));

        $this->assertSame('+6281299998888', Person::sole()->phone);
        $this->assertSame(2, Application::count());
    }

    public function test_authenticated_participant_sees_pending_notice_instead_of_form(): void
    {
        $program = $this->openProgram();
        $this->post("/program/{$program->slug}/daftar", $this->guestPayload());

        $this->get("/program/{$program->slug}/daftar")
            ->assertOk()
            ->assertSee('sudah mendaftar')
            ->assertDontSee('Kirim Pendaftaran');
    }

    public function test_authenticated_resubmit_to_same_program_stays_deduplicated(): void
    {
        $program = $this->openProgram();
        $this->post("/program/{$program->slug}/daftar", $this->guestPayload());

        $this->post("/program/{$program->slug}/daftar", [
            'name' => 'Budi Santoso',
            'phone' => '081234567890',
            'email' => 'budi@example.test',
            'province_code' => '32',
            'city_code' => '3273',
            'referral_source' => 'instagram',
        ])->assertRedirect(route('daftar.thankyou'));

        $this->assertSame(1, Application::count());
    }

    public function test_accepted_applicant_cannot_reapply_and_sees_the_notice(): void
    {
        $program = $this->openProgram();
        $this->post("/program/{$program->slug}/daftar", $this->guestPayload());
        Application::sole()->update(['status' => 'accepted']);

        $this->get("/program/{$program->slug}/daftar")
            ->assertOk()
            ->assertSee('sudah mendaftar')
            ->assertDontSee('Kirim Pendaftaran');

        $this->post("/program/{$program->slug}/daftar", [
            'name' => 'Budi Santoso',
            'phone' => '081234567890',
            'email' => 'budi@example.test',
            'province_code' => '32',
            'city_code' => '3273',
            'referral_source' => 'instagram',
        ])->assertRedirect(route('daftar.thankyou'));

        $this->assertSame(1, Application::count());
    }

    public function test_staff_is_redirected_to_admin(): void
    {
        $program = $this->openProgram();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get("/program/{$program->slug}/daftar")->assertRedirect('/admin');
    }

    public function test_prefilled_form_shows_identity_for_participant(): void
    {
        $program = $this->openProgram();
        $this->post("/program/{$program->slug}/daftar", $this->guestPayload());
        $second = $this->openProgram();

        $this->get("/program/{$second->slug}/daftar")
            ->assertOk()
            ->assertSee('value="Budi Santoso"', false)
            ->assertSee('Masuk sebagai');
    }
}
