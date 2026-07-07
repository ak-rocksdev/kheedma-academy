<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\Cohort;
use App\Models\CommunityMembership;
use App\Models\Enrollment;
use App\Models\Person;
use App\Models\StatusEvent;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PersonMergeTest extends TestCase
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

    private function person(string $name, string $phone, array $extra = []): Person
    {
        return Person::create([
            'name' => $name, 'phone' => $phone, 'email' => str_replace('+', '', $phone).'@example.test',
            ...$extra,
        ]);
    }

    private function participant(Person $person): User
    {
        $user = User::factory()->create();
        $user->assignRole('participant');
        $person->user_id = $user->id;
        $person->save();

        return $user;
    }

    private function merge(Person $survivor, Person $duplicate)
    {
        return $this->actingAs($this->admin())->postJson('/api/admin/people/merge', [
            'survivor_id' => $survivor->id,
            'duplicate_id' => $duplicate->id,
        ]);
    }

    public function test_merge_repoints_history_and_tombstones_duplicate(): void
    {
        $survivor = $this->person('Ahmad Fauzi', '+628111111100');
        $duplicate = $this->person('Ahmad F.', '+628222222200');
        Application::create(['people_id' => $duplicate->id]);
        Application::create(['people_id' => $duplicate->id]);
        Enrollment::create(['people_id' => $duplicate->id, 'cohort_id' => Cohort::factory()->create()->id]);
        CommunityMembership::create(['people_id' => $duplicate->id]);

        $this->merge($survivor, $duplicate)
            ->assertOk()
            ->assertJsonPath('moves.applications', 2)
            ->assertJsonPath('moves.enrollments', 1)
            ->assertJsonPath('moves.membership', true)
            ->assertJsonPath('moves.account', false);

        $this->assertSame(2, $survivor->applications()->count());
        $this->assertSame(1, $survivor->enrollments()->count());
        $this->assertNotNull($survivor->fresh()->communityMembership);

        $tombstone = Person::withTrashed()->find($duplicate->id);
        $this->assertTrue($tombstone->trashed());
        $this->assertSame("merged:{$duplicate->id}:+628222222200", $tombstone->phone);
        $this->assertSame("merged:{$duplicate->id}:628222222200@example.test", $tombstone->email);
        $this->assertSame($survivor->id, $tombstone->merged_into_id);
        $this->assertTrue($tombstone->mergedInto->is($survivor));
        $this->assertTrue($survivor->mergedFrom()->first()->is($tombstone));
    }

    public function test_merge_transfers_account_from_duplicate(): void
    {
        $survivor = $this->person('Ahmad Fauzi', '+628111111100');
        $duplicate = $this->person('Ahmad F.', '+628222222200');
        $user = $this->participant($duplicate);

        $this->merge($survivor, $duplicate)
            ->assertOk()
            ->assertJsonPath('moves.account', true);

        $this->assertSame($user->id, $survivor->fresh()->user_id);
        $this->assertNull(Person::withTrashed()->find($duplicate->id)->user_id);
    }

    public function test_merge_backfills_missing_profile_fields_without_overwriting(): void
    {
        $survivor = $this->person('Ahmad Fauzi', '+628111111100', ['tiktok_username' => null, 'instagram_username' => 'ahmad.asli']);
        $duplicate = $this->person('Ahmad F.', '+628222222200', ['tiktok_username' => 'ahmadtok', 'instagram_username' => 'ahmad.dobel']);

        $this->merge($survivor, $duplicate)->assertOk();

        $survivor->refresh();
        $this->assertSame('ahmadtok', $survivor->tiktok_username);
        $this->assertSame('ahmad.asli', $survivor->instagram_username);
    }

    public function test_merge_blocked_when_both_have_accounts(): void
    {
        $survivor = $this->person('Ahmad Fauzi', '+628111111100');
        $duplicate = $this->person('Ahmad F.', '+628222222200');
        $this->participant($survivor);
        $this->participant($duplicate);

        $this->merge($survivor, $duplicate)
            ->assertStatus(422)
            ->assertJsonValidationErrors('merge');

        $this->assertFalse(Person::withTrashed()->find($duplicate->id)->trashed());
    }

    public function test_merge_blocked_when_both_have_memberships(): void
    {
        $survivor = $this->person('Ahmad Fauzi', '+628111111100');
        $duplicate = $this->person('Ahmad F.', '+628222222200');
        CommunityMembership::create(['people_id' => $survivor->id]);
        CommunityMembership::create(['people_id' => $duplicate->id]);

        $this->merge($survivor, $duplicate)->assertStatus(422)->assertJsonValidationErrors('merge');
    }

    public function test_merge_blocked_on_shared_cohort_and_status_events_survive(): void
    {
        $survivor = $this->person('Ahmad Fauzi', '+628111111100');
        $duplicate = $this->person('Ahmad F.', '+628222222200');
        $cohort = Cohort::factory()->create();
        Enrollment::create(['people_id' => $survivor->id, 'cohort_id' => $cohort->id]);
        $enrollment = Enrollment::create(['people_id' => $duplicate->id, 'cohort_id' => $cohort->id]);
        StatusEvent::create(['enrollment_id' => $enrollment->id, 'status' => 'accepted', 'occurred_at' => now()]);

        $this->merge($survivor, $duplicate)->assertStatus(422)->assertJsonValidationErrors('merge');

        $this->assertSame(1, StatusEvent::count());
        $this->assertSame($duplicate->id, $enrollment->fresh()->people_id);
    }

    public function test_all_conflicts_are_reported_together(): void
    {
        $survivor = $this->person('Ahmad Fauzi', '+628111111100');
        $duplicate = $this->person('Ahmad F.', '+628222222200');
        $this->participant($survivor);
        $this->participant($duplicate);
        CommunityMembership::create(['people_id' => $survivor->id]);
        CommunityMembership::create(['people_id' => $duplicate->id]);
        $cohort = Cohort::factory()->create();
        Enrollment::create(['people_id' => $survivor->id, 'cohort_id' => $cohort->id]);
        Enrollment::create(['people_id' => $duplicate->id, 'cohort_id' => $cohort->id]);

        $response = $this->merge($survivor, $duplicate)->assertStatus(422);

        $this->assertCount(3, $response->json('errors.merge'));
    }

    public function test_non_overlapping_enrollments_are_repointed(): void
    {
        $survivor = $this->person('Ahmad Fauzi', '+628111111100');
        $duplicate = $this->person('Ahmad F.', '+628222222200');
        Enrollment::create(['people_id' => $survivor->id, 'cohort_id' => Cohort::factory()->create()->id]);
        $moved = Enrollment::create(['people_id' => $duplicate->id, 'cohort_id' => Cohort::factory()->create()->id]);
        StatusEvent::create(['enrollment_id' => $moved->id, 'status' => 'accepted', 'occurred_at' => now()]);

        $this->merge($survivor, $duplicate)->assertOk();

        $this->assertSame(2, $survivor->enrollments()->count());
        $this->assertSame($survivor->id, $moved->fresh()->people_id);
        $this->assertSame(1, $moved->statusEvents()->count());
    }

    public function test_self_merge_is_rejected(): void
    {
        $person = $this->person('Ahmad Fauzi', '+628111111100');

        $this->merge($person, $person)->assertStatus(422)->assertJsonValidationErrors('duplicate_id');
    }

    public function test_already_merged_person_cannot_be_merged_again(): void
    {
        $survivor = $this->person('Ahmad Fauzi', '+628111111100');
        $duplicate = $this->person('Ahmad F.', '+628222222200');
        $this->merge($survivor, $duplicate)->assertOk();

        $this->merge($survivor, $duplicate)->assertStatus(422)->assertJsonValidationErrors('duplicate_id');
    }

    public function test_freed_phone_and_email_can_rejoin_after_merge(): void
    {
        $survivor = $this->person('Ahmad Fauzi', '+628111111100');
        $duplicate = $this->person('Ahmad F.', '+6281298765432', ['email' => 'siti@example.test']);
        $this->merge($survivor, $duplicate)->assertOk();

        // The admin API request above pinned the sanctum guard as default;
        // the public form runs on the web guard.
        auth()->shouldUse('web');
        auth('web')->logout();

        // The regression this package fixes: before tombstoning, the freed
        // phone/email passed validation but crashed on the DB unique index.
        $this->post('/komunitas', [
            'name' => 'Siti Aminah',
            'phone' => '081298765432',
            'email' => 'siti@example.test',
            'password' => 'rahasia-kuat',
            'birth_date' => '2000-01-15',
            'motivation' => 'Ingin serius belajar affiliate.',
            'referral_source' => 'tiktok',
        ])->assertRedirect('/akun');

        $this->assertSame('+6281298765432', Person::where('email', 'siti@example.test')->sole()->phone);
    }

    public function test_preview_reports_moves_and_conflicts_without_changing_data(): void
    {
        $survivor = $this->person('Ahmad Fauzi', '+628111111100');
        $duplicate = $this->person('Ahmad F.', '+628222222200');
        Application::create(['people_id' => $duplicate->id]);
        $admin = $this->admin();

        $this->actingAs($admin)
            ->getJson("/api/admin/people/merge-preview?survivor_id={$survivor->id}&duplicate_id={$duplicate->id}")
            ->assertOk()
            ->assertJsonPath('can_merge', true)
            ->assertJsonPath('conflicts', [])
            ->assertJsonPath('moves.applications', 1);

        $this->participant($survivor);
        $this->participant($duplicate);

        $this->actingAs($admin)
            ->getJson("/api/admin/people/merge-preview?survivor_id={$survivor->id}&duplicate_id={$duplicate->id}")
            ->assertOk()
            ->assertJsonPath('can_merge', false)
            ->assertJsonCount(1, 'conflicts');

        $this->assertFalse(Person::withTrashed()->find($duplicate->id)->trashed());
        $this->assertSame($duplicate->id, Application::sole()->people_id);
    }

    public function test_merge_requires_the_people_merge_permission(): void
    {
        $survivor = $this->person('Ahmad Fauzi', '+628111111100');
        $duplicate = $this->person('Ahmad F.', '+628222222200');
        $mentor = User::factory()->mentor()->create();

        $this->actingAs($mentor)
            ->getJson("/api/admin/people/merge-preview?survivor_id={$survivor->id}&duplicate_id={$duplicate->id}")
            ->assertForbidden();

        $this->actingAs($mentor)->postJson('/api/admin/people/merge', [
            'survivor_id' => $survivor->id,
            'duplicate_id' => $duplicate->id,
        ])->assertForbidden();
    }
}
