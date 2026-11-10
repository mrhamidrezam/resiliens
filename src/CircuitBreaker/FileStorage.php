<?php

declare(strict_types=1);

namespace Resiliens\CircuitBreaker;

class FileStorage implements StorageInterface
{
    private string $directory;

    public function __construct(?string $directory = null)
    {
        $this->directory = rtrim($directory ?? sys_get_temp_dir() . '/resiliens_circuits', '/\\');
        if (!is_dir($this->directory)) {
            @mkdir($this->directory, 0777, true);
        }
    }

    private function getFilePath(string $service): string
    {
        $hash = md5($service);
        return $this->directory . "/circuit_{$hash}.json";
    }

    /** @return array{state: string, failures: int, lastFailureTime: float} */
    private function readData(string $service): array
    {
        $path = $this->getFilePath($service);
        if (!file_exists($path)) {
            return ['state' => CircuitState::CLOSED->value, 'failures' => 0, 'lastFailureTime' => 0.0];
        }

        $content = @file_get_contents($path);
        if ($content === false) {
            return ['state' => CircuitState::CLOSED->value, 'failures' => 0, 'lastFailureTime' => 0.0];
        }

        $decoded = json_decode($content, true);
        if (!is_array($decoded)) {
            return ['state' => CircuitState::CLOSED->value, 'failures' => 0, 'lastFailureTime' => 0.0];
        }

        return [
            'state' => (string)($decoded['state'] ?? CircuitState::CLOSED->value),
            'failures' => (int)($decoded['failures'] ?? 0),
            'lastFailureTime' => (float)($decoded['lastFailureTime'] ?? 0.0),
        ];
    }

    /** @param array{state: string, failures: int, lastFailureTime: float} $data */
    private function writeData(string $service, array $data): void
    {
        $path = $this->getFilePath($service);
        $encoded = json_encode($data);
        @file_put_contents($path, $encoded, LOCK_EX);
    }

    public function getState(string $service): CircuitState
    {
        $data = $this->readData($service);
        return CircuitState::tryFrom($data['state']) ?? CircuitState::CLOSED;
    }

    public function setState(string $service, CircuitState $state): void
    {
        $data = $this->readData($service);
        $data['state'] = $state->value;
        $this->writeData($service, $data);
    }

    public function getFailureCount(string $service): int
    {
        return $this->readData($service)['failures'];
    }

    public function incrementFailures(string $service): int
    {
        $data = $this->readData($service);
        $data['failures']++;
        $this->writeData($service, $data);
        return $data['failures'];
    }

    public function resetFailures(string $service): void
    {
        $data = $this->readData($service);
        $data['failures'] = 0;
        $this->writeData($service, $data);
    }

    public function getLastFailureTime(string $service): float
    {
        return $this->readData($service)['lastFailureTime'];
    }

    public function recordFailureTime(string $service, float $timestamp): void
    {
        $data = $this->readData($service);
        $data['lastFailureTime'] = $timestamp;
        $this->writeData($service, $data);
    }
}
