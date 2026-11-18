<?php

namespace App\Services\CustomersApi;

use App\Support\CustomerName;
use Carbon\CarbonImmutable;
use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Support\Str;

/**
 * One customer as returned by the customers API.
 *
 * The upstream spec marks no field as required, so every scalar is nullable
 * and null always means "absent".
 */
final readonly class CustomerData
{
    /**
     * @param  list<string>  $products
     * @param  list<string>  $dataIssues
     */
    public function __construct(
        public ?int $externalId,
        public ?int $bsn,
        public ?string $firstName,
        public ?string $lastName,
        public ?CarbonImmutable $dateOfBirth,
        public ?string $phoneNumber,
        public ?string $email,
        public ?string $tag,
        public ?string $street,
        public ?string $postcode,
        public ?string $city,
        public array $products,
        public ?string $ipAddress,
        public ?string $iban,
        public ?CarbonImmutable $lastInvoiceDate,
        public ?CarbonImmutable $lastLoginAt,
        public array $dataIssues = [],
    ) {}

    /**
     * @param  array<array-key, mixed>  $row
     */
    public static function fromArray(array $row): self
    {
        /** @var list<string> $issues */
        $issues = [];

        $address = self::address($row, $issues);

        return new self(
            externalId: self::integer($row, 'customerId', $issues),
            bsn: self::integer($row, 'bsn', $issues),
            firstName: self::string($row, 'firstName', $issues),
            lastName: self::string($row, 'lastName', $issues),
            dateOfBirth: self::date($row, 'dateOfBirth', '!d-m-Y', $issues),
            phoneNumber: self::string($row, 'phoneNumber', $issues),
            email: self::string($row, 'email', $issues),
            tag: self::string($row, 'tag', $issues),
            street: self::string($address, 'street', $issues, 'address.street'),
            postcode: self::string($address, 'postcode', $issues, 'address.postcode'),
            city: self::string($address, 'city', $issues, 'address.city'),
            products: self::products($row, $issues),
            ipAddress: self::string($row, 'ipAddress', $issues),
            iban: self::string($row, 'iban', $issues),
            lastInvoiceDate: self::date($row, 'lastInvoiceDate', '!d-m-Y', $issues),
            lastLoginAt: self::date($row, 'lastLoginDateTime', '!d-m-Y H:i:s', $issues),
            // Arguments are evaluated in order, so every issue above is collected by now.
            dataIssues: $issues,
        );
    }

    public function fullName(): string
    {
        return CustomerName::format($this->firstName, $this->lastName, $this->externalId);
    }

    public function hasDataIssues(): bool
    {
        return $this->dataIssues !== [];
    }

    /**
     * @param  array<array-key, mixed>  $source
     * @param  list<string>  $issues
     */
    private static function string(array $source, string $key, array &$issues, ?string $field = null): ?string
    {
        $value = $source[$key] ?? null;

        if ($value === null) {
            return null;
        }

        if (!is_string($value)) {
            $issues[] = self::issue($field ?? $key, 'expected string, got '.get_debug_type($value), $value);

            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    /**
     * Accepts an int, or a string of digits (some encoders send large ints as strings).
     *
     * @param  array<array-key, mixed>  $source
     * @param  list<string>  $issues
     */
    private static function integer(array $source, string $key, array &$issues): ?int
    {
        $value = $source[$key] ?? null;

        if ($value === null || (is_string($value) && trim($value) === '')) {
            return null;
        }

        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && ctype_digit($value)) {
            return (int) $value;
        }

        $issues[] = self::issue($key, 'expected integer, got '.get_debug_type($value), $value);

        return null;
    }

    /**
     * @param  array<array-key, mixed>  $row
     * @param  list<string>  $issues
     * @return array<array-key, mixed>
     */
    private static function address(array $row, array &$issues): array
    {
        $value = $row['address'] ?? null;

        if ($value === null) {
            return [];
        }

        if (!is_array($value)) {
            $issues[] = self::issue('address', 'expected object, got '.get_debug_type($value), $value);

            return [];
        }

        return $value;
    }

    /**
     * @param  array<array-key, mixed>  $row
     * @param  list<string>  $issues
     * @return list<string>
     */
    private static function products(array $row, array &$issues): array
    {
        $value = $row['products'] ?? null;

        if ($value === null) {
            return [];
        }

        if (!is_array($value)) {
            $issues[] = self::issue('products', 'expected array, got '.get_debug_type($value), $value);

            return [];
        }

        $products = [];

        foreach ($value as $index => $product) {
            if (!is_string($product)) {
                $issues[] = self::issue("products[{$index}]", 'expected string, got '.get_debug_type($product), $product);

                continue;
            }

            if (trim($product) !== '') {
                $products[] = trim($product);
            }
        }

        return $products;
    }

    /**
     * Parses strictly: the parsed date is formatted back and compared with the
     * input, so an impossible date such as 31-02-2000 is rejected instead of
     * silently rolling over to March.
     *
     * @param  array<array-key, mixed>  $row
     * @param  list<string>  $issues
     */
    private static function date(array $row, string $key, string $format, array &$issues): ?CarbonImmutable
    {
        $value = self::string($row, $key, $issues);

        if ($value === null) {
            return null;
        }

        try {
            $date = CarbonImmutable::createFromFormat($format, $value);
        } catch (InvalidFormatException) {
            $date = null;
        }

        if (!$date instanceof CarbonImmutable || $date->format(ltrim($format, '!')) !== $value) {
            $issues[] = self::issue($key, 'unparsable date', $value);

            return null;
        }

        return $date;
    }

    private static function issue(string $field, string $problem, mixed $value): string
    {
        $shown = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: get_debug_type($value);

        return sprintf('%s: %s (%s)', $field, $problem, Str::limit($shown, 40));
    }
}
