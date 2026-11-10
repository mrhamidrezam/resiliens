<?php

declare(strict_types=1);

namespace Resiliens\CircuitBreaker;

use RuntimeException;

class CircuitBreakerException extends RuntimeException
{
    public function __construct(
        string $serviceName,
        public readonly float $retryAfterSeconds = 0.0
    ) {
        parent::__construct(
            sprintf("Circuit breaker for service '%s' is OPEN. Calls are blocked for %.1f seconds.", $serviceName, $retryAfterSeconds)
        );
    }
}
