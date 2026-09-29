<?php

use LcmtDevMailer\Attribution;

lcmt_it('Attribution stores without consent by default', function () {
    delete_option(Attribution::OPTION_WITHOUT_CONSENT);

    lcmt_assert_true(Attribution::storesWithoutConsent());
});

lcmt_it('Attribution stops storing without consent when the option is 0', function () {
    update_option(Attribution::OPTION_WITHOUT_CONSENT, '0');

    lcmt_assert_same(false, Attribution::storesWithoutConsent());
});

lcmt_it('Attribution enqueues its script with the consent setting', function () {
    wp_dequeue_script('lcmt-attribution');
    wp_deregister_script('lcmt-attribution');
    update_option(Attribution::OPTION_WITHOUT_CONSENT, '0');

    Attribution::enqueue();

    lcmt_assert_true(wp_script_is('lcmt-attribution', 'enqueued'));

    $data = wp_scripts()->get_data('lcmt-attribution', 'data');
    lcmt_assert_true(strpos((string) $data, 'lcmtMailerAttribution') !== false, 'localized object');
    lcmt_assert_true(strpos((string) $data, '"storeWithoutConsent":""') !== false, 'value is false');

    wp_dequeue_script('lcmt-attribution');
    wp_deregister_script('lcmt-attribution');
});

lcmt_it('Attribution tells the script WP Consent API is not active on this site', function () {
    wp_dequeue_script('lcmt-attribution');
    wp_deregister_script('lcmt-attribution');

    lcmt_assert_same(false, Attribution::consentApiActive());

    Attribution::enqueue();

    $data = (string) wp_scripts()->get_data('lcmt-attribution', 'data');
    lcmt_assert_true(strpos($data, '"consentApi":""') !== false, 'consentApi false: ' . $data);

    wp_dequeue_script('lcmt-attribution');
    wp_deregister_script('lcmt-attribution');
});

lcmt_it('Attribution enqueues late so a consent script registered at the default priority is a dependency', function () {
    lcmt_assert_same(Attribution::PRIORITY, has_action('wp_enqueue_scripts', ['LcmtDevMailer\\Attribution', 'enqueue']));
    lcmt_assert_true(Attribution::PRIORITY > 10, 'after the default priority');

    lcmt_assert_same([], Attribution::dependencies(), 'no consent API');

    $registered = wp_script_is('wp-consent-api', 'registered');
    if (!$registered) {
        wp_register_script('wp-consent-api', 'https://example.invalid/consent.js', [], '1', true);
    }

    lcmt_assert_same(['wp-consent-api'], Attribution::dependencies(), 'registered consent script');

    if (!$registered) {
        wp_deregister_script('wp-consent-api');
    }
});

lcmt_it('Attribution registers the plugin with WP Consent API', function () {
    $base = plugin_basename(WP_PLUGIN_DIR . '/lcmt-dev-mailer/lcmt-dev-mailer.php');

    lcmt_assert_true(apply_filters('wp_consent_api_registered_' . $base, false));
});
