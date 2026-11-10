# Resiliens 🛡️

[![CI](https://github.com/mrhamidrezam/resiliens/actions/workflows/ci.yml/badge.svg)](https://github.com/mrhamidrezam/resiliens/actions/workflows/ci.yml)
[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](https://opensource.org/licenses/MIT)
[![PHP Version](https://img.shields.io/badge/PHP-%3E%3D8.2-777bb4.svg)](https://www.php.net/)
[![Zero Dependency](https://img.shields.io/badge/dependencies-0-success.svg)](composer.json)

**Zero-dependency fault-tolerance & resilience toolkit for PHP 8.2+**: Circuit Breaker, Exponential Retry with Full Jitter, Bulkhead Semaphore, Rate Limiter, and Fallback chains.

Designed for microservices, external payment gateways (Stripe, Zarinpal, Bale), LLM API calls, and background workers where failure of remote dependencies must never cascade into full application outages.

---

## ⚡ Key Highlights

- **Zero External Dependencies:** Pure PHP 8.2+ with strict typing (`declare(strict_types=1);`), Enums, and Readonly properties.
- **Circuit Breaker (3 States):** `CLOSED`, `OPEN`, and `HALF_OPEN` with automatic cooldown transitions and atomic file/memory storage.
- **Exponential Retry with Full Jitter:** Prevents the **Thundering Herd** problem during service recovery.
- **Bulkhead Isolation:** Limits concurrent active execution slots to avoid resource exhaustion.
- **Token Bucket Rate Limiter:** Protects downstream endpoints with precision leak and refill rate calculations.
- **Fluent Pipeline Interface:** Compose complex resilience policies with intuitive chainable syntax.
- **Blazing Fast Performance:** Benchmarked at **> 2,000,000 operations/sec** in memory.
- **Built-in CLI Utility:** Inspect circuits and run throughput benchmarks from your terminal.

---

## 📦 Installation

```bash
composer require mrhamidrezam/resiliens
```

*Or drop the `src/` directory directly into your project if you prefer zero-composer standalone usage.*

---

## 🚀 Quickstart

### 1. Unified Pipeline (Retry + Circuit Breaker + Fallback)

```php
use Resiliens\Resiliens;
use Resiliens\Retry\BackoffStrategy;

$response = Resiliens::run(function () use ($api, $orderId) {
    return $api->chargePayment($orderId, 250000);
})
->withRetry(
    maxAttempts: 3,
    baseDelayMs: 150,
    strategy: BackoffStrategy::FULL_JITTER
)
->withCircuitBreaker(
    circuit: 'payment_gateway',
    failureThreshold: 4,
    recoveryTimeoutSeconds: 30.0
)
->fallback(function (Throwable $e) use ($orderId) {
    // Graceful degradation when circuit is open or retries fail
    Logger::warning("Payment delayed for order {$orderId}: " . $e->getMessage());
    return ['status' => 'queued_for_async_processing'];
})
->execute();
```

---

### 2. Standalone Circuit Breaker (Shared Atomic Storage)

When running inside PHP-FPM or multi-process CLI queues, use `FileStorage` to share circuit state across all workers without needing Redis:

```php
use Resiliens\CircuitBreaker\CircuitBreaker;
use Resiliens\CircuitBreaker\FileStorage;
use Resiliens\CircuitBreaker\CircuitBreakerException;

$circuit = new CircuitBreaker(
    serviceName: 'sms_provider',
    failureThreshold: 5,
    recoveryTimeoutSeconds: 60.0,
    storage: new FileStorage() // Persists state atomically across PHP requests
);

try {
    $result = $circuit->call(function () use ($sms, $phone, $text) {
        return $sms->sendVerificationCode($phone, $text);
    });
} catch (CircuitBreakerException $e) {
    // Fails fast in < 0.1ms without calling the broken remote SMS server
    echo "Service temporarily unavailable. Retry after: " . $e->retryAfterSeconds . "s";
}
```

---

### 3. Exponential Backoff with Jitter

```php
use Resiliens\Resiliens;
use Resiliens\Retry\BackoffStrategy;

$data = Resiliens::retry(
    maxAttempts: 4,
    baseDelayMs: 200,
    maxDelayMs: 4000,
    strategy: BackoffStrategy::FULL_JITTER
)
->retryOn(TimeoutException::class, NetworkException::class)
->onRetry(function (int $attempt, Throwable $e, int $delayMs) {
    echo "Attempt {$attempt} failed: {$e->getMessage()}. Retrying in {$delayMs}ms...\n";
})
->execute(function () use ($client) {
    return $client->fetchData();
});
```

---

### 4. Bulkhead Concurrency Semaphore

Prevent slow database queries or slow external webhooks from consuming all PHP worker threads:

```php
use Resiliens\Resiliens;
use Resiliens\Bulkhead\BulkheadRejectedException;

$bulkhead = Resiliens::bulkhead('heavy_report_generator', maxConcurrentCalls: 5);

try {
    $report = $bulkhead->execute(function () use ($reporter) {
        return $reporter->generateAnnualSummary();
    });
} catch (BulkheadRejectedException $e) {
    http_response_code(429);
    echo "Server busy. Too many concurrent reports being generated.";
}
```

---

### 5. Token Bucket Rate Limiter

```php
use Resiliens\Resiliens;

$limiter = Resiliens::rateLimiter('openai_embeddings', capacity: 100, refillRatePerSecond: 10.0);

if ($limiter->tryAcquire(cost: 1)) {
    $embeddings = $ai->embedText($prompt);
} else {
    $waitTime = $limiter->getWaitTimeFor(cost: 1);
    throw new Exception("Rate limit reached. Wait {$waitTime} seconds.");
}
```

---

## 🖥️ Command-Line Interface (CLI)

Resiliens includes a standalone CLI binary:

```bash
# Check circuit state across PHP-FPM workers
php bin/resiliens check payment_gateway

# Reset a tripped circuit back to CLOSED
php bin/resiliens reset payment_gateway

# Benchmark local throughput
php bin/resiliens benchmark
```

---

## 🧪 Testing

Resiliens includes a standalone zero-dependency test suite:

```bash
php tests/run.php
```

---

## 📄 License

MIT License © 2026 Hamidreza ([mrhamidrezam](https://github.com/mrhamidrezam))
