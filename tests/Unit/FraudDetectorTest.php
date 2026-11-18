<?php

namespace Tests\Unit;

use App\Services\CustomersApi\CustomerData;
use App\Services\Fraud\FraudDetector;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class FraudDetectorTest extends TestCase
{
    private FraudDetector $detector;

    private CarbonImmutable $now;

    private int $sequence = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->detector = new FraudDetector;
        $this->now = CarbonImmutable::create(2026, 6, 15, 12);
    }

    public function testACleanSetHasNoReasons(): void
    {
        $reasons = $this->detect([$this->customer(), $this->customer()]);

        $this->assertSame([0 => [], 1 => []], $reasons);
    }

    public function testCustomersSharingAnIpAreBothFlagged(): void
    {
        $reasons = $this->detect([
            $this->customer(['firstName' => 'John', 'lastName' => 'Doe', 'ipAddress' => '127.0.0.1']),
            $this->customer(['firstName' => 'Jane', 'lastName' => 'Roe', 'ipAddress' => '127.0.0.1']),
            $this->customer(),
        ]);

        $this->assertSame(['Same IP as Jane Roe (127.0.0.1)'], $reasons[0]);
        $this->assertSame(['Same IP as John Doe (127.0.0.1)'], $reasons[1]);
        $this->assertSame([], $reasons[2]);
    }

    public function testEachMemberOfALargerGroupNamesAllOthers(): void
    {
        $reasons = $this->detect([
            $this->customer(['firstName' => 'A', 'lastName' => 'One', 'ipAddress' => '10.0.0.1']),
            $this->customer(['firstName' => 'B', 'lastName' => 'Two', 'ipAddress' => '10.0.0.1']),
            $this->customer(['firstName' => 'C', 'lastName' => 'Three', 'ipAddress' => '10.0.0.1']),
        ]);

        $this->assertSame(['Same IP as B Two, C Three (10.0.0.1)'], $reasons[0]);
        $this->assertSame(['Same IP as A One, C Three (10.0.0.1)'], $reasons[1]);
        $this->assertSame(['Same IP as A One, B Two (10.0.0.1)'], $reasons[2]);
    }

    public function testIbansMatchRegardlessOfCaseAndSpacing(): void
    {
        $reasons = $this->detect([
            $this->customer(['firstName' => 'John', 'lastName' => 'Doe', 'iban' => 'NL20EYIJ4524255179']),
            $this->customer(['firstName' => 'Jane', 'lastName' => 'Roe', 'iban' => 'nl20 eyij 4524 2551 79']),
        ]);

        $this->assertSame(['Same IBAN as Jane Roe (NL20EYIJ4524255179)'], $reasons[0]);
        $this->assertSame(['Same IBAN as John Doe (NL20EYIJ4524255179)'], $reasons[1]);
    }

    public function testMissingIpsAndIbansNeverMatch(): void
    {
        $reasons = $this->detect([
            $this->customer(['ipAddress' => null, 'iban' => null]),
            $this->customer(['ipAddress' => null, 'iban' => null]),
        ]);

        $this->assertSame([0 => [], 1 => []], $reasons);
    }

    public function testCustomersWithoutNamesAreNamedByTheFallback(): void
    {
        $reasons = $this->detect([
            $this->customer(['externalId' => 7, 'firstName' => null, 'lastName' => null, 'ipAddress' => '10.0.0.9']),
            $this->customer(['externalId' => 8, 'firstName' => null, 'lastName' => null, 'ipAddress' => '10.0.0.9']),
        ]);

        $this->assertSame(['Same IP as Customer #8 (10.0.0.9)'], $reasons[0]);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function validPhoneNumbersDataProvider(): array
    {
        return [
            'NL mobile' => ['+31612987632'],
            'NL landline' => ['+31201234567'],
        ];
    }

    #[DataProvider('validPhoneNumbersDataProvider')]
    public function testValidPhoneNumbersAreNotFlagged(string $phone): void
    {
        $reasons = $this->detect([$this->customer(['phoneNumber' => $phone])]);

        $this->assertSame([], $reasons[0]);
    }

    public function testAMissingPhoneNumberIsNotFlagged(): void
    {
        $reasons = $this->detect([$this->customer(['phoneNumber' => null])]);

        $this->assertSame([], $reasons[0]);
    }

    #[DataProvider('invalidPhoneNumbersDataProvider')]
    public function testInvalidPhoneNumbersAreFlagged(string $phone): void
    {
        $reasons = $this->detect([$this->customer(['phoneNumber' => $phone])]);

        $this->assertSame(["Non-NL phone number ({$phone})"], $reasons[0]);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidPhoneNumbersDataProvider(): array
    {
        return [
            'NL prefix, 7 digits' => ['+316924303'],
            'NL prefix, 8 digits' => ['+3161234567'],
            'NL prefix, 10 digits' => ['+316123456789'],
            'German' => ['+49061808308'],
            'German, short' => ['+4909758330'],
            'Belgian' => ['+32470123456'],
            'national format' => ['0612345678'],
        ];
    }

    public function testTurningEighteenTodayIsNotUnderage(): void
    {
        $reasons = $this->detect([$this->customer(['dateOfBirth' => CarbonImmutable::create(2008, 6, 15)])]);

        $this->assertSame([], $reasons[0]);
    }

    public function testTurningEighteenTomorrowIsUnderage(): void
    {
        $reasons = $this->detect([$this->customer(['dateOfBirth' => CarbonImmutable::create(2008, 6, 16)])]);

        $this->assertSame(['Customer is under 18 (17)'], $reasons[0]);
    }

    public function testAMissingDateOfBirthIsNotUnderage(): void
    {
        $reasons = $this->detect([$this->customer(['dateOfBirth' => null])]);

        $this->assertSame([], $reasons[0]);
    }

    public function testACustomerGetsEveryMatchingReasonInOrder(): void
    {
        $reasons = $this->detect([
            $this->customer([
                'firstName' => 'John', 'lastName' => 'Doe',
                'ipAddress' => '127.0.0.1', 'iban' => 'NL01BANK0123456789',
                'phoneNumber' => '+49061808308', 'dateOfBirth' => CarbonImmutable::create(2010, 1, 1),
            ]),
            $this->customer([
                'firstName' => 'Jane', 'lastName' => 'Roe',
                'ipAddress' => '127.0.0.1', 'iban' => 'NL01BANK0123456789',
            ]),
        ]);

        $this->assertSame([
            'Same IP as Jane Roe (127.0.0.1)',
            'Same IBAN as Jane Roe (NL01BANK0123456789)',
            'Non-NL phone number (+49061808308)',
            'Customer is under 18 (16)',
        ], $reasons[0]);
    }

    /**
     * @param  list<CustomerData>  $customers
     * @return array<int, list<string>>
     */
    private function detect(array $customers): array
    {
        return $this->detector->detect($customers, $this->now);
    }

    /**
     * A clean adult Dutch customer with a unique IP and IBAN, unless overridden.
     *
     * @param  array<string, mixed>  $overrides
     */
    private function customer(array $overrides = []): CustomerData
    {
        $n = ++$this->sequence;

        $attributes = array_merge([
            'externalId' => $n,
            'bsn' => 100000000 + $n,
            'firstName' => 'Customer',
            'lastName' => (string) $n,
            'dateOfBirth' => CarbonImmutable::create(1980, 1, 1),
            'phoneNumber' => '+31612345678',
            'email' => "customer{$n}@example.com",
            'tag' => null,
            'street' => null,
            'postcode' => null,
            'city' => null,
            'products' => [],
            'ipAddress' => "192.168.0.{$n}",
            'iban' => sprintf('NL00TEST%010d', $n),
            'lastInvoiceDate' => null,
            'lastLoginAt' => null,
        ], $overrides);

        return new CustomerData(...$attributes);
    }
}
