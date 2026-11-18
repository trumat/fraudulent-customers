<?php

namespace App\Support;

use App\Http\Resources\ScanResource;
use App\Models\Scan;
use Illuminate\Cache\Repository;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * The newest scan as a ready-made API payload, so a page refresh can restore
 * it without rebuilding the resource. The database stays the source of truth:
 * a cache miss rebuilds the entry from the newest scan.
 */
final readonly class LatestScanCache
{
    private const string KEY = 'scans.latest';

    public function __construct(private Repository $cache) {}

    /**
     * Stores the scan with its customers as a plain array. Objects are not
     * allowed: the cache unserializes them as __PHP_Incomplete_Class.
     *
     * @return array<string, mixed>
     */
    public function put(Scan $scan): array
    {
        $scan->loadMissing(['customers' => fn (HasMany $query) => $query->orderBy('id')]);

        /** @var array{data: array<string, mixed>} $response */
        $response = ScanResource::make($scan)->response()->getData(true);
        $payload = $response['data'];

        $this->cache->forever(self::KEY, $payload);

        return $payload;
    }

    /**
     * @return array<string, mixed>|null null when no scan has run yet
     */
    public function get(): ?array
    {
        $payload = $this->cache->get(self::KEY);

        if (is_array($payload)) {
            /** @var array<string, mixed> $payload */
            return $payload;
        }

        $scan = Scan::query()
            ->orderByDesc('scanned_at')
            ->orderByDesc('id')
            ->first();

        return $scan === null ? null : $this->put($scan);
    }
}
