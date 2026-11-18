<?php

namespace Tests\Feature\Api;

use App\Models\Customer;
use App\Models\Scan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ListScansTest extends TestCase
{
    use RefreshDatabase;

    public function testItListsScansNewestFirstWithoutCustomers(): void
    {
        $older = Scan::factory()->has(Customer::factory())->create(['scanned_at' => now()->subDay()]);
        $newer = Scan::factory()->create(['scanned_at' => now()]);

        $this->getJson('/api/scans')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $newer->id)
            ->assertJsonPath('data.1.id', $older->id)
            ->assertJsonMissingPath('data.1.customers');
    }

    public function testItReturnsAnEmptyListWhenThereAreNoScans(): void
    {
        $this->getJson('/api/scans')
            ->assertOk()
            ->assertExactJson(['data' => []]);
    }
}
