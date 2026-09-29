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

    /**
     * Years first, then months, then days: 90 days reads "3 months".
     */
    public function testExpressesAPeriodInTheLargestWholeUnit(): void
    {
        $cases = [
            1095 => [3, 'year'],
            730  => [2, 'year'],
            365  => [1, 'year'],
            3650 => [10, 'year'],
            180  => [6, 'month'],
            90   => [3, 'month'],
            30   => [1, 'month'],
            45   => [45, 'day'],
            1    => [1, 'day'],
            364  => [364, 'day'],
        ];

        foreach ($cases as $days => $expected) {
            $this->assertSame($expected, Retention::periodParts($days), (string) $days);
        }
    }
}
