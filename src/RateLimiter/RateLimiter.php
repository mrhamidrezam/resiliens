<?php

declare(strict_types=1);

namespace Resiliens\RateLimiter;

class RateLimiter
{
    private float $tokens;
    private float $lastRefillTimestamp;

    public function __construct(
        public readonly string $name,
        public readonly int $capacity,
        public readonly float $refillRatePerSecond
    ) {
        $this->tokens = (float)$capacity;
        $this->lastRefillTimestamp = microtime(true);
    }

    private function refill(): void
    {
        $now = microtime(true);
        $elapsed = $now - $this->lastRefillTimestamp;
        $this->lastRefillTimestamp = $now;

        $this->tokens = min((float)$this->capacity, $this->tokens + ($elapsed * $this->refillRatePerSecond));
    }

    public function getAvailableTokens(): float
    {
        $this->refill();
        return $this->tokens;
    }

    public function tryAcquire(int $cost = 1): bool
    {
        $this->refill();

        if ($this->tokens >= $cost) {
            $this->tokens -= $cost;
            return true;
        }

        return false;
    }

    public function getWaitTimeFor(int $cost = 1): float
    {
        $this->refill();
        if ($this->tokens >= $cost) {
            return 0.0;
        }

        $needed = $cost - $this->tokens;
        return $needed / $this->refillRatePerSecond;
    }

    /**
     * @template T
     * @param callable(): T $operation
     * @param int $cost
     * @return T
     * @throws RateLimitExceededException
     */
    public function execute(callable $operation, int $cost = 1): mixed
    {
        if (!$this->tryAcquire($cost)) {
            throw new RateLimitExceededException($this->name, $this->getWaitTimeFor($cost));
        }

        return $operation();
    }
}
