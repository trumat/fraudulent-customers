<?php

namespace Tests\Unit;

use App\Services\CustomersApi\CustomerData;
use App\Services\CustomersApi\CustomersApiClient;
use App\Services\CustomersApi\CustomersApiException;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CustomersApiClientTest extends TestCase
{
    private const string BASE_URL = 'http://customers.test/api/v1';

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();

        config([
            'services.customers_api.base_url' => self::BASE_URL,
            'services.customers_api.timeout' => 5,
            'services.customers_api.attempts' => 3,
            'services.customers_api.retry_sleep_ms' => 0,
        ]);
    }

    public function testItMapsCustomersFromASuccessfulResponse(): void
    {
        $this->fakeCustomers([$this->customerRow()]);

        $customers = $this->client()->fetchAll();

        $this->assertCount(1, $customers);
        $customer = $customers[0];

        $this->assertSame(26225, $customer->externalId);
        $this->assertSame(324971175, $customer->bsn);
        $this->assertSame('Juul', $customer->firstName);
        $this->assertSame('de Haas', $customer->lastName);
        $this->assertSame('Juul de Haas', $customer->fullName());
        $this->assertEquals(CarbonImmutable::create(1962, 11, 26), $customer->dateOfBirth);
        $this->assertSame('+31612987632', $customer->phoneNumber);
        $this->assertSame('juultjehaas@gmail.com', $customer->email);
        $this->assertSame('new-customer', $customer->tag);
        $this->assertSame('Kerkstraat 12', $customer->street);
        $this->assertSame('2873CD', $customer->postcode);
        $this->assertSame('Amsterdam', $customer->city);
        $this->assertSame(['landline'], $customer->products);
        $this->assertSame('114.157.110.110', $customer->ipAddress);
        $this->assertSame('NL20EYIJ4524255179', $customer->iban);
        $this->assertEquals(CarbonImmutable::create(2023, 1, 3), $customer->lastInvoiceDate);
        $this->assertEquals(CarbonImmutable::create(2022, 11, 12, 9, 52, 36), $customer->lastLoginAt);
        $this->assertSame([], $customer->dataIssues);
        $this->assertFalse($customer->hasDataIssues());
    }

    public function testBlankValuesAreAbsentNotIssues(): void
    {
        $customer = CustomerData::fromArray($this->customerRow([
            'firstName' => '',
            'lastName' => '   ',
            'ipAddress' => null,
            'bsn' => '',
            'dateOfBirth' => ' ',
            'address' => null,
        ]));

        $this->assertNull($customer->firstName);
        $this->assertNull($customer->lastName);
        $this->assertNull($customer->ipAddress);
        $this->assertNull($customer->bsn);
        $this->assertNull($customer->dateOfBirth);
        $this->assertNull($customer->street);
        $this->assertSame([], $customer->dataIssues);
    }

    /**
     * @return array<string, array{array<string, mixed>, string, string}>
     */
    public static function wrongTypesDataProvider(): array
    {
        return [
            'firstName as int' => [['firstName' => 123], 'firstName', 'firstName: expected string, got int (123)'],
            'ipAddress as array' => [['ipAddress' => []], 'ipAddress', 'ipAddress: expected string, got array ([])'],
            'customerId not numeric' => [['customerId' => 'abc'], 'externalId', 'customerId: expected integer, got string ("abc")'],
            'address as string' => [['address' => 'Kerkstraat 12'], 'street', 'address: expected object, got string ("Kerkstraat 12")'],
            'address part as int' => [['address' => ['street' => 12, 'postcode' => '2873CD', 'city' => 'Amsterdam']], 'street', 'address.street: expected string, got int (12)'],
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    #[DataProvider('wrongTypesDataProvider')]
    public function testWrongTypesAreRecordedAsIssues(array $overrides, string $property, string $expectedIssue): void
    {
        $customer = CustomerData::fromArray($this->customerRow($overrides));

        $this->assertNull($customer->{$property});
        $this->assertSame([$expectedIssue], $customer->dataIssues);
        $this->assertTrue($customer->hasDataIssues());
    }

    public function testWrongProductTypesAreRecordedAsIssues(): void
    {
        $notAList = CustomerData::fromArray($this->customerRow(['products' => 'landline']));
        $this->assertSame([], $notAList->products);
        $this->assertSame(['products: expected array, got string ("landline")'], $notAList->dataIssues);

        $mixed = CustomerData::fromArray($this->customerRow(['products' => ['tv', 5]]));
        $this->assertSame(['tv'], $mixed->products);
        $this->assertSame(['products[1]: expected string, got int (5)'], $mixed->dataIssues);
    }

    /**
     * @return array<string, array{string, mixed, string, string}>
     */
    public static function unparsableDatesDataProvider(): array
    {
        return [
            'garbage' => ['dateOfBirth', 'garbage', 'dateOfBirth', 'dateOfBirth: unparsable date ("garbage")'],
            'impossible date' => ['dateOfBirth', '31-02-2000', 'dateOfBirth', 'dateOfBirth: unparsable date ("31-02-2000")'],
            'int' => ['dateOfBirth', 19900101, 'dateOfBirth', 'dateOfBirth: expected string, got int (19900101)'],
            'wrong order' => ['lastInvoiceDate', '2023-01-03', 'lastInvoiceDate', 'lastInvoiceDate: unparsable date ("2023-01-03")'],
            'login without time' => ['lastLoginDateTime', '12-11-2022', 'lastLoginAt', 'lastLoginDateTime: unparsable date ("12-11-2022")'],
        ];
    }

    #[DataProvider('unparsableDatesDataProvider')]
    public function testUnparsableDatesAreRecordedAsIssues(string $key, mixed $value, string $property, string $expectedIssue): void
    {
        $customer = CustomerData::fromArray($this->customerRow([$key => $value]));

        $this->assertNull($customer->{$property});
        $this->assertSame([$expectedIssue], $customer->dataIssues);
    }

    public function testItLogsMalformedFields(): void
    {
        Log::spy();
        $this->fakeCustomers([
            $this->customerRow(['customerId' => 1, 'dateOfBirth' => '31-02-2000']),
            $this->customerRow(['customerId' => 2]),
        ]);

        $customers = $this->client()->fetchAll();

        $this->assertCount(2, $customers);
        Log::shouldHaveReceived('warning')->once()->withArgs(
            fn (string $message, array $context): bool => $context === [
                'count' => 1,
                'issues' => ['customer 1: dateOfBirth: unparsable date ("31-02-2000")'],
            ],
        );
    }

    public function testItDoesNotLogWhenAllFieldsAreWellFormed(): void
    {
        Log::spy();
        $this->fakeCustomers([$this->customerRow()]);

        $this->client()->fetchAll();

        Log::shouldNotHaveReceived('warning');
    }

    public function testDigitStringIdsAreAccepted(): void
    {
        $customer = CustomerData::fromArray($this->customerRow(['customerId' => '26225', 'bsn' => '324971175']));

        $this->assertSame(26225, $customer->externalId);
        $this->assertSame(324971175, $customer->bsn);
        $this->assertSame([], $customer->dataIssues);
    }

    public function testFullNameFallsBackWhenNamesAreMissing(): void
    {
        $this->assertSame('de Haas', CustomerData::fromArray($this->customerRow(['firstName' => null]))->fullName());
        $this->assertSame('Customer #26225', CustomerData::fromArray($this->customerRow(['firstName' => null, 'lastName' => '']))->fullName());
        $this->assertSame('Unknown customer', CustomerData::fromArray([])->fullName());
    }

    public function testItSendsTheRequestToTheConfiguredBaseUrl(): void
    {
        $this->fakeCustomers([]);

        $this->client()->fetchAll();

        Http::assertSent(fn (Request $request): bool => $request->url() === self::BASE_URL.'/customers'
            && $request->method() === 'GET'
            && $request->hasHeader('Accept', 'application/json'));
    }

    public function testItRetriesAServerErrorAndThenSucceeds(): void
    {
        Http::fake(['*' => Http::sequence()
            ->push(['message' => 'Service Unavailable'], 503)
            ->push(['success' => true, 'customers' => [$this->customerRow()]])]);

        $customers = $this->client()->fetchAll();

        $this->assertCount(1, $customers);
        Http::assertSentCount(2);
    }

    public function testItThrowsAfterExhaustingAttemptsOnPersistentServerErrors(): void
    {
        Http::fake(['*' => Http::response(['message' => 'Service Unavailable'], 503)]);

        try {
            $this->client()->fetchAll();
            $this->fail('Expected a CustomersApiException.');
        } catch (CustomersApiException $e) {
            $this->assertSame('The customers API responded with HTTP 503.', $e->getMessage());
            $this->assertSame(503, $e->status);
        }

        Http::assertSentCount(3);
    }

    public function testItDoesNotRetryClientErrors(): void
    {
        Http::fake(['*' => Http::response(['message' => 'Not Found'], 404)]);

        $this->expectExceptionObject(CustomersApiException::httpError(404));

        try {
            $this->client()->fetchAll();
        } finally {
            Http::assertSentCount(1);
        }
    }

    public function testItWrapsConnectionFailures(): void
    {
        Http::fake(['*' => Http::failedConnection('cURL error 7: Failed to connect')]);

        try {
            $this->client()->fetchAll();
            $this->fail('Expected a CustomersApiException.');
        } catch (CustomersApiException $e) {
            $this->assertSame('The customers API could not be reached.', $e->getMessage());
            $this->assertNotNull($e->getPrevious());
        }

        Http::assertSentCount(3);
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function malformedPayloadsDataProvider(): array
    {
        return [
            'not JSON' => ['<html>Oops</html>'],
            'success false' => [['success' => false, 'customers' => []]],
            'success missing' => [['customers' => []]],
            'customers missing' => [['success' => true]],
            'customers not a list' => [['success' => true, 'customers' => ['a' => []]]],
            'customers a string' => [['success' => true, 'customers' => 'none']],
            'row not an object' => [['success' => true, 'customers' => ['oops']]],
        ];
    }

    #[DataProvider('malformedPayloadsDataProvider')]
    public function testItRejectsMalformedPayloads(mixed $body): void
    {
        Http::fake(['*' => Http::response($body)]);

        $this->expectException(CustomersApiException::class);
        $this->expectExceptionMessageIsOrContains('The customers API returned an unexpected response.');

        $this->client()->fetchAll();
    }

    private function client(): CustomersApiClient
    {
        return app(CustomersApiClient::class);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function fakeCustomers(array $rows): void
    {
        Http::fake(['*' => Http::response(['success' => true, 'customers' => $rows])]);
    }

    /**
     * A row built from the example values in the customers API spec.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function customerRow(array $overrides = []): array
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
            'ipAddress' => '114.157.110.110',
            'iban' => 'NL20EYIJ4524255179',
            'lastInvoiceDate' => '03-01-2023',
            'lastLoginDateTime' => '12-11-2022 09:52:36',
        ], $overrides);
    }
}
