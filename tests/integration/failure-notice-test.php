<?php

use LcmtDevMailer\FailureNotice;
use LcmtDevMailer\SubmissionsPage;

/**
 * Sets an administrator (or a subscriber) as the current user and a dismissal
 * moment ten minutes ago, so rows already in the local table never count.
 * The rows a test inserts are five minutes old by default: failures under two
 * minutes old are emails still being sent and do not count yet.
 */
function lcmt_it_failure_setup(bool $admin = true): void
{
    $userId = $admin
        ? (int) get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ID'])[0]
        : wp_insert_user(['user_login' => 'it-sub-' . uniqid(), 'user_pass' => wp_generate_password(), 'role' => 'subscriber']);

    wp_set_current_user($userId);
    update_option(FailureNotice::OPTION_DISMISSED_AT, gmdate('Y-m-d H:i:s', time() - 600));
}

function lcmt_it_failed_row(array $overrides = []): int
{
    return lcmt_it_insert($overrides + [
        'mail_sent'  => 0,
        'mail_error' => 'SMTP connect() failed.',
        'created_at' => gmdate('Y-m-d H:i:s', time() - 300),
    ]);
}

function lcmt_it_capture(callable $render): string
{
    ob_start();
    $render();

    return (string) ob_get_clean();
}

function lcmt_it_dashboard_widgets(): array
{
    global $wp_meta_boxes;

    require_once ABSPATH . 'wp-admin/includes/dashboard.php';
    set_current_screen('dashboard');
    $wp_meta_boxes['dashboard'] = [];

    FailureNotice::addDashboardWidget();

    return array_keys($wp_meta_boxes['dashboard']['normal']['core'] ?? []);
}

lcmt_it('FailureNotice banner prints nothing without an unresolved failure', function () {
    lcmt_it_failure_setup();
    lcmt_it_insert();

    lcmt_assert_same('', lcmt_it_capture([FailureNotice::class, 'banner']));
});

lcmt_it('FailureNotice banner uses the plural for two failures', function () {
    lcmt_it_failure_setup();
    lcmt_it_failed_row();
    lcmt_it_failed_row();

    $html = lcmt_it_capture([FailureNotice::class, 'banner']);

    lcmt_assert_true(strpos($html, '2 form messages could not be emailed.') !== false, 'plural');
    lcmt_assert_true(strpos($html, 'notice-error') !== false, 'error notice');
});

lcmt_it('FailureNotice banner uses the singular for one failure', function () {
    lcmt_it_failure_setup();
    lcmt_it_failed_row();

    $html = lcmt_it_capture([FailureNotice::class, 'banner']);

    lcmt_assert_true(strpos($html, '1 form message could not be emailed.') !== false, 'singular');
});

lcmt_it('FailureNotice banner shows the latest error escaped', function () {
    lcmt_it_failure_setup();
    lcmt_it_failed_row(['created_at' => gmdate('Y-m-d H:i:s', time() - 400), 'mail_error' => 'older error']);
    lcmt_it_failed_row(['created_at' => gmdate('Y-m-d H:i:s', time() - 300), 'mail_error' => 'Boom <b>bold</b>']);

    $html = lcmt_it_capture([FailureNotice::class, 'banner']);

    lcmt_assert_true(strpos($html, 'Boom &lt;b&gt;bold&lt;/b&gt;') !== false, 'escaped latest error');
    lcmt_assert_same(false, strpos($html, '<b>bold'), 'no raw markup');
    lcmt_assert_same(false, strpos($html, 'older error'), 'only the latest error');
});

lcmt_it('FailureNotice banner links to the failed list and a nonce-protected dismiss URL', function () {
    lcmt_it_failure_setup();
    lcmt_it_failed_row();

    $html = lcmt_it_capture([FailureNotice::class, 'banner']);

    lcmt_assert_true(strpos($html, esc_url(SubmissionsPage::url(['failed' => 1]))) !== false, 'failed list link');

    preg_match('/href="([^"]*admin-post\.php[^"]*)"/', $html, $match);
    lcmt_assert_true(isset($match[1]), 'dismiss link present');

    parse_str((string) wp_parse_url(html_entity_decode($match[1]), PHP_URL_QUERY), $query);
    lcmt_assert_same(FailureNotice::DISMISS_ACTION, $query['action'] ?? null, 'action');
    lcmt_assert_true((bool) wp_verify_nonce($query['_wpnonce'] ?? '', 'lcmt_mailer_dismiss_failures'), 'valid nonce');
});

lcmt_it('FailureNotice banner prints nothing for a user without the capability', function () {
    lcmt_it_failure_setup(false);
    lcmt_it_failed_row();

    lcmt_assert_same('', lcmt_it_capture([FailureNotice::class, 'banner']));
});

lcmt_it('FailureNotice banner ignores sent, processed and spam rows', function () {
    lcmt_it_failure_setup();
    lcmt_it_insert(['mail_sent' => 1]);
    lcmt_it_failed_row(['status' => 'processed']);
    lcmt_it_failed_row(['status' => 'spam']);

    lcmt_assert_same('', lcmt_it_capture([FailureNotice::class, 'banner']));

    lcmt_it_failed_row();

    lcmt_assert_true(strpos(lcmt_it_capture([FailureNotice::class, 'banner']), '1 form message could not') !== false, 'only the failed row counts');
});

lcmt_it('FailureNotice registers the dashboard widget only with failures and the capability', function () {
    lcmt_it_failure_setup();
    lcmt_assert_same([], lcmt_it_dashboard_widgets(), 'no failure');

    lcmt_it_failed_row();
    lcmt_assert_same(['lcmt_mailer_failures'], lcmt_it_dashboard_widgets(), 'with a failure');

    lcmt_it_failure_setup(false);
    lcmt_assert_same([], lcmt_it_dashboard_widgets(), 'without the capability');
});

lcmt_it('FailureNotice widget lists five failures newest first with escaped errors', function () {
    lcmt_it_failure_setup();

    for ($i = 1; $i <= 6; $i++) {
        lcmt_it_failed_row(['created_at' => gmdate('Y-m-d H:i:s', time() - 300 + $i), 'mail_error' => "err-{$i} <i>x</i>"]);
    }

    $html = lcmt_it_capture([FailureNotice::class, 'renderWidget']);

    lcmt_assert_same(5, substr_count($html, '<li>'), 'five items');
    lcmt_assert_same(false, strpos($html, 'err-1 '), 'oldest left out');
    lcmt_assert_true(strpos($html, 'err-6 &lt;i&gt;x&lt;/i&gt;') !== false, 'escaped error');
    lcmt_assert_true(strpos($html, 'err-6') < strpos($html, 'err-5'), 'newest first');
    lcmt_assert_true(strpos($html, esc_url(SubmissionsPage::url(['failed' => 1]))) !== false, 'all failed link');
});

lcmt_it('FailureNotice widget falls back when the error is NULL', function () {
    lcmt_it_failure_setup();
    $id = lcmt_it_failed_row(['mail_error' => null]);

    $html = lcmt_it_capture([FailureNotice::class, 'renderWidget']);

    lcmt_assert_true(strpos($html, 'The request stopped before the email was sent.') !== false, 'fallback text');
    lcmt_assert_true(strpos($html, esc_url(SubmissionsPage::url(['submission' => $id]))) !== false, 'detail link');
});

lcmt_it('FailureNotice dismiss hides the banner until a new failure', function () {
    lcmt_it_failure_setup();
    lcmt_it_failed_row(['created_at' => gmdate('Y-m-d H:i:s', time() - 400)]);

    lcmt_assert_true(lcmt_it_capture([FailureNotice::class, 'banner']) !== '', 'banner before');

    FailureNotice::dismiss();

    lcmt_assert_same('', lcmt_it_capture([FailureNotice::class, 'banner']), 'banner after dismiss');

    // A failure only counts once two minutes old, so move the dismissal back
    // in time rather than wait: a failure after it must bring the banner back.
    update_option(FailureNotice::OPTION_DISMISSED_AT, gmdate('Y-m-d H:i:s', time() - 350));
    lcmt_assert_same('', lcmt_it_capture([FailureNotice::class, 'banner']), 'still hidden before the new failure');

    lcmt_it_failed_row(['created_at' => gmdate('Y-m-d H:i:s', time() - 300)]);

    lcmt_assert_true(strpos(lcmt_it_capture([FailureNotice::class, 'banner']), '1 form message could not') !== false, 'banner back with 1');
});

lcmt_it('FailureNotice banner waits two minutes before counting a failure', function () {
    lcmt_it_failure_setup();
    lcmt_it_failed_row(['created_at' => gmdate('Y-m-d H:i:s', time() - 30)]);

    lcmt_assert_same('', lcmt_it_capture([FailureNotice::class, 'banner']), 'in-flight send');
    lcmt_assert_same([], lcmt_it_dashboard_widgets(), 'no widget');
});
