<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\Cohort;
use App\Models\CommunityMembership;
use App\Models\Person;
use App\Models\Program;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Live (Precognition) validation on the two funnel forms: a precognitive
 * request runs the route's middleware and validates the FormRequest, but
 * never executes the controller — so nothing gets persisted either way.
 */
class PrecognitionValidationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        DB::table('indonesia_provinces')->insert([
            'code' => '32', 'name' => 'JAWA BARAT', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('indonesia_cities')->insert([
            'code' => '3273', 'province_code' => '32', 'name' => 'KOTA BANDUNG', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function openProgram(): Program
    {
        $program = Program::factory()->active()->create();
        Cohort::factory()->openWindow()->create(['program_id' => $program->id]);

        return $program;
    }

    /** @return array<string, mixed> */
    private function communityPayload(): array
    {
        return [
            'name' => 'Siti Aminah',
            'phone' => '081298765432',
            'email' => 'siti@example.test',
            'password' => 'rahasia-kuat',
            'birth_date' => '2000-01-15',
            'gender' => 'male',
            'motivation' => 'Ingin serius belajar affiliate.',
            'referral_source' => 'tiktok',
            'followed_socials' => 1,
        ];
    }

    /** @return array<string, mixed> */
    private function applyPayload(): array
    {
        return [
            'name' => 'Budi Santoso',
            'phone' => '081234567890',
            'email' => 'budi@example.test',
            'password' => 'rahasia-kuat',
            'province_code' => '32',
            'city_code' => '3273',
            'birth_date' => '2000-01-15',
            'gender' => 'male',
            'motivation' => 'Ingin serius belajar affiliate.',
            'referral_source' => 'instagram',
            'followed_socials' => 1,
        ];
    }

    public function test_precognitive_community_join_validating_invalid_email_returns_422_without_persisting(): void
    {
        $this->withPrecognition()
            ->withHeaders(['Precognition-Validate-Only' => 'email', 'Accept' => 'application/json'])
            ->post('/komunitas', [...$this->communityPayload(), 'email' => 'bukan-email'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('email');

        $this->assertSame(0, Person::count());
        $this->assertSame(0, User::count());
        $this->assertSame(0, CommunityMembership::count());
    }

    public function test_precognitive_community_join_validating_valid_email_succeeds_without_persisting(): void
    {
        $this->withPrecognition()
            ->withHeaders(['Precognition-Validate-Only' => 'email', 'Accept' => 'application/json'])
            ->post('/komunitas', $this->communityPayload())
            ->assertSuccessfulPrecognition()
            ->assertNoContent();

        $this->assertSame(0, Person::count());
        $this->assertSame(0, User::count());
        $this->assertSame(0, CommunityMembership::count());
    }

    public function test_precognitive_apply_validating_phone_of_existing_account_returns_422(): void
    {
        $program = $this->openProgram();

        // Provision an account carrying this phone via a full (non-precognitive) submit.
        $this->post("/program/{$program->slug}/daftar", $this->applyPayload());
        Auth::logout();

        $second = $this->openProgram();

        $this->withPrecognition()
            ->withHeaders(['Precognition-Validate-Only' => 'phone', 'Accept' => 'application/json'])
            ->post("/program/{$second->slug}/daftar", [...$this->applyPayload(), 'email' => 'lain@example.test'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['phone' => 'Nomor ini sudah punya akun. Silakan masuk.']);

        $this->assertSame(1, Application::count());
        $this->assertSame(1, User::role('participant')->count());
    }

    public function test_normal_post_still_creates_everything(): void
    {
        $this->post('/komunitas', $this->communityPayload())
            ->assertRedirect('/akun');

        $person = Person::sole();
        $this->assertNotNull($person->user_id);
        $this->assertSame(1, CommunityMembership::count());
        $this->assertTrue(Auth::check());
    }
}
