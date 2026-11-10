<?php

declare(strict_types=1);

namespace Resiliens\Retry;

use Throwable;

class RetryPolicy
{
    /** @var array<class-string<Throwable>> */
    private array $retryableExceptions = [];

    /** @var null|callable(Throwable): bool */
    private $retryPredicate = null;

    /** @var null|callable(int, Throwable, int): void */
    private $onRetryCallback = null;

    /** @var callable(int): void */
    private $sleepHandler;

    public function __construct(
        public readonly int $maxAttempts = 3,
        public readonly int $baseDelayMs = 100,
        public readonly int $maxDelayMs = 5000,
        public readonly BackoffStrategy $strategy = BackoffStrategy::FULL_JITTER,
        public readonly float $multiplier = 2.0,
        ?callable $sleepHandler = null
    ) {
        $this->sleepHandler = $sleepHandler ?? static function (int $delayMs): void {
            if ($delayMs > 0) {
                usleep($delayMs * 1000);
            }
        };
    }

    /**
     * @param class-string<Throwable> ...$exceptionClasses
     */
    public function retryOn(string ...$exceptionClasses): self
    {
        $this->retryableExceptions = array_merge($this->retryableExceptions, $exceptionClasses);
        return $this;
    }

    /**
     * @param callable(Throwable): bool $predicate
     */
    public function retryIf(callable $predicate): self
    {
        $this->retryPredicate = $predicate;
        return $this;
    }

    /**
     * @param callable(int $attempt, Throwable $exception, int $delayMs): void $callback
     */
    public function onRetry(callable $callback): self
    {
        $this->onRetryCallback = $callback;
        return $this;
    }

    private function shouldRetry(Throwable $e): bool
    {
        if ($this->retryPredicate !== null) {
            return (bool)($this->retryPredicate)($e);
        }

        if (empty($this->retryableExceptions)) {
            return true;
        }

        foreach ($this->retryableExceptions as $class) {
            if ($e instanceof $class) {
                return true;
            }
        }

        return false;
    }

    /**
     * @template T
     * @param callable(): T $operation
     * @return T
     * @throws Throwable
     */
    public function execute(callable $operation): mixed
    {
        $attempt = 1;

        while (true) {
            try {
                return $operation();
            } catch (Throwable $e) {
                if ($attempt >= $this->maxAttempts || !$this->shouldRetry($e)) {
                    throw $e;
                }

                $delayMs = $this->strategy->calculateDelayMs(
                    $attempt,
                    $this->baseDelayMs,
                    $this->maxDelayMs,
                    $this->multiplier
                );

                if ($this->onRetryCallback !== null) {
                    ($this->onRetryCallback)($attempt, $e, $delayMs);
                }

                ($this->sleepHandler)($delayMs);
                $attempt++;
            }
        }
    }
}
