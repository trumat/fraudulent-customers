<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ScanResource;
use App\Models\Scan;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ListScansController extends Controller
{
    public function __invoke(): AnonymousResourceCollection
    {
        return ScanResource::collection(
            Scan::query()->orderByDesc('scanned_at')->orderByDesc('id')->get(),
        );
    }
}
