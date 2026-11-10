<?php

declare(strict_types=1);

namespace Resiliens;

use Resiliens\CircuitBreaker\CircuitBreaker;
use Resiliens\CircuitBreaker\StorageInterface;
use Resiliens\CircuitBreaker\FileStorage;
use Resiliens\CircuitBreaker\MemoryStorage;
use Resiliens\Pipeline\ResiliencePipeline;
use Resiliens\RateLimiter\RateLimiter;
use Resiliens\Bulkhead\Bulkhead;
use Resiliens\Retry\RetryPolicy;
use Resiliens\Retry\BackoffStrategy;

class Resiliens
{
    public const VERSION = '0.1.0';

    /** @var array<string, CircuitBreaker> */
    private static array $circuits = [];

    /** @var array<string, RateLimiter> */
    private static array $limiters = [];

    /** @var array<string, Bulkhead> */
    private static array $bulkheads = [];

    private static ?StorageInterface $defaultStorage = null;

    public static function setDefaultStorage(StorageInterface $storage): void
    {
        self::$defaultStorage = $storage;
    }

    public static function run(callable $operation): ResiliencePipeline
    {
        return new ResiliencePipeline($operation);
    }

    public static function circuit(
        string $serviceName,
        int $failureThreshold = 5,
        float $recoveryTimeoutSeconds = 30.0,
        ?StorageInterface $storage = null
    ): CircuitBreaker {
        if (!isset(self::$circuits[$serviceName])) {
            $effectiveStorage = $storage ?? self::$defaultStorage ?? new MemoryStorage();
            self::$circuits[$serviceName] = new CircuitBreaker(
                $serviceName,
                $failureThreshold,
                $recoveryTimeoutSeconds,
                $effectiveStorage
            );
        }

        return self::$circuits[$serviceName];
    }

    public static function retry(
        int $maxAttempts = 3,
        int $baseDelayMs = 100,
        int $maxDelayMs = 5000,
        BackoffStrategy $strategy = BackoffStrategy::FULL_JITTER,
        ?callable $sleepHandler = null
    ): RetryPolicy {
        return new RetryPolicy($maxAttempts, $baseDelayMs, $maxDelayMs, $strategy, 2.0, $sleepHandler);
    }

    public static function rateLimiter(
        string $name,
        int $capacity = 60,
        float $refillRatePerSecond = 1.0
    ): RateLimiter {
        if (!isset(self::$limiters[$name])) {
            self::$limiters[$name] = new RateLimiter($name, $capacity, $refillRatePerSecond);
        }
        return self::$limiters[$name];
    }

    public static function bulkhead(
        string $name,
        int $maxConcurrentCalls = 10
    ): Bulkhead {
        if (!isset(self::$bulkheads[$name])) {
            self::$bulkheads[$name] = new Bulkhead($name, $maxConcurrentCalls);
        }
        return self::$bulkheads[$name];
    }

    public static function clearRegistry(): void
    {
        self::$circuits = [];
        self::$limiters = [];
        self::$bulkheads = [];
    }
}
