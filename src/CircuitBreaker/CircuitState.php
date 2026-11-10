<?php

declare(strict_types=1);

namespace Resiliens\CircuitBreaker;

enum CircuitState: string
{
    case CLOSED = 'closed';
    case OPEN = 'open';
    case HALF_OPEN = 'half_open';
}
