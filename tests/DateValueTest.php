<?php

use LcmtDevMailer\DateValue;
use PHPUnit\Framework\TestCase;

/**
 * A date field submits YYYY-MM-DD, the format of <input type="date">; the
 * mail shows it in the site's format.
 */
class DateValueTest extends TestCase
{
    public function test_a_date_is_shown_in_the_given_format(): void
    {
        $this->assertSame('12/12/2026', DateValue::format('2026-12-12', 'd/m/Y'));
        $this->assertSame('5/3/2027', DateValue::format('2027-03-05', 'j/n/Y'));
    }

    public function test_surrounding_spaces_are_ignored(): void
    {
        $this->assertSame('12/12/2026', DateValue::format(' 2026-12-12 ', 'd/m/Y'));
    }

    public function test_anything_else_is_left_as_sent(): void
    {
        foreach (['', '12/12/2026', '2026-13-01', '2026-02-30', '2026-12-12T10:00', 'tomorrow'] as $value) {
            $this->assertSame($value, DateValue::format($value, 'd/m/Y'), $value);
        }
    }

    public function test_an_empty_format_leaves_the_date_as_sent(): void
    {
        $this->assertSame('2026-12-12', DateValue::format('2026-12-12', ''));
    }
}
