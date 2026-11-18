<?php

namespace Tests\Feature;

use Tests\TestCase;

class WebRoutesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // app.blade.php uses @vite, and there is no build manifest in tests.
        $this->withoutVite();
    }

    public function testTheScanPageRendersTheSpa(): void
    {
        $this->get('/')->assertOk()->assertViewIs('app');
    }

    public function testTheHistoryPageRendersTheSpa(): void
    {
        $this->get('/scans')->assertOk()->assertViewIs('app');
    }

    public function testUnknownApiPathsReturnJson404(): void
    {
        $this->get('/api/unknown')
            ->assertNotFound()
            ->assertJsonStructure(['message']);
    }
}
