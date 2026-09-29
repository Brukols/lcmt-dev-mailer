<?php

use LcmtDevMailer\Uninstaller;

/**
 * Nothing here runs uninstall.php, nor Uninstaller::uninstallSite() with the
 * data set to be deleted: dropping the table is DDL, which the test
 * transaction cannot roll back.
 */

lcmt_it('Uninstaller keeps the data by default', function () {
    delete_option(Uninstaller::OPTION_DELETE_DATA);
    delete_site_option(Uninstaller::OPTION_DELETE_DATA);

    lcmt_assert_same(false, Uninstaller::shouldDeleteData());
});

lcmt_it('Uninstaller deletes the data only once the site asked for it', function () {
    Uninstaller::store(true);
    lcmt_assert_same('1', get_option(Uninstaller::OPTION_DELETE_DATA));
    lcmt_assert_true(Uninstaller::shouldDeleteData());

    Uninstaller::store(false);
    lcmt_assert_same('0', get_option(Uninstaller::OPTION_DELETE_DATA));
    lcmt_assert_same(false, Uninstaller::shouldDeleteData());
});

lcmt_it('Uninstaller removes its own option with the data', function () {
    lcmt_assert_true(in_array(Uninstaller::OPTION_DELETE_DATA, Uninstaller::DATA_OPTIONS, true));
    lcmt_assert_true(in_array('lcmt_mailer_retention_days', Uninstaller::DATA_OPTIONS, true));
});

lcmt_it('Uninstaller sanitizes the setting to 1 or 0', function () {
    lcmt_assert_same('1', sanitize_option(Uninstaller::OPTION_DELETE_DATA, '1'));
    lcmt_assert_same('0', sanitize_option(Uninstaller::OPTION_DELETE_DATA, null));
    lcmt_assert_same('0', sanitize_option(Uninstaller::OPTION_DELETE_DATA, 'yes'));
    lcmt_assert_same('0', sanitize_option(Uninstaller::OPTION_DELETE_DATA, '0'));
});

lcmt_it('Uninstaller AJAX answer needs a valid nonce and delete_plugins', function () {
    lcmt_sp_admin();
    $nonce = wp_create_nonce(Uninstaller::AJAX_ACTION);

    lcmt_assert_true(Uninstaller::authorized(['nonce' => $nonce, 'delete' => '1']), 'admin with nonce');
    lcmt_assert_same(false, Uninstaller::authorized(['nonce' => 'bad', 'delete' => '1']), 'bad nonce');
    lcmt_assert_same(false, Uninstaller::authorized(['delete' => '1']), 'no nonce');

    $subscriber = wp_insert_user(['user_login' => 'it-sub-' . uniqid(), 'user_pass' => wp_generate_password(), 'role' => 'subscriber']);
    wp_set_current_user($subscriber);

    lcmt_assert_same(false, Uninstaller::authorized(['nonce' => wp_create_nonce(Uninstaller::AJAX_ACTION), 'delete' => '1']), 'subscriber');
});

lcmt_it('Uninstaller AJAX handler is registered for logged-in users only', function () {
    lcmt_assert_same(10, has_action('wp_ajax_' . Uninstaller::AJAX_ACTION, ['LcmtDevMailer\\Uninstaller', 'handleAjax']));
    lcmt_assert_same(false, has_action('wp_ajax_nopriv_' . Uninstaller::AJAX_ACTION));
});

lcmt_it('Uninstaller enqueues the deactivation prompt on plugins.php only, with the plugin basename', function () {
    lcmt_sp_admin();
    wp_dequeue_script('lcmt-admin-uninstall');
    wp_deregister_script('lcmt-admin-uninstall');

    Uninstaller::enqueue('index.php');
    lcmt_assert_same(false, wp_script_is('lcmt-admin-uninstall', 'enqueued'), 'other screen');

    Uninstaller::enqueue('plugins.php');
    lcmt_assert_true(wp_script_is('lcmt-admin-uninstall', 'enqueued'), 'plugins screen');

    $data = (string) wp_scripts()->get_data('lcmt-admin-uninstall', 'data');
    lcmt_assert_true(str_contains($data, '"plugin":"lcmt-dev-mailer/lcmt-dev-mailer.php"'), 'basename: ' . $data);
    lcmt_assert_true(str_contains($data, '"action":"' . Uninstaller::AJAX_ACTION . '"'), 'action');
    lcmt_assert_true(str_contains($data, 'If you later delete LCMT Mailer'), 'asked at deactivation');

    wp_dequeue_script('lcmt-admin-uninstall');
    wp_deregister_script('lcmt-admin-uninstall');
});

lcmt_it('Data retention page shows the delete-on-uninstall checkbox', function () {
    update_option(Uninstaller::OPTION_DELETE_DATA, '1');

    $html = lcmt_rt_render([]);

    lcmt_assert_true(preg_match('/name="' . Uninstaller::OPTION_DELETE_DATA . '"[^>]*checked/', $html) === 1, 'ticked checkbox');
    lcmt_assert_true(str_contains($html, 'Delete received messages when the plugin is deleted'), 'label');
});

lcmt_it('Uninstaller keeping the data only unschedules the purge', function () {
    global $wpdb;

    Uninstaller::store(false);
    delete_site_option(Uninstaller::OPTION_DELETE_DATA);

    // Guard: never reach the DROP TABLE on the local site.
    if (Uninstaller::shouldDeleteData()) {
        throw new RuntimeException('refusing to run uninstallSite() with the data set to be deleted');
    }

    update_option('lcmt_mailer_retention_days', 200);
    LcmtDevMailer\Retention::schedule();

    Uninstaller::uninstallSite();

    $table = LcmtDevMailer\SubmissionRepository::table();
    lcmt_assert_same($table, $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table))), 'table kept');
    lcmt_assert_same('200', (string) get_option('lcmt_mailer_retention_days'), 'settings kept');
    lcmt_assert_same('0', get_option(Uninstaller::OPTION_DELETE_DATA), 'answer kept');
    lcmt_assert_same(false, wp_next_scheduled(Uninstaller::CRON_HOOK), 'purge unscheduled');
});
