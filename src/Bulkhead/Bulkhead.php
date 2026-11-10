<?php

declare(strict_types=1);

namespace Resiliens\Bulkhead;

use Throwable;

class Bulkhead
{
    private int $currentActive = 0;

    public function __construct(
        public readonly string $name,
        public readonly int $maxConcurrentCalls
    ) {
    }

    public function getActiveCalls(): int
    {
        return $this->currentActive;
    }

    public function getAvailableCapacity(): int
    {
        return max(0, $this->maxConcurrentCalls - $this->currentActive);
    }

    public function isAvailable(): bool
    {
        return $this->currentActive < $this->maxConcurrentCalls;
    }

    /**
     * @template T
     * @param callable(): T $operation
     * @return T
     * @throws BulkheadRejectedException|Throwable
     */
    public function execute(callable $operation): mixed
    {
        if (!$this->isAvailable()) {
            throw new BulkheadRejectedException($this->name, $this->maxConcurrentCalls);
        }

        $this->currentActive++;
        try {
            return $operation();
        } finally {
            $this->currentActive--;
        }
    }
}
