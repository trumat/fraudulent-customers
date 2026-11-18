<?php

namespace Tests\Feature\Api;

use App\Models\Customer;
use App\Models\Scan;
use App\Support\LatestScanCache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class ShowLatestScanTest extends TestCase
{
    use RefreshDatabase;

    public function testItIsServedFromTheCache(): void
    {
        $scan = Scan::factory()->has(Customer::factory()->count(2))->create();
        $cached = app(LatestScanCache::class)->put($scan);

        // Proves the response comes from the cache, not the database.
        Scan::query()->delete();

        $this->getJson('/api/scans/latest')
            ->assertOk()
            ->assertExactJson(['data' => $cached]);
    }

    public function testItFallsBackToTheNewestScanAndRefillsTheCache(): void
    {
        Scan::factory()->create(['scanned_at' => now()->subDay()]);
        $newest = Scan::factory()->has(Customer::factory()->count(2))->create(['scanned_at' => now()]);
        Cache::flush();

        $this->getJson('/api/scans/latest')
            ->assertOk()
            ->assertJsonPath('data.id', $newest->id)
            ->assertJsonCount(2, 'data.customers');

        $this->assertIsArray(Cache::get('scans.latest'));
    }

    /**
     * phpunit.xml uses the array store, which never serializes. The database
     * store does, with serializable_classes => false, so any object in the
     * payload (e.g. if put() used resolve(), which keeps the nested customer
     * collection as an object) would come back as __PHP_Incomplete_Class.
     */
    public function testThePayloadSurvivesTheDatabaseCacheStore(): void
    {
        config(['cache.default' => 'database']);
        $this->app->forgetInstance('cache.store');
        Cache::clearResolvedInstances();

        $scan = Scan::factory()->has(Customer::factory()->count(2))->create();
        $cached = app(LatestScanCache::class)->put($scan);
        $this->assertDatabaseCount('cache', 1);

        Scan::query()->delete();

        $this->assertSame($cached, app(LatestScanCache::class)->get());
        $this->getJson('/api/scans/latest')
            ->assertOk()
            ->assertExactJson(['data' => $cached]);
    }

    public function testAnOlderScanDoesNotReplaceANewerCachedOne(): void
    {
        [$older, $newer] = Scan::factory()->count(2)->create();
        $cache = app(LatestScanCache::class);

        // The newer scan finishes first.
        $cache->put($newer);
        $cache->put($older);

        $this->getJson('/api/scans/latest')
            ->assertOk()
            ->assertJsonPath('data.id', $newer->id);
    }

    public function testItReturns404WhenThereAreNoScans(): void
    {
        $this->getJson('/api/scans/latest')
            ->assertNotFound()
            ->assertJsonPath('message', 'No scans yet.');
    }
}
