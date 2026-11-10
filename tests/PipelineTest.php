<?php

declare(strict_types=1);

use Resiliens\Resiliens;
use Resiliens\CircuitBreaker\CircuitBreakerException;
use Resiliens\CircuitBreaker\MemoryStorage;

class PipelineTest extends TestCase
{
    public function testPipelineExecutesSuccessfully(): void
    {
        $res = Resiliens::run(fn() => 100)
            ->withRetry(maxAttempts: 2, sleepHandler: fn() => null)
            ->withCircuitBreaker('pipe_test_1', 3)
            ->execute();

        $this->assertEquals(100, $res);
    }

    public function testFallbackHandlerReturnsGracefulDegradation(): void
    {
        $res = Resiliens::run(function () {
            throw new RuntimeException('Primary DB down');
        })
        ->withRetry(maxAttempts: 2, sleepHandler: fn() => null)
        ->fallback(fn(Throwable $e) => ['status' => 'degraded', 'error' => $e->getMessage()])
        ->execute();

        $this->assertEquals(['status' => 'degraded', 'error' => 'Primary DB down'], $res);
    }

    public function testPipelineTripsCircuitBreaker(): void
    {
        $storage = new MemoryStorage();
        $circuit = Resiliens::circuit('pipeline_trip_svc', 2, 60.0, $storage);

        // Fail 2 times
        for ($i = 0; $i < 2; $i++) {
            try {
                Resiliens::run(fn() => throw new RuntimeException("err {$i}"))
                    ->withCircuitBreaker($circuit)
                    ->execute();
            } catch (RuntimeException) {}
        }

        // 3rd time should throw CircuitBreakerException
        $this->assertThrows(CircuitBreakerException::class, function () use ($circuit) {
            Resiliens::run(fn() => 'unreachable')
                ->withCircuitBreaker($circuit)
                ->execute();
        });
    }
}
