<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\CommunityMembership;
use App\Models\Person;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class IntakeProfileModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_person_carries_the_intake_profile(): void
    {
        $person = Person::create([
            'name' => 'Uji Profil', 'phone' => '+628123450010', 'email' => 'uji.profil@example.test',
            'birth_date' => '2000-01-15', 'tiktok_username' => 'ujiprofil',
            'tiktok_followers' => 1500, 'has_started_affiliate' => true,
            'affiliate_level' => 3, 'affiliate_gmv_range' => '0-50',
        ]);

        $fresh = $person->fresh();
        $this->assertSame('2000-01-15', $fresh->birth_date->toDateString());
        $this->assertIsInt($fresh->age);
        $this->assertTrue($fresh->has_started_affiliate);
        $this->assertSame(3, (int) $fresh->affiliate_level);
        $this->assertContains($fresh->affiliate_gmv_range, Person::GMV_RANGES);
    }

    public function test_registration_records_carry_motivation_and_prefilter_is_gone(): void
    {
        $person = Person::create([
            'name' => 'Uji Motivasi', 'phone' => '+628123450011', 'email' => 'uji.motivasi@example.test',
        ]);
        $application = Application::create([
            'people_id' => $person->id, 'status' => 'pending', 'motivation' => 'Ingin belajar dari nol.',
        ]);
        $membership = CommunityMembership::create([
            'people_id' => $person->id, 'motivation' => 'Cari teman seperjalanan.',
        ]);

        $this->assertSame('Ingin belajar dari nol.', $application->fresh()->motivation);
        $this->assertSame('Cari teman seperjalanan.', $membership->fresh()->motivation);
        $this->assertFalse(Schema::hasColumn('applications', 'prefilter_verdict'));
        $this->assertFalse(Schema::hasColumn('applications', 'prefilter_link'));
    }
}
