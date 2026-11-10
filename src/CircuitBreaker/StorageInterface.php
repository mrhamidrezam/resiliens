<?php

declare(strict_types=1);

namespace Resiliens\CircuitBreaker;

interface StorageInterface
{
    public function getState(string $service): CircuitState;
    public function setState(string $service, CircuitState $state): void;
    public function getFailureCount(string $service): int;
    public function incrementFailures(string $service): int;
    public function resetFailures(string $service): void;
    public function getLastFailureTime(string $service): float;
    public function recordFailureTime(string $service, float $timestamp): void;
}
