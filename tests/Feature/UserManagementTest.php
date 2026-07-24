<?php

namespace Tests\Feature;

use App\Models\Person;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserManagementTest extends TestCase
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

    public function test_admin_can_create_a_mentor_with_generated_password(): void
    {
        $this->actingAs($this->admin())
            ->postJson('/api/admin/users', [
                'name' => 'Ustadz Budi',
                'email' => 'budi@kheedma.id',
                'phone' => '0811111111',
                'role' => 'mentor',
            ])
            ->assertCreated()
            ->assertJsonPath('user.role', 'mentor')
            ->assertJsonPath('user.is_active', true)
            ->assertJsonPath('generated_password', fn ($p) => is_string($p) && preg_match('/^\d{6}$/', $p) === 1);

        $this->assertTrue(User::where('email', 'budi@kheedma.id')->first()->hasRole('mentor'));
    }

    public function test_non_admin_is_forbidden(): void
    {
        $mentor = User::factory()->mentor()->create();

        $this->actingAs($mentor)->getJson('/api/admin/users')->assertForbidden();
    }

    public function test_cannot_deactivate_self(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->patchJson("/api/admin/users/{$admin->id}", ['is_active' => false])
            ->assertStatus(422);
    }

    public function test_cannot_remove_last_admin(): void
    {
        $admin = $this->admin();
        $other = User::factory()->admin()->create();

        // Demote the only *other* admin first is allowed; then the acting admin is last.
        $this->actingAs($admin)->deleteJson("/api/admin/users/{$other->id}")->assertNoContent();

        // Now $admin is the last admin; deleting them must be blocked (self-guard also applies).
        $this->actingAs($admin)->deleteJson("/api/admin/users/{$admin->id}")->assertStatus(422);
    }

    public function test_admin_can_deactivate_and_reactivate_another_user(): void
    {
        $admin = $this->admin();
        $mentor = User::factory()->mentor()->create();

        $this->actingAs($admin)
            ->patchJson("/api/admin/users/{$mentor->id}", ['is_active' => false])
            ->assertOk()
            ->assertJsonPath('user.is_active', false);

        $this->actingAs($admin)
            ->patchJson("/api/admin/users/{$mentor->id}", ['is_active' => true])
            ->assertJsonPath('user.is_active', true);
    }

    public function test_promotable_lists_only_participant_accounts(): void
    {
        $participant = User::factory()->participant()->create(['name' => 'Hafiidh']);
        User::factory()->mentor()->create(['name' => 'Mentor Budi']);

        $this->actingAs($this->admin())
            ->getJson('/api/admin/users/promotable')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $participant->id)
            ->assertJsonPath('data.0.email', $participant->email);
    }

    public function test_promotable_filters_by_query(): void
    {
        User::factory()->participant()->create(['name' => 'Hafiidh Ar Rasyiid']);
        User::factory()->participant()->create(['name' => 'Siti Aminah']);

        $this->actingAs($this->admin())
            ->getJson('/api/admin/users/promotable?q=hafi')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Hafiidh Ar Rasyiid');
    }

    public function test_promotable_requires_users_manage_permission(): void
    {
        User::factory()->participant()->create();

        $this->actingAs(User::factory()->mentor()->create())
            ->getJson('/api/admin/users/promotable')
            ->assertForbidden();
    }

    public function test_admin_can_promote_a_participant_to_mentor(): void
    {
        $participant = User::factory()->participant()->create();
        $originalPasswordHash = $participant->password;

        $this->actingAs($this->admin())
            ->postJson("/api/admin/users/{$participant->id}/promote", ['role' => 'mentor'])
            ->assertOk()
            ->assertJsonPath('user.role', 'mentor')
            ->assertJsonPath('user.id', $participant->id);

        $fresh = $participant->fresh();
        $this->assertSame(['mentor'], $fresh->getRoleNames()->all());
        $this->assertSame($originalPasswordHash, $fresh->password);
    }

    public function test_admin_can_promote_a_participant_to_admin(): void
    {
        $participant = User::factory()->participant()->create();

        $this->actingAs($this->admin())
            ->postJson("/api/admin/users/{$participant->id}/promote", ['role' => 'admin'])
            ->assertOk()
            ->assertJsonPath('user.role', 'admin');

        $this->assertSame(['admin'], $participant->fresh()->getRoleNames()->all());
    }

    public function test_cannot_promote_an_account_that_is_already_staff(): void
    {
        $mentor = User::factory()->mentor()->create();

        $this->actingAs($this->admin())
            ->postJson("/api/admin/users/{$mentor->id}/promote", ['role' => 'admin'])
            ->assertStatus(422);

        $this->assertSame(['mentor'], $mentor->fresh()->getRoleNames()->all());
    }

    public function test_promote_rejects_an_invalid_role(): void
    {
        $participant = User::factory()->participant()->create();

        $this->actingAs($this->admin())
            ->postJson("/api/admin/users/{$participant->id}/promote", ['role' => 'participant'])
            ->assertStatus(422);
    }

    public function test_promote_requires_users_manage_permission(): void
    {
        $participant = User::factory()->participant()->create();

        $this->actingAs(User::factory()->mentor()->create())
            ->postJson("/api/admin/users/{$participant->id}/promote", ['role' => 'mentor'])
            ->assertForbidden();
    }

    public function test_promote_endpoints_require_authentication(): void
    {
        $participant = User::factory()->participant()->create();

        $this->getJson('/api/admin/users/promotable')->assertUnauthorized();
        $this->postJson("/api/admin/users/{$participant->id}/promote", ['role' => 'mentor'])->assertUnauthorized();
    }

    public function test_promotable_filters_by_email_and_phone(): void
    {
        User::factory()->participant()->create(['email' => 'hafiidh@contoh.id', 'phone' => '0811222333']);
        User::factory()->participant()->create(['email' => 'siti@contoh.id', 'phone' => '0899888777']);

        $this->actingAs($this->admin())
            ->getJson('/api/admin/users/promotable?q=hafiidh%40contoh')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.email', 'hafiidh@contoh.id');

        $this->actingAs($this->admin())
            ->getJson('/api/admin/users/promotable?q=0899888')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.phone', '0899888777');
    }

    public function test_promote_leaves_the_person_link_untouched(): void
    {
        $participant = User::factory()->participant()->create();
        $person = Person::create([
            'name' => $participant->name,
            'email' => $participant->email,
            'phone' => '0811000111',
        ]);
        $person->user()->associate($participant)->save();

        $this->actingAs($this->admin())
            ->postJson("/api/admin/users/{$participant->id}/promote", ['role' => 'mentor'])
            ->assertOk();

        $this->assertSame($participant->id, $person->fresh()->user_id);
    }
}
