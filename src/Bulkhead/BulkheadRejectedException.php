<?php

declare(strict_types=1);

namespace Resiliens\Bulkhead;

use RuntimeException;

class BulkheadRejectedException extends RuntimeException
{
    public function __construct(string $name, int $maxConcurrent)
    {
        parent::__construct(
            sprintf("Bulkhead '%s' capacity saturated (max concurrent limit: %d).", $name, $maxConcurrent)
        );
    }
}
