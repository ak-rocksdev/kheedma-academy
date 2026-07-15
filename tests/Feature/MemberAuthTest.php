<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\Person;
use App\Models\Program;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MemberAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
    }

    private function participant(array $overrides = []): User
    {
        $user = User::factory()->create([...$overrides, 'password' => Hash::make('rahasia-kuat')]);
        $user->assignRole('participant');
        $person = Person::create([
            'name' => $user->name, 'phone' => '+62812'.random_int(10000000, 99999999),
            'email' => $user->email,
        ]);
        $person->user_id = $user->id;
        $person->save();

        return $user;
    }

    public function test_participant_can_login_and_reach_akun(): void
    {
        $user = $this->participant();

        $this->post('/masuk', ['email' => $user->email, 'password' => 'rahasia-kuat'])
            ->assertRedirect('/akun');

        $user->refresh();
        $this->actingAs($user)->get('/akun')->assertOk()->assertSee($user->name);
        $this->actingAs($user)->get('/akun?bagian=profil')->assertOk()->assertSee($user->person->phone);
    }

    public function test_wrong_password_is_rejected(): void
    {
        $user = $this->participant();

        $this->from('/masuk')
            ->post('/masuk', ['email' => $user->email, 'password' => 'salah-total'])
            ->assertRedirect('/masuk')
            ->assertSessionHasErrors('email');
    }

    public function test_deactivated_participant_cannot_login(): void
    {
        $user = $this->participant(['is_active' => false]);

        $this->from('/masuk')
            ->post('/masuk', ['email' => $user->email, 'password' => 'rahasia-kuat'])
            ->assertRedirect('/masuk')
            ->assertSessionHasErrors('email');
    }

    public function test_staff_is_redirected_to_admin_panel(): void
    {
        $admin = User::factory()->admin()->create(['password' => Hash::make('rahasia-kuat')]);

        $this->post('/masuk', ['email' => $admin->email, 'password' => 'rahasia-kuat'])
            ->assertRedirect('/admin');

        $this->actingAs($admin)->get('/akun')->assertRedirect('/admin');
    }

    public function test_guest_is_redirected_to_member_login(): void
    {
        $this->get('/akun')->assertRedirect('/masuk');
    }

    public function test_logout_ends_the_session(): void
    {
        $user = $this->participant();

        $this->actingAs($user)->post('/keluar')->assertRedirect('/');
        $this->assertGuest();
    }

    public function test_participant_cannot_open_the_admin_panel_shell(): void
    {
        $user = $this->participant();

        $this->actingAs($user)->get('/admin')->assertRedirect('/akun');
        $this->actingAs($user)->get('/admin/programs')->assertRedirect('/akun');
    }

    public function test_guest_and_staff_still_reach_the_admin_shell(): void
    {
        $this->get('/admin')->assertOk();

        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->get('/admin')->assertOk();
    }

    public function test_nav_account_menu_matches_the_role(): void
    {
        $this->get('/')->assertSee('Masuk')->assertDontSee('Panel Admin');

        $participant = $this->participant();
        $this->actingAs($participant)->get('/')->assertSee('Akun Saya')->assertDontSee('Panel Admin');

        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->get('/')->assertSee('Panel Admin')->assertSee('Keluar');
    }

    public function test_authenticated_participant_visiting_login_is_redirected(): void
    {
        $user = $this->participant();

        $this->actingAs($user)->get('/masuk')->assertRedirect('/akun');
    }

    public function test_deactivated_mid_session_is_logged_out_of_akun(): void
    {
        $user = $this->participant();
        $user->update(['is_active' => false]);

        $this->actingAs($user)->get('/akun')->assertRedirect('/masuk');
        $this->assertGuest();
    }

    public function test_akun_lists_application_statuses(): void
    {
        $user = $this->participant();
        $program = Program::factory()->active()->create(['name' => 'Program Status']);
        Application::create([
            'people_id' => $user->person->id, 'status' => 'pending',
            'program_id' => $program->id, 'referral_source' => 'teman',
        ]);

        $this->actingAs($user)->get('/akun')
            ->assertOk()
            ->assertSee('Program Status')
            ->assertSee('Menunggu');
    }
}
