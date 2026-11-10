<?php

declare(strict_types=1);

if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
} else {
    require_once __DIR__ . '/../src/CircuitBreaker/CircuitState.php';
    require_once __DIR__ . '/../src/CircuitBreaker/CircuitBreakerException.php';
    require_once __DIR__ . '/../src/CircuitBreaker/StorageInterface.php';
    require_once __DIR__ . '/../src/CircuitBreaker/MemoryStorage.php';
    require_once __DIR__ . '/../src/CircuitBreaker/FileStorage.php';
    require_once __DIR__ . '/../src/CircuitBreaker/CircuitBreaker.php';
    require_once __DIR__ . '/../src/Retry/BackoffStrategy.php';
    require_once __DIR__ . '/../src/Retry/RetryPolicy.php';
    require_once __DIR__ . '/../src/RateLimiter/RateLimitExceededException.php';
    require_once __DIR__ . '/../src/RateLimiter/RateLimiter.php';
    require_once __DIR__ . '/../src/Bulkhead/BulkheadRejectedException.php';
    require_once __DIR__ . '/../src/Bulkhead/Bulkhead.php';
    require_once __DIR__ . '/../src/Pipeline/ResiliencePipeline.php';
    require_once __DIR__ . '/../src/Resiliens.php';
}

use Resiliens\Resiliens;
use Resiliens\Retry\BackoffStrategy;

echo "=== Resiliens Usage Showcase ===\n\n";

// 1. Fluent Pipeline (Retry + Circuit Breaker + Fallback)
echo "1. Executing Pipeline with Fallback:\n";
$result = Resiliens::run(function () {
    // Simulate failing microservice / external API
    throw new RuntimeException("Payment Gateway Timeout (504)");
})
->withRetry(maxAttempts: 3, strategy: BackoffStrategy::FULL_JITTER)
->withCircuitBreaker('payment_gateway', failureThreshold: 3, recoveryTimeoutSeconds: 15.0)
->fallback(function (Throwable $e) {
    echo "  [Fallback triggered] " . $e->getMessage() . "\n";
    return ['status' => 'queued_for_later', 'retry_mode' => 'async'];
})
->execute();

var_dump($result);

// 2. Rate Limiting Example
echo "\n2. Rate Limiter (Token Bucket):\n";
$limiter = Resiliens::rateLimiter('sms_api', capacity: 2, refillRatePerSecond: 1.0);
echo "  Attempt 1: " . ($limiter->tryAcquire() ? "ALLOW" : "BLOCK") . "\n";
echo "  Attempt 2: " . ($limiter->tryAcquire() ? "ALLOW" : "BLOCK") . "\n";
echo "  Attempt 3: " . ($limiter->tryAcquire() ? "ALLOW" : "BLOCK") . "\n";

echo "\nDone!\n";
