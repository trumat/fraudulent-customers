<?php

namespace Tests\Feature\Api;

use App\Models\Customer;
use App\Models\Scan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShowScanTest extends TestCase
{
    use RefreshDatabase;

    public function testItReturnsTheScanWithItsCustomers(): void
    {
        $scan = Scan::factory()->has(Customer::factory()->count(2))->create();
        Customer::factory()->create(); // belongs to another scan

        $this->getJson("/api/scans/{$scan->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $scan->id)
            ->assertJsonCount(2, 'data.customers');
    }

    public function testItReturns404ForAnUnknownScan(): void
    {
        $this->getJson('/api/scans/999')
            ->assertNotFound()
            ->assertJsonStructure(['message']);
    }
}
