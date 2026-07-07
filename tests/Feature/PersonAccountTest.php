<?php

namespace Tests\Feature;

use App\Models\Person;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PersonAccountTest extends TestCase
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

    private function person(string $name = 'Ahmad Fauzi', string $phone = '+628111111100'): Person
    {
        return Person::create([
            'name' => $name, 'phone' => $phone, 'email' => str_replace('+', '', $phone).'@example.test',
        ]);
    }

    private function withParticipantAccount(Person $person): User
    {
        $user = User::factory()->create();
        $user->assignRole('participant');
        $person->user_id = $user->id;
        $person->save();

        return $user;
    }

    public function test_admin_can_deactivate_and_reactivate_a_participant_account(): void
    {
        $person = $this->person();
        $user = $this->withParticipantAccount($person);
        $admin = $this->admin();

        $this->actingAs($admin)
            ->patchJson("/api/admin/people/{$person->id}/account", ['is_active' => false])
            ->assertOk()
            ->assertJsonPath('account.is_active', false);
        $this->assertFalse($user->fresh()->is_active);

        $this->actingAs($admin)
            ->patchJson("/api/admin/people/{$person->id}/account", ['is_active' => true])
            ->assertOk()
            ->assertJsonPath('account.is_active', true);
        $this->assertTrue($user->fresh()->is_active);
    }

    public function test_admin_can_reset_password_with_a_generated_one(): void
    {
        $person = $this->person();
        $user = $this->withParticipantAccount($person);

        $response = $this->actingAs($this->admin())
            ->patchJson("/api/admin/people/{$person->id}/account", ['reset_password' => true])
            ->assertOk()
            ->assertJsonPath('generated_password', fn ($p) => is_string($p) && strlen($p) >= 8);

        $this->assertTrue(Hash::check($response->json('generated_password'), $user->fresh()->password));
    }

    public function test_admin_can_reset_password_with_a_manual_one(): void
    {
        $person = $this->person();
        $user = $this->withParticipantAccount($person);

        $this->actingAs($this->admin())
            ->patchJson("/api/admin/people/{$person->id}/account", [
                'reset_password' => true,
                'password' => 'sandi-baru-aman',
            ])
            ->assertOk()
            ->assertJsonPath('generated_password', null);

        $this->assertTrue(Hash::check('sandi-baru-aman', $user->fresh()->password));
    }

    public function test_short_manual_password_is_rejected(): void
    {
        $person = $this->person();
        $this->withParticipantAccount($person);

        $this->actingAs($this->admin())
            ->patchJson("/api/admin/people/{$person->id}/account", [
                'reset_password' => true,
                'password' => 'pendek',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('password');
    }

    public function test_person_without_account_is_rejected(): void
    {
        $person = $this->person();

        $this->actingAs($this->admin())
            ->patchJson("/api/admin/people/{$person->id}/account", ['is_active' => false])
            ->assertStatus(422)
            ->assertJsonValidationErrors('account');
    }

    public function test_staff_linked_account_is_unreachable_via_this_path(): void
    {
        $person = $this->person();
        $staff = User::factory()->mentor()->create();
        $person->user_id = $staff->id;
        $person->save();

        $this->actingAs($this->admin())
            ->patchJson("/api/admin/people/{$person->id}/account", ['is_active' => false])
            ->assertStatus(422)
            ->assertJsonValidationErrors('account');

        $this->assertTrue($staff->fresh()->is_active);
    }

    public function test_account_actions_require_users_manage_permission(): void
    {
        $person = $this->person();
        $this->withParticipantAccount($person);

        $this->actingAs(User::factory()->mentor()->create())
            ->patchJson("/api/admin/people/{$person->id}/account", ['is_active' => false])
            ->assertForbidden();
    }

    public function test_person_show_includes_the_account_block(): void
    {
        $person = $this->person();
        $admin = $this->admin();

        $this->actingAs($admin)
            ->getJson("/api/admin/people/{$person->id}")
            ->assertOk()
            ->assertJsonPath('person.account', null);

        $user = $this->withParticipantAccount($person);

        $this->actingAs($admin)
            ->getJson("/api/admin/people/{$person->id}")
            ->assertOk()
            ->assertJsonPath('person.account.is_active', true)
            ->assertJsonPath('person.account.email', $user->email);
    }
}
