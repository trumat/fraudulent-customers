<?php

namespace App\Services\CustomersApi;

use RuntimeException;
use Throwable;

/**
 * Any failure to get a usable customer list from the customers API.
 *
 * The message is safe to show to users; technical details go into
 * context(), which Laravel adds to the log entry when it is reported.
 */
final class CustomersApiException extends RuntimeException
{
    private function __construct(
        string $message,
        public readonly ?string $detail = null,
        public readonly ?int $status = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public static function unreachable(Throwable $previous): self
    {
        return new self(
            'The customers API could not be reached.',
            detail: $previous->getMessage(),
            previous: $previous,
        );
    }

    public static function httpError(int $status): self
    {
        return new self(
            "The customers API responded with HTTP {$status}.",
            status: $status,
        );
    }

    public static function malformedPayload(string $detail): self
    {
        return new self(
            'The customers API returned an unexpected response.',
            detail: $detail,
        );
    }

    /**
     * @return array{detail: ?string, status: ?int}
     */
    public function context(): array
    {
        return ['detail' => $this->detail, 'status' => $this->status];
    }
}
