<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/CircuitBreaker/CircuitState.php';
require_once __DIR__ . '/../src/CircuitBreaker/CircuitBreakerException.php';
require_once __DIR__ . '/../src/CircuitBreaker/StorageInterface.php';
require_once __DIR__ . '/../src/CircuitBreaker/MemoryStorage.php';
require_once __DIR__ . '/../src/CircuitBreaker/FileStorage.php';
require_once __DIR__ . '/../src/CircuitBreaker/CircuitBreaker.php';
require_once __DIR__ . '/../src/Retry/BackoffStrategy.php';
require_once __DIR__ . '/../src/Retry/RetryPolicy.php';
require_once __DIR__ . '/../src/RateLimiter/RateLimitExceededException.php';
require_once __DIR__ . '/../src/RateLimiter/RateLimiter.php';
require_once __DIR__ . '/../src/Bulkhead/BulkheadRejectedException.php';
require_once __DIR__ . '/../src/Bulkhead/Bulkhead.php';
require_once __DIR__ . '/../src/Pipeline/ResiliencePipeline.php';
require_once __DIR__ . '/../src/Resiliens.php';

abstract class TestCase
{
    private int $assertions = 0;

    public function getAssertionsCount(): int
    {
        return $this->assertions;
    }

    protected function assertTrue(bool $condition, string $message = 'Failed asserting that condition is true.'): void
    {
        $this->assertions++;
        if (!$condition) {
            throw new AssertionError($message);
        }
    }

    protected function assertFalse(bool $condition, string $message = 'Failed asserting that condition is false.'): void
    {
        $this->assertions++;
        if ($condition) {
            throw new AssertionError($message);
        }
    }

    protected function assertEquals(mixed $expected, mixed $actual, string $message = ''): void
    {
        $this->assertions++;
        if ($expected !== $actual) {
            $msg = $message ?: sprintf('Failed asserting that %s matches expected %s', var_export($actual, true), var_export($expected, true));
            throw new AssertionError($msg);
        }
    }

    protected function assertThrows(string $expectedException, callable $action): Throwable
    {
        $this->assertions++;
        try {
            $action();
        } catch (Throwable $e) {
            if ($e instanceof $expectedException) {
                return $e;
            }
            throw new AssertionError(sprintf('Expected %s but caught %s: %s', $expectedException, get_class($e), $e->getMessage()));
        }

        throw new AssertionError(sprintf('Failed asserting that exception %s was thrown.', $expectedException));
    }
}

echo "\033[1;36m=== Resiliens Test Suite ===\033[0m\n";

$testFiles = glob(__DIR__ . '/*Test.php');
$totalTests = 0;
$totalAssertions = 0;
$failures = [];

$startAll = microtime(true);

foreach ($testFiles as $file) {
    require_once $file;
    $className = pathinfo($file, PATHINFO_FILENAME);
    if (!class_exists($className)) {
        continue;
    }

    echo "► Running \033[1m{$className}\033[0m\n";
    $ref = new ReflectionClass($className);
    $methods = $ref->getMethods(ReflectionMethod::IS_PUBLIC);

    foreach ($methods as $method) {
        if (!str_starts_with($method->getName(), 'test')) {
            continue;
        }

        $testCase = new $className();
        $totalTests++;

        try {
            $testCase->{$method->getName()}();
            $totalAssertions += $testCase->getAssertionsCount();
            echo "  \033[32m✔\033[0m {$method->getName()}\n";
        } catch (Throwable $e) {
            $failures[] = "{$className}::{$method->getName()} - " . $e->getMessage();
            echo "  \033[31m✖\033[0m {$method->getName()}: {$e->getMessage()}\n";
        }
    }
}

$elapsed = (microtime(true) - $startAll) * 1000;
echo str_repeat('-', 55) . "\n";

if (empty($failures)) {
    echo "\033[1;32mALL TESTS PASSED!\033[0m ({$totalTests} tests, {$totalAssertions} assertions in " . round($elapsed, 2) . "ms)\n";
    exit(0);
} else {
    echo "\033[1;31mFAILED TESTS:\033[0m\n";
    foreach ($failures as $f) {
        echo " - {$f}\n";
    }
    exit(1);
}
