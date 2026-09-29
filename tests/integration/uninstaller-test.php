<?php

use LcmtDevMailer\Uninstaller;

require_once __DIR__ . '/submissions-page-helpers.php';

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
    lcmt_assert_same(false, str_contains($data, 'If you later delete LCMT Mailer'), 'no native confirm text any more');
    lcmt_assert_same(false, str_contains($data, '"confirm"'), 'no confirm text');

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


/**
 * Enqueue the script for a screen, then print the dialog like admin_footer.
 */
function lcmt_un_dialog(string $hook = 'plugins.php'): string
{
    wp_dequeue_script('lcmt-admin-uninstall');
    wp_deregister_script('lcmt-admin-uninstall');

    Uninstaller::enqueue($hook);

    ob_start();
    Uninstaller::printDialog();

    return (string) ob_get_clean();
}

lcmt_it('Uninstaller prints the deactivation dialog on plugins.php only, for users who can delete plugins', function () {
    lcmt_sp_admin();

    lcmt_assert_true(str_contains(lcmt_un_dialog('plugins.php'), '<dialog id="lcmt-deactivate-dialog"'), 'plugins screen');
    lcmt_assert_same('', lcmt_un_dialog('index.php'), 'other screen');

    $subscriber = wp_insert_user(['user_login' => 'it-sub-' . uniqid(), 'user_pass' => wp_generate_password(), 'role' => 'subscriber']);
    wp_set_current_user($subscriber);
    lcmt_assert_same('', lcmt_un_dialog('plugins.php'), 'subscriber');

    wp_dequeue_script('lcmt-admin-uninstall');
    wp_deregister_script('lcmt-admin-uninstall');
});

lcmt_it('Uninstaller hooks the dialog into the admin footer', function () {
    lcmt_assert_same(10, has_action('admin_footer', ['LcmtDevMailer\\Uninstaller', 'printDialog']));
});

lcmt_it('the deactivation dialog explains, asks and has two buttons', function () {
    lcmt_sp_admin();
    delete_option(Uninstaller::OPTION_DELETE_DATA);

    $html = lcmt_un_dialog();

    lcmt_assert_true(str_contains($html, 'aria-labelledby="lcmt-deactivate-title"'), 'labelled');
    lcmt_assert_true(str_contains($html, '<h2 id="lcmt-deactivate-title">Deactivate LCMT Mailer</h2>'), 'title');
    lcmt_assert_true(str_contains($html, 'Deactivating keeps everything: your settings, your templates and the received messages.'), 'sentence');
    lcmt_assert_true(str_contains($html, 'Also delete the received messages and statistics when the plugin is deleted'), 'checkbox label');
    lcmt_assert_true(str_contains($html, 'data-help-off="They stay in the database, but are no longer anonymized automatically once the plugin is inactive."'), 'help off');
    lcmt_assert_true(str_contains($html, 'data-help-on="They will be erased for good when you delete the plugin."'), 'help on');
    lcmt_assert_true(str_contains($html, '<button type="button" class="button" data-lcmt-cancel>Cancel</button>'), 'cancel');
    lcmt_assert_true(str_contains($html, '<button type="button" class="button button-primary" data-lcmt-confirm data-busy-label="Deactivating…">Deactivate</button>'), 'deactivate');
    lcmt_assert_true(str_contains($html, 'aria-live="polite"'), 'help announced');
    lcmt_assert_same(1, substr_count($html, '<dialog'), 'one dialog');
    lcmt_assert_same(1, substr_count($html, '<style>'), 'scoped style');
    lcmt_assert_true(str_contains($html, '#lcmt-deactivate-dialog'), 'style is scoped to the dialog');

    wp_dequeue_script('lcmt-admin-uninstall');
    wp_deregister_script('lcmt-admin-uninstall');
});

lcmt_it('the deactivation dialog ticks the box only when the stored answer is yes', function () {
    lcmt_sp_admin();
    $checked = static fn(string $html): bool => preg_match('/<input type="checkbox"[^>]*data-lcmt-delete[^>]*\schecked/', $html) === 1;

    delete_option(Uninstaller::OPTION_DELETE_DATA);
    lcmt_assert_same(false, $checked(lcmt_un_dialog()), 'no answer');
    lcmt_assert_true(str_contains(lcmt_un_dialog(), 'data-lcmt-help aria-live="polite">They stay in the database'), 'help follows: off');

    update_option(Uninstaller::OPTION_DELETE_DATA, '0');
    lcmt_assert_same(false, $checked(lcmt_un_dialog()), 'answer no');

    update_option(Uninstaller::OPTION_DELETE_DATA, '1');
    $html = lcmt_un_dialog();
    lcmt_assert_true($checked($html), 'answer yes');
    lcmt_assert_true(str_contains($html, 'data-lcmt-help aria-live="polite">They will be erased for good'), 'help follows: on');

    wp_dequeue_script('lcmt-admin-uninstall');
    wp_deregister_script('lcmt-admin-uninstall');
});

lcmt_it('the deactivation dialog escapes its texts', function () {
    lcmt_sp_admin();
    $inject = static fn(string $text) => $text === 'Cancel' ? '"><img src=x onerror=alert(1)>' : $text;
    add_filter('gettext', $inject);

    try {
        $html = lcmt_un_dialog();
    } finally {
        remove_filter('gettext', $inject);
    }

    lcmt_assert_same(false, str_contains($html, '<img'), 'no raw tag');
    lcmt_assert_true(str_contains($html, '&lt;img src=x onerror=alert(1)&gt;'), 'escaped');

    wp_dequeue_script('lcmt-admin-uninstall');
    wp_deregister_script('lcmt-admin-uninstall');
});
