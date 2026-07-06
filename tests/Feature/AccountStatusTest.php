<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AccountStatusTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
    }

    public function test_deactivated_user_cannot_login(): void
    {
        $user = User::factory()->create(['password' => Hash::make('secret123'), 'is_active' => false]);
        $user->assignRole('admin');

        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'secret123'])
            ->assertStatus(422);
    }

    public function test_active_user_can_login(): void
    {
        $user = User::factory()->create(['password' => Hash::make('secret123'), 'is_active' => true]);
        $user->assignRole('admin');

        $this->withHeader('Referer', config('app.url'))
            ->postJson('/api/login', ['email' => $user->email, 'password' => 'secret123'])
            ->assertOk();
    }

    public function test_deactivated_session_is_rejected(): void
    {
        $user = User::factory()->create(['is_active' => false]);
        $user->assignRole('admin');

        $this->actingAs($user)->getJson('/api/me')->assertStatus(401);
    }
}
