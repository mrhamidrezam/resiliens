<?php

declare(strict_types=1);

namespace Resiliens\CircuitBreaker;

class MemoryStorage implements StorageInterface
{
    /** @var array<string, CircuitState> */
    private array $states = [];

    /** @var array<string, int> */
    private array $failures = [];

    /** @var array<string, float> */
    private array $failureTimes = [];

    public function getState(string $service): CircuitState
    {
        return $this->states[$service] ?? CircuitState::CLOSED;
    }

    public function setState(string $service, CircuitState $state): void
    {
        $this->states[$service] = $state;
    }

    public function getFailureCount(string $service): int
    {
        return $this->failures[$service] ?? 0;
    }

    public function incrementFailures(string $service): int
    {
        $count = ($this->failures[$service] ?? 0) + 1;
        $this->failures[$service] = $count;
        return $count;
    }

    public function resetFailures(string $service): void
    {
        $this->failures[$service] = 0;
    }

    public function getLastFailureTime(string $service): float
    {
        return $this->failureTimes[$service] ?? 0.0;
    }

    public function recordFailureTime(string $service, float $timestamp): void
    {
        $this->failureTimes[$service] = $timestamp;
    }
}
