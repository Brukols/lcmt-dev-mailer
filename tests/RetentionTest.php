<?php

use LcmtDevMailer\Retention;
use PHPUnit\Framework\TestCase;

class RetentionTest extends TestCase
{
    public function testKeepsAValidNumberOfDays(): void
    {
        $this->assertSame(365, Retention::clampDays('365', 1, Retention::DEFAULT_DAYS));
        $this->assertSame(1, Retention::clampDays(1, 1, Retention::DEFAULT_DAYS));
    }

    public function testFallsBackToTheDefaultOnInputThatWouldPurgeEverything(): void
    {
        foreach (['0', '-5', 'abc', '', null, ['1']] as $value) {
            $this->assertSame(Retention::DEFAULT_DAYS, Retention::clampDays($value, 1, Retention::DEFAULT_DAYS), var_export($value, true));
        }
    }

    public function testAllowsZeroWhenZeroMeansForever(): void
    {
        $this->assertSame(0, Retention::clampDays('0', 0, 0));
        $this->assertSame(0, Retention::clampDays('-3', 0, 0));
    }

    public function testCapsAbsurdlyLongPeriods(): void
    {
        $this->assertSame(Retention::MAX_DAYS, Retention::clampDays('99999', 1, Retention::DEFAULT_DAYS));
    }

    public function testComputesTheCutoffInUtc(): void
    {
        $now = gmmktime(12, 0, 0, 9, 29, 2026);

        $this->assertSame('2026-09-19 12:00:00', Retention::cutoff(10, $now));
        $this->assertNull(Retention::cutoff(0, $now));
    }
}
