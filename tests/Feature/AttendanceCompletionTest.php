<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Cohort;
use App\Models\CohortSession;
use App\Models\Enrollment;
use App\Models\Person;
use App\Models\Program;
use App\Support\AttendanceCompletion;
use App\Support\ProgramEligibility;
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

    private function attend(Enrollment $enrollment, int $count): void
    {
        $sessions = $enrollment->cohort->sessions()->take($count)->get();
        foreach ($sessions as $session) {
            Attendance::firstOrCreate([
                'cohort_session_id' => $session->id,
                'enrollment_id' => $enrollment->id,
            ]);
        }
    }

    private function autoCompletedCount(Enrollment $enrollment): int
    {
        return $enrollment->statusEvents()
            ->where('status', 'completed')->where('note', 'auto:attendance')->count();
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

    public function test_reaching_requirement_writes_one_completed_event(): void
    {
        $engine = app(AttendanceCompletion::class);
        $enrollment = $this->makeEnrollment(requiredAttendance: 2);

        $this->attend($enrollment, 2);
        $engine->sync($enrollment);
        $engine->sync($enrollment); // idempotent

        $this->assertSame(1, $this->autoCompletedCount($enrollment));
    }

    public function test_default_requirement_is_all_sessions(): void
    {
        $engine = app(AttendanceCompletion::class);
        $enrollment = $this->makeEnrollment(requiredAttendance: null, sessions: 3);

        $this->attend($enrollment, 2);
        $engine->sync($enrollment);
        $this->assertSame(0, $this->autoCompletedCount($enrollment));

        $this->attend($enrollment, 3);
        $engine->sync($enrollment);
        $this->assertSame(1, $this->autoCompletedCount($enrollment));
    }

    public function test_correction_below_requirement_retracts_auto_event_only(): void
    {
        $engine = app(AttendanceCompletion::class);
        $enrollment = $this->makeEnrollment(requiredAttendance: 2);

        // A manual completed event must never be touched.
        $enrollment->statusEvents()->create(['status' => 'completed', 'note' => 'manual', 'occurred_at' => now()]);

        $this->attend($enrollment, 2);
        $engine->sync($enrollment);
        $this->assertSame(1, $this->autoCompletedCount($enrollment));

        $enrollment->attendances()->limit(1)->delete();
        $engine->sync($enrollment);

        $this->assertSame(0, $this->autoCompletedCount($enrollment));
        $this->assertSame(1, $enrollment->statusEvents()->where('note', 'manual')->count());
    }

    public function test_no_sessions_means_no_op(): void
    {
        $engine = app(AttendanceCompletion::class);
        $enrollment = $this->makeEnrollment(requiredAttendance: null, sessions: 0);

        $engine->sync($enrollment);
        $this->assertSame(0, $this->autoCompletedCount($enrollment));
    }

    public function test_dropped_enrollment_is_never_auto_completed(): void
    {
        $engine = app(AttendanceCompletion::class);
        $enrollment = $this->makeEnrollment(requiredAttendance: 1);

        $enrollment->statusEvents()->create(['status' => 'dropped', 'note' => 'berhenti', 'occurred_at' => now()]);
        $this->attend($enrollment, 1);
        $engine->sync($enrollment);

        $this->assertSame(0, $this->autoCompletedCount($enrollment));
    }

    public function test_attendance_completion_unlocks_affiliate_eligibility(): void
    {
        $engine = app(AttendanceCompletion::class);
        $eligibility = app(ProgramEligibility::class);

        $enrollment = $this->makeEnrollment(requiredAttendance: 2);
        $affiliate = Program::factory()->affiliate(1)->active()->create();
        $person = $enrollment->person;

        $this->assertFalse($eligibility->canAccess($person, $affiliate));

        $this->attend($enrollment, 2);
        $engine->sync($enrollment);

        $this->assertTrue($eligibility->canAccess($person->fresh(), $affiliate));
    }
}
