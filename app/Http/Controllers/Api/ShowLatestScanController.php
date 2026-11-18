<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\LatestScanCache;
use Illuminate\Http\JsonResponse;

class ShowLatestScanController extends Controller
{
    public function __invoke(LatestScanCache $latestScan): JsonResponse
    {
        $payload = $latestScan->get();

        abort_if($payload === null, 404, 'No scans yet.');

        // Wrapped by hand to match the shape of a ScanResource response.
        return response()->json(['data' => $payload]);
    }
}
