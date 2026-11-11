<?php

use LcmtDevMailer\OnceMarker;
use PHPUnit\Framework\TestCase;

/**
 * A key claimed once, atomically (security review F2: a solved ALTCHA
 * challenge must not be accepted by several parallel requests). Here the
 * persistent object cache path, with the bootstrap's in-memory wp_cache_add();
 * the database path runs in tests/integration/once-marker-test.php.
 */
class OnceMarkerTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['lcmt_test_cache']           = [];
        $GLOBALS['lcmt_test_ext_object_cache'] = true;
        $GLOBALS['lcmt_test_cache_calls']      = [];
    }

    public function test_a_key_is_claimed_once(): void
    {
        $this->assertTrue(OnceMarker::claim('lcmt_test_key', 60));
        $this->assertFalse(OnceMarker::claim('lcmt_test_key', 60), 'claimed again');
    }

    public function test_keys_are_independent(): void
    {
        $this->assertTrue(OnceMarker::claim('lcmt_test_a', 60));
        $this->assertTrue(OnceMarker::claim('lcmt_test_b', 60));
    }

    public function test_the_claim_uses_an_add_never_a_read_then_write(): void
    {
        OnceMarker::claim('lcmt_test_key', 30);

        $this->assertSame(['add'], array_column($GLOBALS['lcmt_test_cache_calls'] ?? [], 0));
        $this->assertSame(30, $GLOBALS['lcmt_test_cache_calls'][0][2], 'expiry');
    }
}
