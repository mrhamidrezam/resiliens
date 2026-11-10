<?php

declare(strict_types=1);

use Resiliens\Retry\RetryPolicy;
use Resiliens\Retry\BackoffStrategy;

class RetryTest extends TestCase
{
    public function testSucceedsWithoutRetryOnFirstAttempt(): void
    {
        $policy = new RetryPolicy(maxAttempts: 3, sleepHandler: fn() => null);
        $attempts = 0;

        $res = $policy->execute(function () use (&$attempts) {
            $attempts++;
            return 'ok';
        });

        $this->assertEquals('ok', $res);
        $this->assertEquals(1, $attempts);
    }

    public function testRetriesUntilSuccess(): void
    {
        $policy = new RetryPolicy(maxAttempts: 4, sleepHandler: fn() => null);
        $attempts = 0;

        $res = $policy->execute(function () use (&$attempts) {
            $attempts++;
            if ($attempts < 3) {
                throw new RuntimeException("Transient failure {$attempts}");
            }
            return 'recovered';
        });

        $this->assertEquals('recovered', $res);
        $this->assertEquals(3, $attempts);
    }

    public function testThrowsExceptionWhenMaxAttemptsExceeded(): void
    {
        $policy = new RetryPolicy(maxAttempts: 3, sleepHandler: fn() => null);
        $attempts = 0;

        $this->assertThrows(RuntimeException::class, function () use ($policy, &$attempts) {
            $policy->execute(function () use (&$attempts) {
                $attempts++;
                throw new RuntimeException('Permanent failure');
            });
        });

        $this->assertEquals(3, $attempts);
    }

    public function testSelectiveRetryOnExceptionClass(): void
    {
        $policy = (new RetryPolicy(maxAttempts: 3, sleepHandler: fn() => null))
            ->retryOn(InvalidArgumentException::class);

        $attempts = 0;

        // RuntimeException is not in retryOn list -> fails immediately on attempt 1
        $this->assertThrows(RuntimeException::class, function () use ($policy, &$attempts) {
            $policy->execute(function () use (&$attempts) {
                $attempts++;
                throw new RuntimeException('Not retryable');
            });
        });

        $this->assertEquals(1, $attempts);
    }

    public function testOnRetryHookInvoked(): void
    {
        $retriedAttempts = [];
        $policy = (new RetryPolicy(maxAttempts: 3, sleepHandler: fn() => null))
            ->onRetry(function (int $attempt, Throwable $e, int $delayMs) use (&$retriedAttempts) {
                $retriedAttempts[] = $attempt;
            });

        $attempts = 0;
        try {
            $policy->execute(function () use (&$attempts) {
                $attempts++;
                throw new RuntimeException('err');
            });
        } catch (RuntimeException) {}

        $this->assertEquals([1, 2], $retriedAttempts);
    }
}
