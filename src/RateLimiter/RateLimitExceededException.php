<?php

declare(strict_types=1);

namespace Resiliens\RateLimiter;

use RuntimeException;

class RateLimitExceededException extends RuntimeException
{
    public function __construct(
        string $bucketName,
        public readonly float $retryAfterSeconds
    ) {
        parent::__construct(
            sprintf("Rate limit exceeded for '%s'. Retry after %.2f seconds.", $bucketName, $retryAfterSeconds)
        );
    }
}
