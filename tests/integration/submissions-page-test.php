<?php

use LcmtDevMailer\SubmissionRepository;
use LcmtDevMailer\SubmissionsPage;

require_once ABSPATH . 'wp-admin/includes/admin.php';

function lcmt_sp_admin(): void
{
    $admins = get_users(['role' => 'administrator', 'number' => 1]);
    wp_set_current_user($admins[0]->ID);
}

function lcmt_sp_fields(array $pairs): string
{
    $snapshot = [];

    foreach ($pairs as $name => [$type, $value]) {
        $snapshot[] = ['name' => $name, 'type' => $type, 'value' => $value];
    }

    return wp_json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

/**
 * Run a callback with $_GET replaced, restoring it afterwards.
 */
function lcmt_sp_with_get(array $get, callable $callback)
{
    $saved = $_GET;
    $_GET  = $get;

    try {
        return $callback();
    } finally {
        $_GET = $saved;
    }
}

function lcmt_sp_render(array $get): string
{
    set_current_screen('mail_page_' . SubmissionsPage::PAGE_SLUG);

    // WP_List_Table builds its links from the request host, which wp-cli has not got.
    $_SERVER['HTTP_HOST'] ??= (string) wp_parse_url(home_url(), PHP_URL_HOST);

    return lcmt_sp_with_get($get, function () {
        ob_start();

        try {
            SubmissionsPage::render();
        } finally {
            $html = ob_get_clean();
        }

        return $html;
    });
}

lcmt_it('SubmissionsPage capability defaults to manage_options and can be filtered', function () {
    lcmt_assert_same('manage_options', SubmissionsPage::capability());

    $filter = fn() => 'edit_pages';
    add_filter('lcmt_mailer_submissions_capability', $filter);
    $value = SubmissionsPage::capability();
    remove_filter('lcmt_mailer_submissions_capability', $filter);

    lcmt_assert_same('edit_pages', $value);
});

lcmt_it('SubmissionsPage filtersFromRequest drops unknown values and sanitizes the rest', function () {
    $filters = lcmt_sp_with_get([
        'status'   => 'bogus',
        'channel'  => 'nope',
        'form_key' => 'My Form<b>',
        'failed'   => '1',
        's'        => '  caf\\\'e <b>x</b> ',
    ], fn() => SubmissionsPage::filtersFromRequest());

    lcmt_assert_same('', $filters['status']);
    lcmt_assert_same('', $filters['channel']);
    lcmt_assert_same('my-form', $filters['form_key']);
    lcmt_assert_true($filters['failed']);
    lcmt_assert_same("caf'e x", $filters['search']);

    $filters = lcmt_sp_with_get(['status' => 'spam', 'channel' => 'direct'], fn() => SubmissionsPage::filtersFromRequest());

    lcmt_assert_same('spam', $filters['status']);
    lcmt_assert_same('direct', $filters['channel']);
    lcmt_assert_same(false, $filters['failed']);
});

lcmt_it('SubmissionsPage apply sets a status, deletes, and ignores unknown actions', function () {
    $id = lcmt_it_insert();

    foreach (['read', 'processed', 'spam', 'new'] as $status) {
        lcmt_assert_same('updated', SubmissionsPage::apply($status, [$id]));
        lcmt_assert_same($status, SubmissionRepository::find($id)['status']);
    }

    lcmt_assert_same('', SubmissionsPage::apply('bogus', [$id]));
    lcmt_assert_same('new', SubmissionRepository::find($id)['status']);

    lcmt_assert_same('deleted', SubmissionsPage::apply('delete', [$id]));
    lcmt_assert_null(SubmissionRepository::find($id));
});

lcmt_it('SubmissionsPage builds its urls', function () {
    $url = SubmissionsPage::url(['submission' => 5]);
    parse_str((string) wp_parse_url($url, PHP_URL_QUERY), $query);

    lcmt_assert_same('lcmt-mailer-submissions', $query['page']);
    lcmt_assert_same('mail', $query['post_type']);
    lcmt_assert_same('5', $query['submission']);
    lcmt_assert_true(strpos($url, 'edit.php') !== false);

    lcmt_sp_admin();

    $single = html_entity_decode(SubmissionsPage::singleActionUrl(7, 'spam'));
    parse_str((string) wp_parse_url($single, PHP_URL_QUERY), $query);

    lcmt_assert_true(strpos($single, 'admin-post.php') !== false);
    lcmt_assert_same('lcmt_mailer_submission', $query['action']);
    lcmt_assert_same('7', $query['id']);
    lcmt_assert_same('spam', $query['do']);
    lcmt_assert_true((bool) wp_verify_nonce($query['_wpnonce'], 'lcmt_submission_7'), 'nonce');
    lcmt_assert_same(false, wp_verify_nonce($query['_wpnonce'], 'lcmt_submission_8'), 'nonce is per id');
});

lcmt_it('SubmissionsPage renders the list with escaped summary, channel and mail state', function () {
    lcmt_sp_admin();

    lcmt_it_insert([
        'form_key'  => 'render-form',
        'mail_sent' => 0,
        'channel'   => 'direct',
        'fields'    => lcmt_sp_fields(['name' => ['text', '<script>alert(1)</script>'], 'email' => ['email', 'a@example.com']]),
    ]);

    $html = lcmt_sp_render(['post_type' => 'mail', 'page' => 'lcmt-mailer-submissions', 'form_key' => 'render-form']);

    lcmt_assert_true(strpos($html, 'Received messages') !== false, 'heading');
    lcmt_assert_true(strpos($html, '<script>alert(1)</script>') === false, 'raw script');
    lcmt_assert_true(strpos($html, '&lt;script&gt;alert(1)&lt;/script&gt;') !== false, 'escaped script');
    lcmt_assert_true(strpos($html, 'a@example.com') !== false, 'email');
    lcmt_assert_true(strpos($html, 'Direct') !== false, 'channel label');
    lcmt_assert_true(strpos($html, 'Not sent') !== false, 'not sent');
    lcmt_assert_true(strpos($html, 'render-form') !== false, 'form key');
});

lcmt_it('SubmissionsPage renders the list empty state', function () {
    lcmt_sp_admin();

    $html = lcmt_sp_render(['form_key' => 'no-such-form']);

    lcmt_assert_true(strpos($html, 'No messages yet.') !== false);
});

lcmt_it('SubmissionsPage renders one message with line breaks and the mail error', function () {
    lcmt_sp_admin();

    $id = lcmt_it_insert([
        'mail_sent'  => 0,
        'mail_error' => 'SMTP <down>',
        'fields'     => lcmt_sp_fields(['message' => ['textarea', "line one\nline <two>"]]),
    ]);

    $html = lcmt_sp_render(['submission' => (string) $id]);

    lcmt_assert_true(strpos($html, "line one<br />\nline &lt;two&gt;") !== false, 'nl2br + escaped');
    lcmt_assert_true(strpos($html, 'SMTP &lt;down&gt;') !== false, 'mail error');
    lcmt_assert_true(strpos($html, 'Not sent') !== false, 'not sent');
    lcmt_assert_true(strpos($html, 'Mark as spam') !== false, 'action button');
});

lcmt_it('SubmissionsPage renders the fallback error when a failed row has no message', function () {
    lcmt_sp_admin();

    $id   = lcmt_it_insert(['mail_sent' => 0, 'fields' => lcmt_sp_fields(['name' => ['text', 'Ann']])]);
    $html = lcmt_sp_render(['submission' => (string) $id]);

    lcmt_assert_true(strpos($html, 'The request stopped before the email was sent.') !== false);
});

lcmt_it('SubmissionsPage renders an anonymized message', function () {
    lcmt_sp_admin();

    $id = lcmt_it_insert();
    SubmissionRepository::anonymize([$id]);

    $html = lcmt_sp_render(['submission' => (string) $id]);

    lcmt_assert_true(strpos($html, 'Personal data anonymized on') !== false);
});

lcmt_it('SubmissionsPage renders a missing message', function () {
    lcmt_sp_admin();

    $html = lcmt_sp_render(['submission' => '999999999']);

    lcmt_assert_true(strpos($html, 'This message no longer exists.') !== false);
});

lcmt_it('SubmissionsPage listQueryArgs maps search to s, drops empty values and keeps paged', function () {
    $args = lcmt_sp_with_get([
        'form_key' => 'my-form',
        'status'   => 'spam',
        'channel'  => 'direct',
        'failed'   => '1',
        's'        => 'caf\\\'e',
        'paged'    => '3',
    ], fn() => SubmissionsPage::listQueryArgs());

    lcmt_assert_same(
        ['form_key' => 'my-form', 'status' => 'spam', 'channel' => 'direct', 'failed' => 1, 's' => "caf'e", 'paged' => 3],
        $args
    );

    lcmt_assert_same([], lcmt_sp_with_get(['status' => 'bogus', 'paged' => '0'], fn() => SubmissionsPage::listQueryArgs()));
});

lcmt_it('SubmissionsPage markOpenedAsRead marks a new message read before the menu is built', function () {
    lcmt_sp_admin();

    $id     = lcmt_it_insert(['status' => 'new']);
    $before = SubmissionRepository::countUnread();
    $get    = ['page' => SubmissionsPage::PAGE_SLUG, 'submission' => (string) $id];

    lcmt_sp_with_get($get, fn() => SubmissionsPage::addSubmenu());

    lcmt_assert_same('read', SubmissionRepository::find($id)['status']);
    lcmt_assert_same($before - 1, SubmissionRepository::countUnread());
});

lcmt_it('SubmissionsPage markOpenedAsRead leaves processed alone', function () {
    lcmt_sp_admin();

    $id = lcmt_it_insert(['status' => 'processed']);

    lcmt_sp_with_get(['page' => SubmissionsPage::PAGE_SLUG, 'submission' => (string) $id], fn() => SubmissionsPage::markOpenedAsRead());

    lcmt_assert_same('processed', SubmissionRepository::find($id)['status']);
});

lcmt_it('SubmissionsPage markOpenedAsRead does nothing without the capability', function () {
    wp_set_current_user(0);

    $id = lcmt_it_insert(['status' => 'new']);

    lcmt_sp_with_get(['page' => SubmissionsPage::PAGE_SLUG, 'submission' => (string) $id], fn() => SubmissionsPage::markOpenedAsRead());

    lcmt_assert_same('new', SubmissionRepository::find($id)['status']);
});

lcmt_it('SubmissionsPage markOpenedAsRead does nothing on another page', function () {
    lcmt_sp_admin();

    $id = lcmt_it_insert(['status' => 'new']);

    lcmt_sp_with_get(['page' => 'something-else', 'submission' => (string) $id], fn() => SubmissionsPage::markOpenedAsRead());
    lcmt_sp_with_get(['submission' => (string) $id], fn() => SubmissionsPage::markOpenedAsRead());

    lcmt_assert_same('new', SubmissionRepository::find($id)['status']);
});

lcmt_it('SubmissionsPage handleLoad still marks an opened message read', function () {
    lcmt_sp_admin();

    $id = lcmt_it_insert(['status' => 'new']);

    lcmt_sp_with_get(['page' => SubmissionsPage::PAGE_SLUG, 'submission' => (string) $id], fn() => SubmissionsPage::handleLoad());

    lcmt_assert_same('read', SubmissionRepository::find($id)['status']);
});

lcmt_it('SubmissionsPage row action Delete asks for confirmation', function () {
    lcmt_sp_admin();

    lcmt_it_insert(['form_key' => 'confirm-form', 'fields' => lcmt_sp_fields(['name' => ['text', 'Ann']])]);

    $html = lcmt_sp_render(['form_key' => 'confirm-form']);

    lcmt_assert_true(strpos($html, 'submitdelete') !== false, 'delete link');
    lcmt_assert_true(strpos($html, 'onclick="return confirm(') !== false, 'confirm');
});
