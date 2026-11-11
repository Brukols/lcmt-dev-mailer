<?php

use LcmtDevMailer\OnceMarker;

/**
 * OnceMarker without a persistent object cache: the claim is an INSERT IGNORE
 * of a transient row (security review F2). Parallel requests cannot be
 * started from here; the unique key on option_name is what makes it atomic,
 * so these tests check that a second claim of an existing row loses, and that
 * the row is a normal, expiring transient.
 */

lcmt_it('OnceMarker: a key is claimed once, in the database', function () {
    if (wp_using_ext_object_cache()) {
        return; // this site uses the object cache path (unit-tested)
    }

    $key = 'lcmt_it_once_' . wp_generate_password(12, false, false);

    lcmt_assert_true(OnceMarker::claim($key, 60), 'first claim');
    lcmt_assert_true(!OnceMarker::claim($key, 60), 'second claim loses');
    lcmt_assert_same('1', get_transient($key), 'readable as a transient');

    $timeout = (int) get_option('_transient_timeout_' . $key);
    lcmt_assert_true($timeout > time() && $timeout <= time() + 61, 'expires with the claim');
});

lcmt_it('OnceMarker: a row someone else wrote first wins over the claim', function () {
    if (wp_using_ext_object_cache()) {
        return;
    }

    global $wpdb;
    $key = 'lcmt_it_once_' . wp_generate_password(12, false, false);

    // Another request got there between our cheap checks and our claim.
    $wpdb->insert($wpdb->options, ['option_name' => '_transient_' . $key, 'option_value' => '1', 'autoload' => 'off']);

    lcmt_assert_true(!OnceMarker::claim($key, 60), 'the claim loses');
});
