<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Cohort;
use App\Models\CohortSession;
use App\Models\Enrollment;
use App\Models\Person;
use App\Models\Program;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AttendanceCompletionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
    }

    private function makeEnrollment(?int $requiredAttendance = null, int $sessions = 3): Enrollment
    {
        $program = Program::factory()->active()->create();
        $cohort = Cohort::factory()->create([
            'program_id' => $program->id,
            'required_attendance' => $requiredAttendance,
        ]);
        CohortSession::factory()->count($sessions)->create(['cohort_id' => $cohort->id]);

        $person = Person::create([
            'name' => 'Peserta '.fake()->unique()->numberBetween(1, 99999),
            'phone' => '+628'.fake()->unique()->numerify('##########'),
            'email' => fake()->unique()->safeEmail(),
        ]);

        return Enrollment::create(['people_id' => $person->id, 'cohort_id' => $cohort->id]);
    }

    public function test_schema_and_relations_round_trip(): void
    {
        $enrollment = $this->makeEnrollment(requiredAttendance: 2);

        $session = $enrollment->cohort->sessions()->first();
        Attendance::create(['cohort_session_id' => $session->id, 'enrollment_id' => $enrollment->id]);

        $this->assertSame(3, $enrollment->cohort->sessions()->count());
        $this->assertSame(2, (int) $enrollment->cohort->required_attendance);
        $this->assertSame(1, $enrollment->attendances()->count());
        $this->assertTrue(Role::findByName('admin', 'web')->hasPermissionTo('enrollments.manage'));
        $this->assertTrue(Role::findByName('mentor', 'web')->hasPermissionTo('attendance.record'));
        $this->assertFalse(Role::findByName('mentor', 'web')->hasPermissionTo('enrollments.manage'));
    }
}
