<?php

namespace App\Http\Resources;

use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Only scalars, arrays and null, so the payload can be cached as is.
 *
 * @mixin Customer
 */
class CustomerResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'external_id' => $this->external_id,
            'full_name' => $this->full_name,
            'bsn' => $this->bsn,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'date_of_birth' => $this->date_of_birth?->format('Y-m-d'),
            'phone_number' => $this->phone_number,
            'email' => $this->email,
            'tag' => $this->tag,
            'street' => $this->street,
            'postcode' => $this->postcode,
            'city' => $this->city,
            'products' => $this->products,
            'ip_address' => $this->ip_address,
            'iban' => $this->iban,
            'last_invoice_date' => $this->last_invoice_date?->format('Y-m-d'),
            // Upstream sends naive local time, so no timezone is added.
            'last_login_at' => $this->last_login_at?->format('Y-m-d H:i:s'),
            'is_fraudulent' => $this->is_fraudulent,
            'fraud_reasons' => $this->fraud_reasons,
        ];
    }
}
