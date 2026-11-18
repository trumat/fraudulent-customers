<?php

namespace App\Services\Fraud;

use App\Services\CustomersApi\CustomerData;
use Carbon\CarbonImmutable;
use Closure;

final class FraudDetector
{
    private const string DUTCH_PHONE = '/^\+31\d{9}\z/';

    private const int ADULT_AGE = 18;

    /**
     * @param  list<CustomerData>  $customers
     * @return array<int, list<string>> reason messages keyed by input index; [] for a clean customer
     */
    public function detect(array $customers, CarbonImmutable $now): array
    {
        $sharedIp = $this->sharedValueReasons($customers, 'IP', fn (CustomerData $c): ?string => $c->ipAddress);
        $sharedIban = $this->sharedValueReasons($customers, 'IBAN', fn (CustomerData $c): ?string => $c->iban === null
            ? null
            : strtoupper((string) preg_replace('/\s+/', '', $c->iban)));

        $reasons = [];

        foreach ($customers as $index => $customer) {
            $reasons[$index] = array_values(array_filter([
                $sharedIp[$index] ?? null,
                $sharedIban[$index] ?? null,
                $this->foreignPhoneReason($customer),
                $this->underageReason($customer, $now),
            ], fn (?string $reason): bool => $reason !== null));
        }

        return $reasons;
    }

    private function foreignPhoneReason(CustomerData $customer): ?string
    {
        $phone = $customer->phoneNumber;

        if ($phone === null || preg_match(self::DUTCH_PHONE, $phone) === 1) {
            return null;
        }

        return "Non-NL phone number ({$phone})";
    }

    private function underageReason(CustomerData $customer, CarbonImmutable $now): ?string
    {
        $dob = $customer->dateOfBirth;

        if ($dob === null || !$dob->addYears(self::ADULT_AGE)->isAfter($now)) {
            return null;
        }

        $age = max(0, (int) $dob->diffInYears($now));

        return "Customer is under 18 ({$age})";
    }

    /**
     * Every customer in a group of two or more with the same key gets one
     * message naming the *other* members of the group.
     *
     * @param  list<CustomerData>  $customers
     * @param  Closure(CustomerData): ?string  $key
     * @return array<int, string>
     */
    private function sharedValueReasons(array $customers, string $label, Closure $key): array
    {
        /** @var array<string, list<int>> $groups */
        $groups = [];

        foreach ($customers as $index => $customer) {
            $value = $key($customer);

            if ($value !== null && $value !== '') {
                $groups[$value][] = $index;
            }
        }

        $messages = [];

        foreach ($groups as $value => $indices) {
            if (count($indices) < 2) {
                continue;
            }

            foreach ($indices as $index) {
                $others = array_map(
                    fn (int $other): string => $customers[$other]->fullName(),
                    array_values(array_filter($indices, fn (int $other): bool => $other !== $index)),
                );

                $messages[$index] = sprintf('Same %s as %s (%s)', $label, implode(', ', $others), $value);
            }
        }

        return $messages;
    }
}
