<?php

declare(strict_types=1);

use Resiliens\RateLimiter\RateLimiter;
use Resiliens\RateLimiter\RateLimitExceededException;

class RateLimiterTest extends TestCase
{
    public function testAllowsWithinCapacity(): void
    {
        $limiter = new RateLimiter('api_limit', 3, 10.0);
        $this->assertTrue($limiter->tryAcquire());
        $this->assertTrue($limiter->tryAcquire());
        $this->assertTrue($limiter->tryAcquire());
        $this->assertFalse($limiter->tryAcquire());
    }

    public function testThrowsExceptionWhenLimitExceeded(): void
    {
        $limiter = new RateLimiter('strict_limit', 1, 1.0);
        $limiter->tryAcquire();

        $this->assertThrows(RateLimitExceededException::class, function () use ($limiter) {
            $limiter->execute(fn() => 'blocked');
        });
    }

    public function testRefillsTokensOverTime(): void
    {
        // 1 token capacity, refills at 100 tokens per second (1 token per 10ms)
        $limiter = new RateLimiter('fast_refill', 1, 100.0);
        $this->assertTrue($limiter->tryAcquire());
        $this->assertFalse($limiter->tryAcquire());

        usleep(15000); // 15ms -> 1.5 tokens refilled
        $this->assertTrue($limiter->tryAcquire());
    }
}
