<?php

namespace App\Models;

use App\Support\CustomerName;
use Carbon\CarbonImmutable;
use Database\Factories\CustomerFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A customer as recorded by one scan, with the fraud verdict at that time.
 *
 * @property ?CarbonImmutable $date_of_birth
 * @property list<string> $products
 * @property ?CarbonImmutable $last_invoice_date
 * @property ?CarbonImmutable $last_login_at
 * @property list<string> $fraud_reasons
 */
class Customer extends Model
{
    /** @use HasFactory<CustomerFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'external_id',
        'bsn',
        'first_name',
        'last_name',
        'date_of_birth',
        'phone_number',
        'email',
        'tag',
        'street',
        'postcode',
        'city',
        'products',
        'ip_address',
        'iban',
        'last_invoice_date',
        'last_login_at',
        'is_fraudulent',
        'fraud_reasons',
    ];

    /**
     * @return BelongsTo<Scan, $this>
     */
    public function scan(): BelongsTo
    {
        return $this->belongsTo(Scan::class);
    }

    /**
     * @return Attribute<string, never>
     */
    protected function fullName(): Attribute
    {
        return Attribute::get(fn (): string => CustomerName::format(
            $this->first_name,
            $this->last_name,
            $this->external_id,
        ));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'external_id' => 'integer',
            'bsn' => 'integer',
            'date_of_birth' => 'immutable_date',
            'products' => 'array',
            'last_invoice_date' => 'immutable_date',
            'last_login_at' => 'immutable_datetime',
            'is_fraudulent' => 'boolean',
            'fraud_reasons' => 'array',
        ];
    }
}
