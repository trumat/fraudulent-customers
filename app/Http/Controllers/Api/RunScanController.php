<?php

namespace App\Http\Controllers\Api;

use App\Actions\RunFraudScanAction;
use App\Http\Controllers\Controller;
use App\Http\Resources\ScanResource;
use App\Support\LatestScanCache;
use Illuminate\Http\JsonResponse;

class RunScanController extends Controller
{
    public function __invoke(RunFraudScanAction $runFraudScan, LatestScanCache $latestScan): JsonResponse
    {
        // A CustomersApiException is rendered as 503 in bootstrap/app.php.
        $scan = $runFraudScan->handle();
        $latestScan->put($scan);

        return ScanResource::make($scan)->response()->setStatusCode(201);
    }
}
