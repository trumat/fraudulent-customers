<?php

namespace App\Support;

/**
 * Builds a display name that is never empty, so messages such as
 * "Same IP as …" can always name the customer even when the upstream
 * API left the name fields out.
 */
final class CustomerName
{
    public static function format(?string $firstName, ?string $lastName, ?int $externalId): string
    {
        $name = trim(implode(' ', array_filter([$firstName, $lastName], fn (?string $part): bool => $part !== null)));

        if ($name !== '') {
            return $name;
        }

        return $externalId !== null ? "Customer #{$externalId}" : 'Unknown customer';
    }
}
