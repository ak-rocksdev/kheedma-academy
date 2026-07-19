<?php

namespace Tests\Feature;

use App\Models\Cohort;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CohortModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_follows_the_window_even_after_class_start(): void
    {
        // PO decision 2026-07-17: ONLY the registration window governs.
        // A class that has already started stays open for registration as
        // long as its window says so (late joiners are welcome).
        $cohort = Cohort::factory()->create([
            'registration_opens_at' => now()->subDay(),
            'registration_closes_at' => now()->addDay(),
            'start_date' => now()->subMinute(),
        ]);

        $this->assertTrue($cohort->isOpenForRegistration());
        $this->assertSame(1, Cohort::openForRegistration()->count());
    }

    public function test_registration_open_before_class_starts(): void
    {
        $cohort = Cohort::factory()->create([
            'registration_opens_at' => now()->subDay(),
            'registration_closes_at' => now()->addDays(2),
            'start_date' => now()->addHour(),
        ]);

        $this->assertTrue($cohort->isOpenForRegistration());
        $this->assertSame(1, Cohort::openForRegistration()->count());
    }

    public function test_manual_close_still_cuts_earlier_than_start(): void
    {
        $cohort = Cohort::factory()->create([
            'registration_opens_at' => now()->subDay(),
            'registration_closes_at' => now()->subMinute(),
            'start_date' => now()->addDay(),
        ]);

        $this->assertFalse($cohort->isOpenForRegistration());
    }

    public function test_start_date_keeps_its_time(): void
    {
        $cohort = Cohort::factory()->create(['start_date' => '2026-08-01 09:30:00']);

        $this->assertSame('09:30', $cohort->fresh()->start_date->format('H:i'));
    }

    public function test_countdown_label_covers_the_final_week_only(): void
    {
        $today = Cohort::factory()->create(['start_date' => now()->addHours(3)]);
        $tomorrow = Cohort::factory()->create(['start_date' => now()->addDay()->setTime(9, 30)]);
        $inFive = Cohort::factory()->create(['start_date' => now()->addDays(5)]);
        $farAway = Cohort::factory()->create(['start_date' => now()->addDays(20)]);
        $past = Cohort::factory()->create(['start_date' => now()->subDay()]);

        $this->assertSame('Hari ini', $today->startCountdownLabel());
        $this->assertSame('Besok', $tomorrow->startCountdownLabel());
        $this->assertSame('5 hari lagi', $inFive->startCountdownLabel());
        $this->assertNull($farAway->startCountdownLabel());
        $this->assertNull($past->startCountdownLabel());
    }

    public function test_starts_within_hours_gates_on_the_future_side(): void
    {
        $soon = Cohort::factory()->create(['start_date' => now()->addHours(20)]);
        $far = Cohort::factory()->create(['start_date' => now()->addDays(5)]);
        $past = Cohort::factory()->create(['start_date' => now()->subHour()]);

        $this->assertTrue($soon->startsWithinHours(48));
        $this->assertFalse($far->startsWithinHours(48));
        $this->assertFalse($past->startsWithinHours(48));
    }

    public function test_start_label_includes_time_when_not_midnight(): void
    {
        $cohort = Cohort::factory()->create(['start_date' => '2026-08-01 09:30:00']);

        $this->assertSame('1 Agustus 2026 pukul 09.30 WIB', $cohort->startLabel());
    }

    public function test_start_label_omits_time_for_legacy_midnight_start(): void
    {
        $cohort = Cohort::factory()->create(['start_date' => '2026-08-01 00:00:00']);

        $this->assertSame('1 Agustus 2026', $cohort->startLabel());
    }

    public function test_start_label_is_null_without_a_start_date(): void
    {
        $cohort = Cohort::factory()->create(['start_date' => null]);

        $this->assertNull($cohort->startLabel());
    }
}
