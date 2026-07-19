<?php

namespace Tests\Feature;

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
}
