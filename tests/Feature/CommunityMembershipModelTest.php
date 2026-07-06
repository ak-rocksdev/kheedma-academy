<?php

namespace Tests\Feature;

use App\Models\CommunityMembership;
use App\Models\Person;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CommunityMembershipModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_person_has_one_membership(): void
    {
        $person = Person::create([
            'name' => 'Uji Komunitas', 'phone' => '+628123450001', 'email' => 'uji.komunitas@example.test',
        ]);
        $membership = CommunityMembership::create([
            'people_id' => $person->id, 'referral_source' => 'instagram',
        ]);

        $this->assertTrue($person->communityMembership->is($membership));
        $this->assertTrue($membership->person->is($person));
    }

    public function test_membership_is_unique_per_person(): void
    {
        $person = Person::create([
            'name' => 'Uji Unik', 'phone' => '+628123450002', 'email' => 'uji.unik@example.test',
        ]);
        CommunityMembership::create(['people_id' => $person->id]);

        $this->expectException(QueryException::class);
        CommunityMembership::create(['people_id' => $person->id]);
    }

    public function test_admin_gets_community_view_permission(): void
    {
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);

        $this->assertTrue(Role::findByName('admin', 'web')->hasPermissionTo('community.view'));
        $this->assertFalse(Role::findByName('mentor', 'web')->hasPermissionTo('community.view'));
    }
}
