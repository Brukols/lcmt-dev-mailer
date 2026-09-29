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

lcmt_it('Attribution registers the plugin with WP Consent API', function () {
    $base = plugin_basename(WP_PLUGIN_DIR . '/lcmt-dev-mailer/lcmt-dev-mailer.php');

    lcmt_assert_true(apply_filters('wp_consent_api_registered_' . $base, false));
});
