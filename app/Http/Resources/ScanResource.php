<?php

namespace App\Http\Resources;

use App\Models\Scan;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Scan
 */
class ScanResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'scanned_at' => $this->scanned_at->toIso8601String(),
            'total_customers' => $this->total_customers,
            'fraudulent_count' => $this->fraudulent_count,
            'customers' => CustomerResource::collection($this->whenLoaded('customers')),
        ];
    }
}
