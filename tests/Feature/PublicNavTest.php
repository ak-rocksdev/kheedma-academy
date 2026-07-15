<?php

namespace Tests\Feature;

use App\Models\Person;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicNavTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_pages_carry_the_mobile_bottom_nav(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Navigasi utama', false)
            ->assertSee(route('komunitas'), false)
            ->assertSee(route('member.login'), false);
    }

    public function test_logged_in_member_reaches_account_and_logout_from_the_layout(): void
    {
        $this->seed(RoleSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('participant');
        $person = Person::create([
            'name' => 'Member Nav',
            'phone' => '+628123123123',
            'email' => 'member.nav@example.test',
        ]);
        $person->user()->associate($user); // user_id is guarded by design
        $person->save();

        $this->actingAs($user)
            ->get('/')
            ->assertOk()
            ->assertSee('Akun Saya')
            ->assertSee('Keluar')
            ->assertSee(route('member.area'), false);
    }

    public function test_account_page_swaps_in_its_own_bottom_nav(): void
    {
        $this->seed(RoleSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('participant');
        Person::create([
            'name' => 'Member Nav Dua',
            'phone' => '+628123123124',
            'email' => 'member.nav2@example.test',
        ]);

        $this->actingAs($user)
            ->get('/akun')
            ->assertOk()
            ->assertSee('Bagian akun', false)
            ->assertDontSee('Navigasi utama', false);
    }
}
