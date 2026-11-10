<?php

declare(strict_types=1);

use Resiliens\Bulkhead\Bulkhead;
use Resiliens\Bulkhead\BulkheadRejectedException;

class BulkheadTest extends TestCase
{
    public function testAllowsConcurrentCallsUpToLimit(): void
    {
        $bulkhead = new Bulkhead('db_pool', 2);
        $this->assertEquals(2, $bulkhead->getAvailableCapacity());

        $result = $bulkhead->execute(function () use ($bulkhead) {
            $this->assertEquals(1, $bulkhead->getActiveCalls());
            $this->assertEquals(1, $bulkhead->getAvailableCapacity());

            return $bulkhead->execute(function () use ($bulkhead) {
                $this->assertEquals(2, $bulkhead->getActiveCalls());
                $this->assertEquals(0, $bulkhead->getAvailableCapacity());
                return 'deep_nested_ok';
            });
        });

        $this->assertEquals('deep_nested_ok', $result);
        $this->assertEquals(0, $bulkhead->getActiveCalls());
    }

    public function testRejectsWhenMaxConcurrencyExceeded(): void
    {
        $bulkhead = new Bulkhead('single_slot', 1);

        $this->assertThrows(BulkheadRejectedException::class, function () use ($bulkhead) {
            $bulkhead->execute(function () use ($bulkhead) {
                // Trying to enter 2nd call while 1st is running
                $bulkhead->execute(fn() => 'should_fail');
            });
        });

        // Ensure active count correctly released
        $this->assertEquals(0, $bulkhead->getActiveCalls());
    }
}
