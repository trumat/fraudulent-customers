<?php

namespace App\Services\CustomersApi;

use Illuminate\Container\Attributes\Config;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Fetches all customers from the (unstable) customers API.
 *
 * Connection errors and 5xx responses are retried; 4xx responses are not,
 * because retrying won't fix them. Every failure ends up as a
 * CustomersApiException.
 */
final class CustomersApiClient
{
    public function __construct(
        #[Config('services.customers_api.base_url')] private readonly string $baseUrl,
        #[Config('services.customers_api.timeout')] private readonly int $timeout,
        #[Config('services.customers_api.attempts')] private readonly int $attempts,
        #[Config('services.customers_api.retry_sleep_ms')] private readonly int $retrySleepMs,
    ) {}

    /**
     * @return list<CustomerData>
     *
     * @throws CustomersApiException
     */
    public function fetchAll(): array
    {
        try {
            $response = Http::baseUrl($this->baseUrl)
                ->acceptJson()
                ->timeout($this->timeout)
                ->retry(
                    max(1, $this->attempts),
                    $this->retrySleepMs,
                    when: fn (Throwable $e): bool => $this->isTransient($e),
                    throw: false,
                )
                ->get('customers');
        } catch (ConnectionException $e) {
            // Still thrown after the last attempt, even with throw: false.
            throw CustomersApiException::unreachable($e);
        }

        if (!$response->successful()) {
            throw CustomersApiException::httpError($response->status());
        }

        return $this->mapPayload($response->json());
    }

    private function isTransient(Throwable $e): bool
    {
        return $e instanceof ConnectionException
            || ($e instanceof RequestException && $e->response->serverError());
    }

    /**
     * @return list<CustomerData>
     */
    private function mapPayload(mixed $payload): array
    {
        if (!is_array($payload)) {
            throw CustomersApiException::malformedPayload('Response body is not a JSON object.');
        }

        if (($payload['success'] ?? null) !== true) {
            throw CustomersApiException::malformedPayload('The "success" flag is missing or not true.');
        }

        $rows = $payload['customers'] ?? null;

        if (!is_array($rows) || !array_is_list($rows)) {
            throw CustomersApiException::malformedPayload('The "customers" field is missing or not a list.');
        }

        $customers = [];

        foreach ($rows as $index => $row) {
            if (!is_array($row)) {
                throw CustomersApiException::malformedPayload("Customer #{$index} is not an object.");
            }

            $customers[] = CustomerData::fromArray($row);
        }

        $this->logDataIssues($customers);

        return $customers;
    }

    /**
     * Malformed fields are treated as absent, so they never count as fraud
     * evidence. They are logged (one entry per response) rather than stored.
     *
     * @param  list<CustomerData>  $customers
     */
    private function logDataIssues(array $customers): void
    {
        $issues = [];

        foreach ($customers as $customer) {
            foreach ($customer->dataIssues as $issue) {
                $issues[] = "customer {$customer->externalId}: {$issue}";
            }
        }

        if ($issues === []) {
            return;
        }

        Log::warning('The customers API returned malformed fields; they were treated as absent.', [
            'count' => count($issues),
            'issues' => array_slice($issues, 0, 20),
        ]);
    }
}
