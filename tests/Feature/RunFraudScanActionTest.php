<?php

namespace Tests\Feature;

use App\Actions\RunFraudScanAction;
use App\Models\Customer;
use App\Models\Scan;
use App\Services\CustomersApi\CustomersApiException;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RunFraudScanActionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
        CarbonImmutable::setTestNow(CarbonImmutable::create(2026, 6, 15, 12));

        config([
            'services.customers_api.base_url' => 'http://customers.test/api/v1',
            'services.customers_api.attempts' => 3,
            'services.customers_api.retry_sleep_ms' => 0,
        ]);
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function testItRecordsTheScanWithEveryCustomerAndVerdict(): void
    {
        Http::fake(['*' => Http::response(['success' => true, 'customers' => [
            $this->row(['customerId' => 1, 'firstName' => 'John', 'lastName' => 'Doe', 'ipAddress' => '127.0.0.1']),
            $this->row(['customerId' => 2, 'firstName' => 'Jane', 'lastName' => 'Roe', 'ipAddress' => '127.0.0.1']),
            $this->row(['customerId' => 3, 'phoneNumber' => '+4915112345678']),
            $this->row(['customerId' => 4, 'dateOfBirth' => '01-01-2010']),
            $this->row(['customerId' => 5]),
        ]])]);

        $scan = app(RunFraudScanAction::class)->handle();

        $this->assertDatabaseCount('scans', 1);
        $this->assertDatabaseCount('customers', 5);

        $scan = Scan::with('customers')->findOrFail($scan->id);
        $this->assertTrue($scan->scanned_at->equalTo(CarbonImmutable::create(2026, 6, 15, 12)));
        $this->assertSame(5, $scan->total_customers);
        $this->assertSame(4, $scan->fraudulent_count);

        $customers = $scan->customers->sortBy('id')->keyBy('external_id');
        $this->assertSame(['Same IP as Jane Roe (127.0.0.1)'], $customers[1]->fraud_reasons);
        $this->assertSame(['Same IP as John Doe (127.0.0.1)'], $customers[2]->fraud_reasons);
        $this->assertSame(['Non-NL phone number (+4915112345678)'], $customers[3]->fraud_reasons);
        $this->assertSame(['Customer is under 18 (16)'], $customers[4]->fraud_reasons);
        $this->assertSame([], $customers[5]->fraud_reasons);

        $this->assertSame(
            [1 => true, 2 => true, 3 => true, 4 => true, 5 => false],
            $customers->map(fn (Customer $c): bool => $c->is_fraudulent)->all(),
        );
    }

    public function testItStoresEveryUpstreamField(): void
    {
        $row = $this->row();
        Http::fake(['*' => Http::response(['success' => true, 'customers' => [$row]])]);

        $scan = app(RunFraudScanAction::class)->handle();

        $this->assertCount(1, $scan->customers);
        $customer = Customer::query()->sole();

        $this->assertSame($scan->id, $customer->scan_id);
        $this->assertSame(26225, $customer->external_id);
        $this->assertSame(324971175, $customer->bsn);
        $this->assertSame('Juul de Haas', $customer->full_name);
        $this->assertSame('1962-11-26', $customer->date_of_birth?->toDateString());
        $this->assertSame('+31612987632', $customer->phone_number);
        $this->assertSame('juultjehaas@gmail.com', $customer->email);
        $this->assertSame('new-customer', $customer->tag);
        $this->assertSame('Kerkstraat 12', $customer->street);
        $this->assertSame('2873CD', $customer->postcode);
        $this->assertSame('Amsterdam', $customer->city);
        $this->assertSame(['landline'], $customer->products);
        $this->assertSame($row['ipAddress'], $customer->ip_address);
        $this->assertSame($row['iban'], $customer->iban);
        $this->assertSame('2023-01-03', $customer->last_invoice_date?->toDateString());
        $this->assertSame('2022-11-12 09:52:36', $customer->last_login_at?->toDateTimeString());
        $this->assertFalse($customer->is_fraudulent);
    }

    public function testMissingFieldsAreStoredAsNull(): void
    {
        Http::fake(['*' => Http::response(['success' => true, 'customers' => [['customerId' => 7]]])]);

        app(RunFraudScanAction::class)->handle();

        $customer = Customer::query()->sole();
        $this->assertNull($customer->first_name);
        $this->assertNull($customer->date_of_birth);
        $this->assertNull($customer->ip_address);
        $this->assertSame([], $customer->products);
        $this->assertSame('Customer #7', $customer->full_name);
        $this->assertFalse($customer->is_fraudulent);
    }

    public function testAPersistentFailureSavesNothing(): void
    {
        Http::fake(['*' => Http::response('Service Unavailable', 503)]);

        try {
            app(RunFraudScanAction::class)->handle();
            $this->fail('Expected a CustomersApiException.');
        } catch (CustomersApiException) {
            // expected
        }

        Http::assertSentCount(3);
        $this->assertDatabaseCount('scans', 0);
        $this->assertDatabaseCount('customers', 0);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function row(array $overrides = []): array
    {
        return array_replace([
            'customerId' => 26225,
            'bsn' => 324971175,
            'firstName' => 'Juul',
            'lastName' => 'de Haas',
            'dateOfBirth' => '26-11-1962',
            'phoneNumber' => '+31612987632',
            'email' => 'juultjehaas@gmail.com',
            'tag' => 'new-customer',
            'address' => ['street' => 'Kerkstraat 12', 'postcode' => '2873CD', 'city' => 'Amsterdam'],
            'products' => ['landline'],
            'ipAddress' => fake()->unique()->ipv4(),
            'iban' => fake()->unique()->iban('NL'),
            'lastInvoiceDate' => '03-01-2023',
            'lastLoginDateTime' => '12-11-2022 09:52:36',
        ], $overrides);
    }
}
