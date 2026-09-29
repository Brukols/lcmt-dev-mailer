<?php

use LcmtDevMailer\StatsPage;
use LcmtDevMailer\SubmissionRepository;
use LcmtDevMailer\SubmissionSettings;
use LcmtDevMailer\SubmissionsPage;

require_once __DIR__ . '/submissions-page-helpers.php';

/**
 * A user who may open Received messages (through the filter) without manage_options.
 */
function lcmt_tabs_editor_with_filter(): callable
{
    $editor = wp_insert_user(['user_login' => 'it-editor-' . uniqid(), 'user_pass' => wp_generate_password(), 'role' => 'editor']);
    wp_set_current_user($editor);

    $filter = static fn() => 'edit_pages';
    add_filter('lcmt_mailer_submissions_capability', $filter);

    return $filter;
}

/**
 * Run a callback that ends in wp_safe_redirect() + exit, and return the location.
 */
function lcmt_tabs_redirect_of(callable $callback): string
{
    $capture = static function ($location) {
        throw new RuntimeException('redirect:' . $location);
    };
    add_filter('wp_redirect', $capture);

    try {
        $callback();
    } catch (RuntimeException $e) {
        if (str_starts_with($e->getMessage(), 'redirect:')) {
            return substr($e->getMessage(), 9);
        }

        throw $e;
    } finally {
        remove_filter('wp_redirect', $capture);
    }

    throw new RuntimeException('no redirect');
}

lcmt_it('Each page has its own URL helper', function () {
    foreach ([
        [SubmissionsPage::url(), 'lcmt-mailer-submissions'],
        [StatsPage::url(['period' => '30']), 'lcmt-mailer-stats'],
        [SubmissionSettings::url(), 'lcmt-mailer-retention'],
    ] as [$url, $slug]) {
        parse_str((string) wp_parse_url($url, PHP_URL_QUERY), $query);

        lcmt_assert_same($slug, $query['page'] ?? null, $url);
        lcmt_assert_same('mail', $query['post_type'] ?? null, $url);
        lcmt_assert_same(false, isset($query['tab']), 'no tab arg');
    }
});

lcmt_it('The three screens have their own wrap and heading and no nav tabs', function () {
    lcmt_sp_admin();

    $screens = [
        'Received messages' => lcmt_sp_render([]),
        'Statistics'        => lcmt_sp_page(StatsPage::class),
        'Data retention'    => lcmt_sp_page(SubmissionSettings::class),
    ];

    foreach ($screens as $title => $html) {
        lcmt_assert_same(1, substr_count($html, '<div class="wrap">'), $title . ' wrap');
        lcmt_assert_same(1, preg_match('#<h1[^>]*>' . preg_quote($title, '#') . '</h1>#', $html), $title . ' heading');
        lcmt_assert_same(1, substr_count($html, '<h1'), $title . ' one heading');
        lcmt_assert_same(false, str_contains($html, 'nav-tab'), $title . ' no nav tabs');
    }

    lcmt_assert_true(str_contains($screens['Received messages'], 'class="wp-list-table'), 'messages list');
    lcmt_assert_true(str_contains($screens['Statistics'], 'lcmt-stats__tile'), 'stats content');
    lcmt_assert_true(str_contains($screens['Data retention'], 'name="' . SubmissionSettings::OPTION_DAYS . '"'), 'retention form');
});

lcmt_it('A message opened from the list shows its detail, whatever the tab arg', function () {
    lcmt_sp_admin();

    $id   = lcmt_it_insert();
    $html = lcmt_sp_render(['submission' => (string) $id]);

    lcmt_assert_true(str_contains($html, 'Back to the messages'), 'detail');
    lcmt_assert_same(false, str_contains($html, 'nav-tab'), 'no tabs');
});

lcmt_it('markOpenedAsRead only acts on the Received messages page', function () {
    lcmt_sp_admin();

    $id = lcmt_it_insert(['status' => 'new']);

    foreach (['lcmt-mailer-stats', 'lcmt-mailer-retention'] as $page) {
        lcmt_sp_with_get(['page' => $page, 'submission' => (string) $id], fn() => SubmissionsPage::markOpenedAsRead());
        lcmt_assert_same('new', SubmissionRepository::find($id)['status'], 'not read from ' . $page);
    }

    lcmt_sp_with_get(['page' => SubmissionsPage::PAGE_SLUG, 'submission' => (string) $id], fn() => SubmissionsPage::markOpenedAsRead());
    lcmt_assert_same('read', SubmissionRepository::find($id)['status'], 'read from the messages page');
});

lcmt_it('The retention form comes back to the retention page after saving', function () {
    $html = lcmt_rt_render([]);

    preg_match_all('/name="_wp_http_referer" value="([^"]*)"/', $html, $m);
    lcmt_assert_true(count($m[1]) >= 1, 'referer field');
    // The first one is the settings form; the Purge now form has its own after it.

    parse_str((string) wp_parse_url(html_entity_decode((string) $m[1][0]), PHP_URL_QUERY), $query);

    lcmt_assert_same('lcmt-mailer-retention', $query['page'] ?? null, 'page');
    lcmt_assert_same(false, isset($query['tab']), 'no tab');
    lcmt_assert_true(str_contains($html, 'name="option_page" value="' . SubmissionSettings::GROUP . '"'), 'option_page');
    lcmt_assert_true(str_contains($html, 'name="_wpnonce"'), 'nonce');

    // The nonce is built by hand (settings_fields() would set the wrong referer): options.php must accept it.
    lcmt_assert_same(1, preg_match('/name="_wpnonce" value="([^"]+)"/', $html, $nonce), 'nonce value');
    lcmt_assert_true((bool) wp_verify_nonce($nonce[1], SubmissionSettings::GROUP . '-options'), 'options.php accepts the nonce');
});

lcmt_it('Purge now redirects to the retention page with its counts', function () {
    lcmt_sp_admin();

    $_REQUEST['_wpnonce'] = wp_create_nonce(SubmissionSettings::PURGE_ACTION);

    try {
        $location = lcmt_tabs_redirect_of(fn() => SubmissionSettings::handlePurgeNow());
    } finally {
        unset($_REQUEST['_wpnonce']);
    }

    parse_str((string) wp_parse_url($location, PHP_URL_QUERY), $query);

    lcmt_assert_same('lcmt-mailer-retention', $query['page'] ?? null, 'page');
    lcmt_assert_same(false, isset($query['tab']), 'no tab');
    lcmt_assert_true(isset($query['anonymized'], $query['deleted']), 'counts');
});

lcmt_it('A bulk action from the messages page redirects back to it and keeps the filters', function () {
    lcmt_sp_admin();

    $id = lcmt_it_insert(['form_key' => 'bulk-form']);

    $get = [
        'page'     => SubmissionsPage::PAGE_SLUG,
        'action'   => 'read',
        'ids'      => [(string) $id],
        'form_key' => 'bulk-form',
        '_wpnonce' => wp_create_nonce('bulk-submissions'),
    ];

    $_REQUEST['_wpnonce'] = $get['_wpnonce'];

    try {
        $location = lcmt_tabs_redirect_of(fn() => lcmt_sp_with_get($get, fn() => SubmissionsPage::handleLoad()));
    } finally {
        unset($_REQUEST['_wpnonce']);
    }

    parse_str((string) wp_parse_url($location, PHP_URL_QUERY), $query);

    lcmt_assert_same(SubmissionsPage::PAGE_SLUG, $query['page'] ?? null);
    lcmt_assert_same('bulk-form', $query['form_key'] ?? null);
    lcmt_assert_same('updated', $query['notice'] ?? null);
    lcmt_assert_same(false, isset($query['tab']), 'no tab');
});

// ── Menu ──

/**
 * Run addSubmenu() against a fake menu holding only the Email templates entry.
 *
 * @return array{menu: array, submenu: list<array>} The menu, and the submenu items the plugin added.
 */
function lcmt_tabs_build_menu(): array
{
    global $menu, $submenu;

    $saved = [$menu, $submenu];
    $menu = [
        20 => ['Other', 'read', 'index.php', '', 'menu-top'],
        21 => ['Email templates', 'edit_posts', 'edit.php?post_type=mail', '', 'menu-top'],
    ];
    $submenu = [];

    try {
        SubmissionsPage::addSubmenu();

        // add_submenu_page() first copies the parent entry as the first submenu item; keep ours only.
        $ours = array_values(array_filter(
            $submenu['edit.php?post_type=mail'] ?? [],
            static fn(array $item) => $item[2] !== 'edit.php?post_type=mail'
        ));

        return ['menu' => $menu, 'submenu' => $ours];
    } finally {
        [$menu, $submenu] = $saved;
    }
}

lcmt_it('The plugin registers three submenus in order with their capabilities', function () {
    lcmt_sp_admin();

    $built = lcmt_tabs_build_menu();

    lcmt_assert_same(['lcmt-mailer-submissions', 'lcmt-mailer-stats', 'lcmt-mailer-retention'], array_column($built['submenu'], 2));
    lcmt_assert_same(['Received messages', 'Statistics', 'Data retention'], array_column($built['submenu'], 3));
    lcmt_assert_same(['manage_options', 'manage_options', 'manage_options'], array_column($built['submenu'], 1));
    lcmt_assert_same(SubmissionsPage::PAGE_SLUG, 'lcmt-mailer-submissions');
    lcmt_assert_same(false, has_action('admin_menu', ['LcmtDevMailer\\StatsPage', 'addSubmenu']), 'one hook registers all three');
    lcmt_assert_true(is_int(has_action('admin_menu', ['LcmtDevMailer\\SubmissionsPage', 'addSubmenu'])), 'menu hook');
});

lcmt_it('Without manage_options the retention entry is absent and the others use the filtered capability', function () {
    $filter = lcmt_tabs_editor_with_filter();

    try {
        $built = lcmt_tabs_build_menu();

        lcmt_assert_same(['lcmt-mailer-submissions', 'lcmt-mailer-stats'], array_column($built['submenu'], 2));
        lcmt_assert_same(['edit_pages', 'edit_pages'], array_column($built['submenu'], 1));
    } finally {
        remove_filter('lcmt_mailer_submissions_capability', $filter);
    }
});

lcmt_it('The unread bubble shows on the top-level menu and the submenu with the same count', function () {
    lcmt_sp_admin();

    global $wpdb;
    $wpdb->query('DELETE FROM ' . SubmissionRepository::table());
    lcmt_it_insert(['status' => 'new']);
    lcmt_it_insert(['status' => 'new']);
    lcmt_it_insert(['status' => 'read']);

    $built  = lcmt_tabs_build_menu();
    $bubble = '<span class="awaiting-mod">2</span>';

    lcmt_assert_same('Email templates ' . $bubble, $built['menu'][21][0], 'top-level');
    lcmt_assert_same('Other', $built['menu'][20][0], 'other entries untouched');
    lcmt_assert_same('Received messages ' . $bubble, $built['submenu'][0][0], 'submenu');
    lcmt_assert_same('Statistics', $built['submenu'][1][0], 'no bubble on statistics');
    lcmt_assert_same('Data retention', $built['submenu'][2][0], 'no bubble on retention');
});

lcmt_it('The unread bubble is absent at zero', function () {
    lcmt_sp_admin();

    global $wpdb;
    $wpdb->query('DELETE FROM ' . SubmissionRepository::table());
    lcmt_it_insert(['status' => 'read']);

    $built = lcmt_tabs_build_menu();

    lcmt_assert_same('Email templates', $built['menu'][21][0]);
    lcmt_assert_same('Received messages', $built['submenu'][0][0]);
});

lcmt_it('The top-level bubble counts after an opened message was read', function () {
    lcmt_sp_admin();

    global $wpdb;
    $wpdb->query('DELETE FROM ' . SubmissionRepository::table());
    $id = lcmt_it_insert(['status' => 'new']);
    lcmt_it_insert(['status' => 'new']);

    $built = lcmt_sp_with_get(['page' => SubmissionsPage::PAGE_SLUG, 'submission' => (string) $id], fn() => lcmt_tabs_build_menu());

    lcmt_assert_same('Email templates <span class="awaiting-mod">1</span>', $built['menu'][21][0]);
});

lcmt_it('The top-level bubble is left out for a user without the messages capability', function () {
    lcmt_sp_admin();
    lcmt_it_insert(['status' => 'new']);

    $filter = static fn() => 'a_capability_nobody_has';
    add_filter('lcmt_mailer_submissions_capability', $filter);

    try {
        $built = lcmt_tabs_build_menu();
    } finally {
        remove_filter('lcmt_mailer_submissions_capability', $filter);
    }

    lcmt_assert_same('Email templates', $built['menu'][21][0]);
});

// ── Badges ──

lcmt_it('The list shows a colored badge for each status', function () {
    lcmt_sp_admin();

    $key = lcmt_it_key();

    foreach (['new', 'read', 'processed', 'spam'] as $status) {
        lcmt_it_insert(['form_key' => $key, 'status' => $status]);
    }

    // Spam only shows when asked for.
    $html = lcmt_sp_render(['form_key' => $key]) . lcmt_sp_render(['form_key' => $key, 'status' => 'spam']);

    lcmt_assert_true(str_contains($html, '>Status</th>'), 'Status column');

    foreach (['new' => 'New', 'read' => 'Read', 'processed' => 'Processed', 'spam' => 'Spam'] as $status => $label) {
        lcmt_assert_true(
            str_contains($html, '<span class="lcmt-badge lcmt-badge--' . $status . '">' . $label . '</span>'),
            $status . ' badge'
        );
    }
});

lcmt_it('The Status column comes right after the sender', function () {
    lcmt_sp_admin();

    lcmt_assert_same(1, preg_match('#column-summary.*column-status#s', lcmt_sp_render([])), 'order');
});

lcmt_it('The Email column shows sent and not sent badges', function () {
    lcmt_sp_admin();

    $key = lcmt_it_key();
    lcmt_it_insert(['form_key' => $key, 'mail_sent' => 1]);
    lcmt_it_insert(['form_key' => $key, 'mail_sent' => 0]);

    $html = lcmt_sp_render(['form_key' => $key]);

    lcmt_assert_true(str_contains($html, '<span class="lcmt-badge lcmt-badge--sent"><span aria-hidden="true">&#10003;</span> Sent</span>'), 'sent');
    lcmt_assert_true(str_contains($html, '<span class="lcmt-badge lcmt-badge--unsent"><span aria-hidden="true">&#10007;</span> Not sent</span>'), 'not sent');
    lcmt_assert_same(false, str_contains($html, 'style="color: #'), 'no colored text');
});

lcmt_it('Unread rows stay bold', function () {
    lcmt_sp_admin();

    $key = lcmt_it_key();
    lcmt_it_insert(['form_key' => $key, 'status' => 'new']);

    lcmt_assert_true(str_contains(lcmt_sp_render(['form_key' => $key]), '<strong>Ann'), 'bold summary');
});

lcmt_it('The message badges sit beside the heading, not inside it', function () {
    lcmt_sp_admin();

    $id   = lcmt_it_insert(['status' => 'processed', 'mail_sent' => 0]);
    $html = lcmt_sp_render(['submission' => (string) $id]);

    preg_match('#<h1[^>]*>(.*?)</h1>#s', $html, $h1);
    lcmt_assert_same('Received message', $h1[1] ?? null, 'the accessible name is the title alone');

    lcmt_assert_same(1, preg_match('#</h1>\s*<span class="lcmt-badges">(.*?)</span>\s*<hr class="wp-header-end">#s', $html, $badges), 'badges follow the heading, in the same header row');
    lcmt_assert_true(str_contains($badges[1], '<span class="lcmt-badge lcmt-badge--processed">Processed</span>'), 'status');
    lcmt_assert_true(str_contains($badges[1], 'lcmt-badge--unsent'), 'email');
    lcmt_assert_true(str_contains($badges[1], '<span aria-hidden="true">&#10007;</span>'), 'glyph hidden from screen readers');

    $id   = lcmt_it_insert(['status' => 'spam', 'mail_sent' => 1]);
    $html = lcmt_sp_render(['submission' => (string) $id]);

    lcmt_assert_same(1, preg_match('#</h1>\s*<span class="lcmt-badges">(.*?)</span>\s*<hr#s', $html, $badges), 'spam row');
    lcmt_assert_true(str_contains($badges[1], 'lcmt-badge--spam'), 'spam');
    lcmt_assert_true(str_contains($badges[1], 'lcmt-badge--sent'), 'sent');
    lcmt_assert_true(str_contains($badges[1], '<span aria-hidden="true">&#10003;</span>'), 'glyph hidden');
});

lcmt_it('The list heading has no badges', function () {
    lcmt_sp_admin();

    lcmt_assert_same(false, str_contains(lcmt_sp_render([]), 'class="lcmt-badges"'));
});

lcmt_it('The badge styles are printed once, with the six colors', function () {
    lcmt_sp_admin();

    $html = lcmt_sp_render([]);

    lcmt_assert_same(1, substr_count($html, '.lcmt-badge {'), 'one stylesheet');

    foreach (['new', 'read', 'processed', 'spam', 'sent', 'unsent'] as $variant) {
        lcmt_assert_true(str_contains($html, '.lcmt-badge--' . $variant), $variant);
    }
});
