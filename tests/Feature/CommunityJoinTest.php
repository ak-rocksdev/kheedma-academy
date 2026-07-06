<?php

namespace Tests\Feature;

use App\Models\CommunityMembership;
use App\Models\Person;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
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
            'password' => 'rahasia-kuat',
            'referral_source' => 'tiktok',
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
        $this->assertTrue(Auth::check());
        $this->assertTrue(Auth::user()->is($person->user));
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
}
