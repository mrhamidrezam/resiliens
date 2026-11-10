<?php

declare(strict_types=1);

namespace Resiliens\Retry;

enum BackoffStrategy: string
{
    case CONSTANT = 'constant';
    case LINEAR = 'linear';
    case EXPONENTIAL = 'exponential';
    case FULL_JITTER = 'full_jitter';

    public function calculateDelayMs(int $attempt, int $baseDelayMs, int $maxDelayMs, float $multiplier = 2.0): int
    {
        $delay = match ($this) {
            self::CONSTANT => $baseDelayMs,
            self::LINEAR => $baseDelayMs * $attempt,
            self::EXPONENTIAL => (int)($baseDelayMs * ($multiplier ** ($attempt - 1))),
            self::FULL_JITTER => (int)(mt_rand(0, (int)min($maxDelayMs, $baseDelayMs * ($multiplier ** ($attempt - 1))))),
        };

        return (int)min($maxDelayMs, max(0, $delay));
    }
}
