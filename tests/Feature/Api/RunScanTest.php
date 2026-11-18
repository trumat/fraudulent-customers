<?php

namespace Tests\Feature\Api;

use App\Models\Scan;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RunScanTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
        CarbonImmutable::setTestNow(CarbonImmutable::create(2026, 6, 15, 12));

        config([
            'services.customers_api.base_url' => 'http://customers.test/api/v1',
            'services.customers_api.attempts' => 3,
            'services.customers_api.retry_sleep_ms' => 0,
        ]);
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function testItReturnsTheNewScanWithReasons(): void
    {
        $this->fakeCustomersApi();

        $this->postJson('/api/scans')
            ->assertCreated()
            ->assertJsonPath('data.scanned_at', '2026-06-15T12:00:00+00:00')
            ->assertJsonPath('data.total_customers', 3)
            ->assertJsonPath('data.fraudulent_count', 2)
            ->assertJsonPath('data.customers.0.full_name', 'John Doe')
            ->assertJsonPath('data.customers.0.is_fraudulent', true)
            ->assertJsonPath('data.customers.0.fraud_reasons', ['Same IP as Jane Roe (127.0.0.1)'])
            ->assertJsonPath('data.customers.0.date_of_birth', '1962-11-26')
            ->assertJsonPath('data.customers.0.last_login_at', '2022-11-12 09:52:36')
            ->assertJsonPath('data.customers.2.is_fraudulent', false)
            ->assertJsonPath('data.customers.2.fraud_reasons', []);

        $this->assertDatabaseCount('scans', 1);
    }

    public function testItCachesTheNewScanAsTheLatest(): void
    {
        $this->fakeCustomersApi();

        $response = $this->postJson('/api/scans')->assertCreated();

        $this->assertSame($response->json('data'), Cache::get('scans.latest'));
    }

    public function testAnUpstreamFailureReturns503AndSavesNoScan(): void
    {
        Http::fake(['*' => Http::response('Service Unavailable', 503)]);

        $this->postJson('/api/scans')
            ->assertStatus(503)
            ->assertExactJson(['message' => 'The customers API responded with HTTP 503.']);

        $this->assertSame(0, Scan::query()->count());
        $this->assertNull(Cache::get('scans.latest'));
    }

    private function fakeCustomersApi(): void
    {
        $row = [
            'dateOfBirth' => '26-11-1962',
            'phoneNumber' => '+31612987632',
            'lastLoginDateTime' => '12-11-2022 09:52:36',
        ];

        Http::fake(['*' => Http::response(['success' => true, 'customers' => [
            [...$row, 'customerId' => 1, 'firstName' => 'John', 'lastName' => 'Doe', 'ipAddress' => '127.0.0.1'],
            [...$row, 'customerId' => 2, 'firstName' => 'Jane', 'lastName' => 'Roe', 'ipAddress' => '127.0.0.1'],
            [...$row, 'customerId' => 3, 'firstName' => 'Kim', 'lastName' => 'Clean', 'ipAddress' => '10.0.0.3'],
        ]])]);
    }
}
