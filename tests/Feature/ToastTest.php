<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ToastTest extends TestCase
{
    use RefreshDatabase;

    public function test_flashed_toast_renders_on_the_next_page(): void
    {
        $this->withSession(['toast' => ['type' => 'success', 'message' => 'Tersimpan ya.']])
            ->get('/')
            ->assertOk()
            ->assertSee('Tersimpan ya.')
            ->assertSee('data-toast', false);
    }

    public function test_error_toast_carries_its_own_styling(): void
    {
        $this->withSession(['toast' => ['type' => 'error', 'message' => 'Gagal menyimpan.']])
            ->get('/')
            ->assertOk()
            ->assertSee('Gagal menyimpan.')
            ->assertSee('bg-red-100', false);
    }

    public function test_page_without_a_flash_renders_no_toast(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertDontSee('data-toast', false);
    }
}
