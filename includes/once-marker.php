<?php

namespace LcmtDevMailer;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * A key that can be claimed once only, atomically: of several requests
 * claiming it at the same time, exactly one wins. A get_transient() then
 * set_transient() pair is not enough: parallel requests all read "not there"
 * before any of them writes (security review F2, ALTCHA replay).
 *
 * - With a persistent object cache (Redis, Memcached…): wp_cache_add(),
 *   which only adds a missing key, atomically in those stores.
 * - Otherwise: an INSERT IGNORE of the transient's row in the options table,
 *   whose option_name is a unique key; only the request whose row went in
 *   wins. The row is a normal transient with its timeout row, so WordPress's
 *   daily cleanup of expired transients deletes it.
 */
final class OnceMarker
{
    private const CACHE_GROUP = 'lcmt_mailer_once';

    /**
     * @param string $key Transient-like name (at most 172 characters).
     * @param int    $ttl Seconds the claim is kept.
     * @return bool True for the one request that claimed the key.
     */
    public static function claim(string $key, int $ttl): bool
    {
        $ttl = max(1, $ttl);

        if (wp_using_ext_object_cache()) {
            return (bool) wp_cache_add($key, 1, self::CACHE_GROUP, $ttl);
        }

        global $wpdb;

        $name     = '_transient_' . $key;
        $inserted = $wpdb->query($wpdb->prepare(
            "INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, %s)",
            $name,
            '1',
            'off'
        ));

        // 0: someone claimed it first; false: the database failed — refuse either way.
        if ($inserted !== 1) {
            return false;
        }

        $wpdb->query($wpdb->prepare(
            "INSERT INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, %s)
             ON DUPLICATE KEY UPDATE option_value = VALUES(option_value)",
            '_transient_timeout_' . $key,
            (string) (time() + $ttl),
            'off'
        ));

        // The option cache may remember the row as missing.
        wp_cache_delete($name, 'options');
        $notoptions = wp_cache_get('notoptions', 'options');
        if (is_array($notoptions) && isset($notoptions[$name])) {
            unset($notoptions[$name]);
            wp_cache_set('notoptions', $notoptions, 'options');
        }

        return true;
    }
}
