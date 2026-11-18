<?php

namespace App\Actions;

use App\Models\Scan;
use App\Services\CustomersApi\CustomersApiClient;
use App\Services\CustomersApi\CustomersApiException;
use App\Services\Fraud\FraudDetector;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Fetches every customer, checks them for fraud and records the result as a
 * new Scan. Nothing is saved when the customers API fails.
 */
final readonly class RunFraudScan
{
    public function __construct(
        private CustomersApiClient $client,
        private FraudDetector $detector,
    ) {}

    /**
     * @throws CustomersApiException
     */
    public function handle(): Scan
    {
        // Fetched before the transaction, so no database lock is held during HTTP retries.
        $customers = $this->client->fetchAll();
        $now = CarbonImmutable::now();
        $reasons = $this->detector->detect($customers, $now);

        $rows = [];
        $fraudulentCount = 0;

        foreach ($customers as $index => $customer) {
            $customerReasons = $reasons[$index] ?? [];
            $isFraudulent = $customerReasons !== [];
            $fraudulentCount += (int) $isFraudulent;

            $rows[] = [
                'external_id' => $customer->externalId,
                'bsn' => $customer->bsn,
                'first_name' => $customer->firstName,
                'last_name' => $customer->lastName,
                'date_of_birth' => $customer->dateOfBirth,
                'phone_number' => $customer->phoneNumber,
                'email' => $customer->email,
                'tag' => $customer->tag,
                'street' => $customer->street,
                'postcode' => $customer->postcode,
                'city' => $customer->city,
                'products' => $customer->products,
                'ip_address' => $customer->ipAddress,
                'iban' => $customer->iban,
                'last_invoice_date' => $customer->lastInvoiceDate,
                'last_login_at' => $customer->lastLoginAt,
                'is_fraudulent' => $isFraudulent,
                'fraud_reasons' => $customerReasons,
            ];
        }

        return DB::transaction(function () use ($now, $rows, $fraudulentCount): Scan {
            $scan = Scan::create([
                'scanned_at' => $now,
                'total_customers' => count($rows),
                'fraudulent_count' => $fraudulentCount,
            ]);

            $scan->setRelation('customers', $scan->customers()->createMany($rows));

            return $scan;
        });
    }
}
