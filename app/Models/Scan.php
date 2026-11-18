<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\ScanFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property CarbonImmutable $scanned_at
 */
class Scan extends Model
{
    /** @use HasFactory<ScanFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'scanned_at',
        'total_customers',
        'fraudulent_count',
    ];

    /**
     * @return HasMany<Customer, $this>
     */
    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'scanned_at' => 'immutable_datetime',
            'total_customers' => 'integer',
            'fraudulent_count' => 'integer',
        ];
    }
}
