<?php

namespace Tests\Feature;

use App\Models\Cohort;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CohortModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_closes_when_class_start_time_passes(): void
    {
        $cohort = Cohort::factory()->create([
            'registration_opens_at' => now()->subDay(),
            'registration_closes_at' => now()->addDay(),
            'start_date' => now()->subMinute(),
        ]);

        $this->assertFalse($cohort->isOpenForRegistration());
        $this->assertSame(0, Cohort::openForRegistration()->count());
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

    public function test_maps_url_requires_both_coordinates(): void
    {
        $located = Cohort::factory()->atLocation()->create();
        $bare = Cohort::factory()->create();

        $this->assertStringContainsString('google.com/maps', $located->mapsUrl());
        $this->assertNull($bare->mapsUrl());
    }

    public function test_is_online_by_type(): void
    {
        $this->assertTrue(Cohort::factory()->online()->create()->isOnline());
        $this->assertFalse(Cohort::factory()->create()->isOnline());
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
