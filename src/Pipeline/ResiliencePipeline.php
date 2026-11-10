<?php

declare(strict_types=1);

namespace Resiliens\Pipeline;

use Resiliens\CircuitBreaker\CircuitBreaker;
use Resiliens\CircuitBreaker\CircuitBreakerException;
use Resiliens\RateLimiter\RateLimiter;
use Resiliens\RateLimiter\RateLimitExceededException;
use Resiliens\Bulkhead\Bulkhead;
use Resiliens\Bulkhead\BulkheadRejectedException;
use Resiliens\Retry\RetryPolicy;
use Resiliens\Retry\BackoffStrategy;
use Throwable;

class ResiliencePipeline
{
    /** @var callable(): mixed */
    private $targetOperation;

    private ?CircuitBreaker $circuitBreaker = null;
    private ?RetryPolicy $retryPolicy = null;
    private ?RateLimiter $rateLimiter = null;
    private ?Bulkhead $bulkhead = null;

    /** @var null|callable(Throwable): mixed */
    private $fallbackHandler = null;

    public function __construct(callable $operation)
    {
        $this->targetOperation = $operation;
    }

    public static function create(callable $operation): self
    {
        return new self($operation);
    }

    public function withCircuitBreaker(
        string|CircuitBreaker $circuit,
        int $failureThreshold = 5,
        float $recoveryTimeoutSeconds = 30.0
    ): self {
        if ($circuit instanceof CircuitBreaker) {
            $this->circuitBreaker = $circuit;
        } else {
            $this->circuitBreaker = new CircuitBreaker($circuit, $failureThreshold, $recoveryTimeoutSeconds);
        }
        return $this;
    }

    public function withRetry(
        int $maxAttempts = 3,
        int $baseDelayMs = 100,
        int $maxDelayMs = 5000,
        BackoffStrategy $strategy = BackoffStrategy::FULL_JITTER,
        ?callable $sleepHandler = null
    ): self {
        $this->retryPolicy = new RetryPolicy($maxAttempts, $baseDelayMs, $maxDelayMs, $strategy, 2.0, $sleepHandler);
        return $this;
    }

    public function withRateLimit(
        string|RateLimiter $limiter,
        int $capacity = 60,
        float $refillRatePerSecond = 1.0
    ): self {
        if ($limiter instanceof RateLimiter) {
            $this->rateLimiter = $limiter;
        } else {
            $this->rateLimiter = new RateLimiter($limiter, $capacity, $refillRatePerSecond);
        }
        return $this;
    }

    public function withBulkhead(
        string|Bulkhead $bulkhead,
        int $maxConcurrentCalls = 10
    ): self {
        if ($bulkhead instanceof Bulkhead) {
            $this->bulkhead = $bulkhead;
        } else {
            $this->bulkhead = new Bulkhead($bulkhead, $maxConcurrentCalls);
        }
        return $this;
    }

    /**
     * @param callable(Throwable): mixed $fallback
     */
    public function fallback(callable $fallback): self
    {
        $this->fallbackHandler = $fallback;
        return $this;
    }

    public function execute(): mixed
    {
        $action = $this->targetOperation;

        // Wrap with Circuit Breaker
        if ($this->circuitBreaker !== null) {
            $cb = $this->circuitBreaker;
            $inner = $action;
            $action = static fn() => $cb->call($inner);
        }

        // Wrap with Rate Limiter
        if ($this->rateLimiter !== null) {
            $rl = $this->rateLimiter;
            $inner = $action;
            $action = static fn() => $rl->execute($inner);
        }

        // Wrap with Bulkhead
        if ($this->bulkhead !== null) {
            $bh = $this->bulkhead;
            $inner = $action;
            $action = static fn() => $bh->execute($inner);
        }

        // Wrap with Retry Policy
        if ($this->retryPolicy !== null) {
            $rp = $this->retryPolicy;
            $inner = $action;
            $action = static fn() => $rp->execute($inner);
        }

        try {
            return $action();
        } catch (Throwable $e) {
            if ($this->fallbackHandler !== null) {
                return ($this->fallbackHandler)($e);
            }
            throw $e;
        }
    }
}
