<?php

namespace Tests\Feature;

use App\Models\CommunityMembership;
use App\Models\Person;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CommunityJoinTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
    }

    /** @return array<string, string> */
    private function validPayload(): array
    {
        return [
            'name' => 'Siti Aminah',
            'phone' => '081298765432',
            'email' => 'siti@example.test',
            'password' => '246810',
            'birth_date' => '2000-01-15',
            'gender' => 'male',
            'motivation' => 'Ingin serius belajar affiliate.',
            'referral_source' => 'tiktok',
            'followed_socials' => 1,
        ];
    }

    public function test_join_creates_person_account_membership_and_logs_in(): void
    {
        $this->post('/komunitas', $this->validPayload())
            ->assertRedirect('/akun');

        $person = Person::sole();
        $this->assertSame('+6281298765432', $person->phone);
        $this->assertNotNull($person->user_id);
        $this->assertTrue($person->user->hasRole('participant'));
        $this->assertSame('tiktok', $person->communityMembership->referral_source);
        $this->assertSame('Ingin serius belajar affiliate.', $person->communityMembership->motivation);
        $this->assertSame('male', $person->gender);
        $this->assertTrue($person->followed_socials);
        $this->assertTrue(Auth::check());
        $this->assertTrue(Auth::user()->is($person->user));
    }

    public function test_join_persists_the_affiliate_profile(): void
    {
        $this->post('/komunitas', [
            ...$this->validPayload(),
            'tiktok_username' => 'sitiaminah',
            'tiktok_followers' => 2500,
            'has_started_affiliate' => 1,
            'affiliate_level' => 4,
            'affiliate_gmv_range' => '0-50',
        ])->assertRedirect('/akun');

        $person = Person::sole();
        $this->assertSame('sitiaminah', $person->tiktok_username);
        $this->assertSame(2500, (int) $person->tiktok_followers);
        $this->assertTrue($person->has_started_affiliate);
        $this->assertSame(4, (int) $person->affiliate_level);
        $this->assertSame('0-50', $person->affiliate_gmv_range);
    }

    public function test_join_nulls_affiliate_dependents_without_tiktok(): void
    {
        $this->post('/komunitas', [
            ...$this->validPayload(),
            'tiktok_username' => '',
            'tiktok_followers' => 999,
            'has_started_affiliate' => 1,
            'affiliate_level' => 8,
            'affiliate_gmv_range' => '100+',
        ])->assertRedirect('/akun');

        $person = Person::sole();
        $this->assertNull($person->tiktok_username);
        $this->assertNull($person->tiktok_followers);
        $this->assertNull($person->has_started_affiliate);
        $this->assertNull($person->affiliate_level);
        $this->assertNull($person->affiliate_gmv_range);
    }

    public function test_join_reuses_existing_person_by_phone(): void
    {
        $existing = Person::create([
            'name' => 'Siti Lama', 'phone' => '+6281298765432', 'email' => 'siti.lama@example.test',
        ]);

        $this->post('/komunitas', $this->validPayload())->assertRedirect('/akun');

        $this->assertSame(1, Person::count());
        $this->assertNotNull($existing->fresh()->user_id);
    }

    public function test_phone_that_already_has_an_account_is_rejected(): void
    {
        $this->post('/komunitas', $this->validPayload());
        Auth::logout();

        $this->from('/komunitas')
            ->post('/komunitas', [...$this->validPayload(), 'email' => 'lain@example.test'])
            ->assertRedirect('/komunitas')
            ->assertSessionHasErrors('phone');

        $this->assertSame(1, User::role('participant')->count());
        $this->assertSame(1, CommunityMembership::count());
    }

    public function test_email_already_used_by_another_account_is_rejected(): void
    {
        User::factory()->create(['email' => 'siti@example.test']);

        $this->from('/komunitas')
            ->post('/komunitas', $this->validPayload())
            ->assertRedirect('/komunitas')
            ->assertSessionHasErrors('email');
    }

    public function test_email_owned_by_another_person_without_account_is_rejected(): void
    {
        Person::create([
            'name' => 'Orang Lain', 'phone' => '+628599999999', 'email' => 'siti@example.test',
        ]);

        $this->from('/komunitas')
            ->post('/komunitas', $this->validPayload())
            ->assertRedirect('/komunitas')
            ->assertSessionHasErrors('email');

        $this->assertSame(0, CommunityMembership::count());
    }

    public function test_honeypot_blocks_bots(): void
    {
        $this->from('/komunitas')
            ->post('/komunitas', [...$this->validPayload(), 'website' => 'spam'])
            ->assertRedirect('/komunitas')
            ->assertSessionHasErrors('website');
    }

    public function test_join_page_renders(): void
    {
        $this->get('/komunitas')->assertOk()->assertSee('Komunitas');
    }

    public function test_affiliate_chain_requires_level_and_gmv_when_started(): void
    {
        $this->from('/komunitas')
            ->post('/komunitas', [
                ...$this->validPayload(),
                'tiktok_username' => 'siti.tiktok',
                'tiktok_followers' => 2000,
                'has_started_affiliate' => 1,
            ])
            ->assertSessionHasErrors(['affiliate_level', 'affiliate_gmv_range']);
    }

    /** A logged-in participant with a full intake profile (used by the confirmation tests). */
    private function participantWithProfile(array $personOverrides = []): User
    {
        $user = User::factory()->create(['password' => Hash::make('246810')]);
        $user->assignRole('participant');
        $person = Person::create([...[
            'name' => $user->name,
            'phone' => '+62812'.random_int(10000000, 99999999),
            'email' => $user->email,
            'birth_date' => '2000-01-15',
            'gender' => 'male',
            'followed_socials' => true,
        ], ...$personOverrides]);
        $person->user_id = $user->id;
        $person->save();
        $user->setRelation('person', $person);

        return $user;
    }

    public function test_member_sees_confirmation_instead_of_blank_form(): void
    {
        $this->post('/komunitas', $this->validPayload())->assertRedirect('/akun');

        $this->get('/komunitas')
            ->assertOk()
            ->assertSee('sudah tergabung')
            ->assertDontSee('Gabung Sekarang');
    }

    public function test_member_who_is_not_yet_in_community_confirms_stored_data(): void
    {
        $user = $this->participantWithProfile();
        $person = $user->person;

        $this->actingAs($user)->get('/komunitas')
            ->assertOk()
            ->assertSee('Konfirmasi datamu')
            ->assertSee('Ya, Gabungkan Aku ke Komunitas')
            ->assertDontSee('Kata sandi');

        $this->actingAs($user)->post('/komunitas', [
            'name' => $person->name,
            'phone' => $person->phone,
            'email' => $person->email,
            'birth_date' => $person->birth_date->toDateString(),
            'gender' => $person->gender,
            'followed_socials' => 1,
            'motivation' => 'Ingin serius belajar affiliate.',
            'referral_source' => 'tiktok',
        ])->assertRedirect('/akun');

        $this->assertSame(1, User::count());
        $this->assertSame('Ingin serius belajar affiliate.', $person->fresh()->communityMembership->motivation);
    }

    public function test_member_with_incomplete_profile_gets_the_editable_form(): void
    {
        $user = $this->participantWithProfile(['birth_date' => null, 'gender' => null, 'followed_socials' => null]);

        $this->actingAs($user)->get('/komunitas')
            ->assertOk()
            ->assertSee('Gabung Sekarang')
            ->assertDontSee('Konfirmasi datamu')
            ->assertDontSee('Kata sandi');
    }

    public function test_staff_is_redirected_from_community_door(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get('/komunitas')->assertRedirect('/admin');
    }
}
