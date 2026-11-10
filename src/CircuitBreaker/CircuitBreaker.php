<?php

declare(strict_types=1);

namespace Resiliens\CircuitBreaker;

use Throwable;

class CircuitBreaker
{
    public function __construct(
        public readonly string $serviceName,
        public readonly int $failureThreshold = 5,
        public readonly float $recoveryTimeoutSeconds = 30.0,
        private ?StorageInterface $storage = null
    ) {
        $this->storage = $storage ?? new MemoryStorage();
    }

    public function isAvailable(): bool
    {
        $state = $this->storage->getState($this->serviceName);

        if ($state === CircuitState::CLOSED) {
            return true;
        }

        if ($state === CircuitState::OPEN) {
            $lastFailure = $this->storage->getLastFailureTime($this->serviceName);
            $elapsed = microtime(true) - $lastFailure;

            if ($elapsed >= $this->recoveryTimeoutSeconds) {
                $this->storage->setState($this->serviceName, CircuitState::HALF_OPEN);
                return true;
            }

            return false;
        }

        // HALF_OPEN allows trial call
        return true;
    }

    public function getRemainingTimeout(): float
    {
        $lastFailure = $this->storage->getLastFailureTime($this->serviceName);
        $elapsed = microtime(true) - $lastFailure;
        return max(0.0, $this->recoveryTimeoutSeconds - $elapsed);
    }

    public function getState(): CircuitState
    {
        // Check if transition to half_open is due
        $this->isAvailable();
        return $this->storage->getState($this->serviceName);
    }

    public function recordSuccess(): void
    {
        $this->storage->resetFailures($this->serviceName);
        $this->storage->setState($this->serviceName, CircuitState::CLOSED);
    }

    public function recordFailure(): void
    {
        $failures = $this->storage->incrementFailures($this->serviceName);
        $this->storage->recordFailureTime($this->serviceName, microtime(true));

        if ($failures >= $this->failureThreshold) {
            $this->storage->setState($this->serviceName, CircuitState::OPEN);
        }
    }

    /**
     * @template T
     * @param callable(): T $operation
     * @return T
     * @throws CircuitBreakerException|Throwable
     */
    public function call(callable $operation): mixed
    {
        if (!$this->isAvailable()) {
            throw new CircuitBreakerException($this->serviceName, $this->getRemainingTimeout());
        }

        try {
            $result = $operation();
            $this->recordSuccess();
            return $result;
        } catch (Throwable $e) {
            $this->recordFailure();
            throw $e;
        }
    }
}
