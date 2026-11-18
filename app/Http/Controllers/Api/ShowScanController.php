<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ScanResource;
use App\Models\Scan;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ShowScanController extends Controller
{
    public function __invoke(Scan $scan): ScanResource
    {
        return ScanResource::make(
            $scan->load(['customers' => fn (HasMany $query) => $query->orderBy('id')]),
        );
    }
}
