<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\Cohort;
use App\Models\CommunityMembership;
use App\Models\Enrollment;
use App\Models\Person;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PersonDirectoryTest extends TestCase
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

    private function person(string $name, string $phone): Person
    {
        return Person::create([
            'name' => $name, 'phone' => $phone, 'email' => str_replace('+', '', $phone).'@example.test',
        ]);
    }

    public function test_guest_and_unpermitted_user_cannot_list_people(): void
    {
        $this->getJson('/api/admin/people')->assertUnauthorized();

        $participant = User::factory()->create();
        $participant->assignRole('participant');
        $this->actingAs($participant)->getJson('/api/admin/people')->assertForbidden();
    }

    public function test_mentor_with_people_view_can_list_people(): void
    {
        $this->person('Ahmad Fauzi', '+628111111100');

        $this->actingAs(User::factory()->mentor()->create())
            ->getJson('/api/admin/people')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Ahmad Fauzi');
    }

    public function test_search_matches_name_phone_or_email(): void
    {
        $this->person('Ahmad Fauzi', '+628111111100');
        $this->person('Budi Santoso', '+628222222200');

        $admin = $this->admin();

        $this->actingAs($admin)->getJson('/api/admin/people?q=Ahmad')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Ahmad Fauzi');
        $this->actingAs($admin)->getJson('/api/admin/people?q=%2B628222222200')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Budi Santoso');
        $this->actingAs($admin)->getJson('/api/admin/people?q=628111111100@example.test')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Ahmad Fauzi');
    }

    public function test_segment_pendaftar_returns_only_people_with_applications(): void
    {
        $applicant = $this->person('Ahmad Fauzi', '+628111111100');
        Application::create(['people_id' => $applicant->id]);
        $this->person('Budi Santoso', '+628222222200');

        $this->actingAs($this->admin())
            ->getJson('/api/admin/people?segment=pendaftar')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $applicant->id)
            ->assertJsonPath('data.0.applications_count', 1);
    }

    public function test_segment_komunitas_returns_only_community_members(): void
    {
        $member = $this->person('Ahmad Fauzi', '+628111111100');
        CommunityMembership::create(['people_id' => $member->id]);
        $this->person('Budi Santoso', '+628222222200');

        $this->actingAs($this->admin())
            ->getJson('/api/admin/people?segment=komunitas')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $member->id)
            ->assertJsonPath('data.0.is_community_member', true);
    }

    public function test_segment_peserta_returns_only_enrolled_people(): void
    {
        $enrolled = $this->person('Ahmad Fauzi', '+628111111100');
        Enrollment::create(['people_id' => $enrolled->id, 'cohort_id' => Cohort::factory()->create()->id]);
        $this->person('Budi Santoso', '+628222222200');

        $this->actingAs($this->admin())
            ->getJson('/api/admin/people?segment=peserta')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $enrolled->id)
            ->assertJsonPath('data.0.enrollments_count', 1);
    }

    public function test_segment_berakun_returns_only_people_with_accounts(): void
    {
        $withAccount = $this->person('Ahmad Fauzi', '+628111111100');
        $user = User::factory()->create();
        $user->assignRole('participant');
        $withAccount->user_id = $user->id;
        $withAccount->save();
        $this->person('Budi Santoso', '+628222222200');

        $this->actingAs($this->admin())
            ->getJson('/api/admin/people?segment=berakun')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $withAccount->id)
            ->assertJsonPath('data.0.has_account', true);
    }

    public function test_soft_deleted_people_are_excluded(): void
    {
        $this->person('Ahmad Fauzi', '+628111111100');
        $this->person('Budi Santoso', '+628222222200')->delete();

        $this->actingAs($this->admin())
            ->getJson('/api/admin/people')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Ahmad Fauzi');
    }

    public function test_list_is_paginated_newest_first(): void
    {
        foreach (range(1, 16) as $i) {
            $person = $this->person("Orang {$i}", sprintf('+62811%08d', $i));
            $person->created_at = now()->subMinutes(17 - $i);
            $person->save();
        }

        $this->actingAs($this->admin())
            ->getJson('/api/admin/people')
            ->assertOk()
            ->assertJsonCount(15, 'data')
            ->assertJsonPath('total', 16)
            ->assertJsonPath('last_page', 2)
            ->assertJsonPath('data.0.name', 'Orang 16');
    }

    public function test_invalid_segment_is_rejected(): void
    {
        $this->actingAs($this->admin())
            ->getJson('/api/admin/people?segment=bogus')
            ->assertStatus(422);
    }
}
