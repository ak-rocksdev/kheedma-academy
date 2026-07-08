<?php

namespace Tests\Feature;

use App\Models\Program;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProgramEligibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_program_segmentation_fields_round_trip(): void
    {
        $general = Program::factory()->create();
        $affiliate = Program::factory()->affiliate(2)->create(['locked_message' => 'Khusus lulusan Level 1.']);

        $this->assertSame('general', $general->fresh()->type);
        $this->assertFalse($general->isAffiliate());

        $fresh = $affiliate->fresh();
        $this->assertSame('affiliate_community', $fresh->type);
        $this->assertSame(2, (int) $fresh->level);
        $this->assertSame('Khusus lulusan Level 1.', $fresh->locked_message);
        $this->assertTrue($fresh->isAffiliate());

        $this->assertNotSame('', (string) config('kheedma.default_locked_message'));
    }
}
