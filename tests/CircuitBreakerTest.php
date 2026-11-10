<?php

declare(strict_types=1);

use Resiliens\CircuitBreaker\CircuitBreaker;
use Resiliens\CircuitBreaker\CircuitBreakerException;
use Resiliens\CircuitBreaker\CircuitState;
use Resiliens\CircuitBreaker\FileStorage;
use Resiliens\CircuitBreaker\MemoryStorage;

class CircuitBreakerTest extends TestCase
{
    public function testInitialStateIsClosed(): void
    {
        $cb = new CircuitBreaker('test_service_1', 3, 10.0, new MemoryStorage());
        $this->assertEquals(CircuitState::CLOSED, $cb->getState());
        $this->assertTrue($cb->isAvailable());
    }

    public function testSuccessfulCallsExecuteCleanly(): void
    {
        $cb = new CircuitBreaker('test_service_2', 3, 10.0, new MemoryStorage());
        $result = $cb->call(fn() => 'success');
        $this->assertEquals('success', $result);
        $this->assertEquals(CircuitState::CLOSED, $cb->getState());
    }

    public function testTripsToOpenAfterThresholdFailures(): void
    {
        $cb = new CircuitBreaker('test_service_3', 2, 10.0, new MemoryStorage());

        // First failure
        try {
            $cb->call(fn() => throw new RuntimeException('fail 1'));
        } catch (RuntimeException) {}

        $this->assertEquals(CircuitState::CLOSED, $cb->getState());

        // Second failure - trips circuit
        try {
            $cb->call(fn() => throw new RuntimeException('fail 2'));
        } catch (RuntimeException) {}

        $this->assertEquals(CircuitState::OPEN, $cb->getState());
        $this->assertFalse($cb->isAvailable());

        // Subsequent call immediately rejected with CircuitBreakerException
        $this->assertThrows(CircuitBreakerException::class, fn() => $cb->call(fn() => 'never_called'));
    }

    public function testHalfOpenRecoveryTransition(): void
    {
        // Recovery timeout of 0.05 seconds (50ms)
        $cb = new CircuitBreaker('test_service_4', 1, 0.05, new MemoryStorage());

        try {
            $cb->call(fn() => throw new RuntimeException('fail'));
        } catch (RuntimeException) {}

        $this->assertEquals(CircuitState::OPEN, $cb->getState());

        // Wait for cooldown
        usleep(60000); // 60ms

        $this->assertTrue($cb->isAvailable());
        $this->assertEquals(CircuitState::HALF_OPEN, $cb->getState());

        // Successful trial closes the circuit
        $res = $cb->call(fn() => 'recovered');
        $this->assertEquals('recovered', $res);
        $this->assertEquals(CircuitState::CLOSED, $cb->getState());
    }

    public function testFileStoragePersistence(): void
    {
        $tmpDir = sys_get_temp_dir() . '/resiliens_test_' . uniqid();
        $storage = new FileStorage($tmpDir);

        $cb1 = new CircuitBreaker('shared_service', 1, 10.0, $storage);
        try {
            $cb1->call(fn() => throw new RuntimeException('err'));
        } catch (RuntimeException) {}

        // New instance with same storage should see OPEN state
        $cb2 = new CircuitBreaker('shared_service', 1, 10.0, $storage);
        $this->assertEquals(CircuitState::OPEN, $cb2->getState());
        $this->assertFalse($cb2->isAvailable());

        // Clean up
        @array_map('unlink', glob($tmpDir . '/*.*'));
        @rmdir($tmpDir);
    }
}
