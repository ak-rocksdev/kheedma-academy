<?php

namespace Tests\Feature;

use App\Models\CommunityMembership;
use App\Models\Person;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommunityAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
    }

    private function member(string $name, string $phone): CommunityMembership
    {
        $person = Person::create([
            'name' => $name, 'phone' => $phone, 'email' => str_replace('+', '', $phone).'@example.test',
        ]);

        return CommunityMembership::create(['people_id' => $person->id, 'referral_source' => 'teman']);
    }

    public function test_admin_can_list_and_search_members(): void
    {
        $this->member('Ahmad Fauzi', '+628111111100');
        $this->member('Budi Santoso', '+628222222200');

        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->getJson('/api/admin/community-members')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->actingAs($admin)
            ->getJson('/api/admin/community-members?q=Ahmad')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.person.name', 'Ahmad Fauzi')
            ->assertJsonPath('data.0.referral_source', 'teman');
    }

    public function test_mentor_is_forbidden(): void
    {
        $mentor = User::factory()->mentor()->create();

        $this->actingAs($mentor)->getJson('/api/admin/community-members')->assertForbidden();
    }
}
