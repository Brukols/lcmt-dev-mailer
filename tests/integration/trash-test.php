<?php

use LcmtDevMailer\FailureNotice;
use LcmtDevMailer\Privacy;
use LcmtDevMailer\Retention;
use LcmtDevMailer\SubmissionRepository;
use LcmtDevMailer\SubmissionSchema;
use LcmtDevMailer\SubmissionSettings;
use LcmtDevMailer\SubmissionsPage;

require_once __DIR__ . '/submissions-page-helpers.php';

function lcmt_tr_redirect(callable $callback): string
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

/**
 * Whether the callback ends in wp_die() (bad nonce, missing capability), which would end the process.
 */
function lcmt_tr_dies(callable $callback): bool
{
    $handler = static fn() => static function () {
        throw new RuntimeException('wp_die');
    };
    add_filter('wp_die_handler', $handler);

    try {
        $callback();
    } catch (RuntimeException $e) {
        return $e->getMessage() === 'wp_die';
    } finally {
        remove_filter('wp_die_handler', $handler);
    }

    return false;
}

function lcmt_tr_query(string $location): array
{
    parse_str((string) wp_parse_url($location, PHP_URL_QUERY), $query);

    return $query;
}

function lcmt_tr_trashed_at(int $id): ?string
{
    global $wpdb;

    return $wpdb->get_var($wpdb->prepare('SELECT trashed_at FROM ' . SubmissionRepository::table() . ' WHERE id = %d', $id));
}

function lcmt_tr_trash_days(int $days): callable
{
    $filter = static fn() => $days;
    add_filter('lcmt_mailer_trash_days', $filter);

    return $filter;
}

/**
 * Run a bulk action through handleLoad() and return the redirect query.
 */
function lcmt_tr_bulk(string $action, array $ids, array $extra = []): array
{
    $get = $extra + ['page' => SubmissionsPage::PAGE_SLUG, 'action' => $action, 'ids' => array_map('strval', $ids)];

    $get['_wpnonce']      = wp_create_nonce('bulk-submissions');
    $_REQUEST['_wpnonce'] = $get['_wpnonce'];

    try {
        return lcmt_tr_query(lcmt_tr_redirect(fn() => lcmt_sp_with_get($get, fn() => SubmissionsPage::handleLoad())));
    } finally {
        unset($_REQUEST['_wpnonce']);
    }
}

function lcmt_tr_single(int $id, string $do): array
{
    $_REQUEST['id']       = (string) $id;
    $_REQUEST['do']       = $do;
    $_REQUEST['_wpnonce'] = wp_create_nonce('lcmt_submission_' . $id);

    try {
        return lcmt_tr_query(lcmt_tr_redirect(fn() => SubmissionsPage::handleSingle()));
    } finally {
        unset($_REQUEST['id'], $_REQUEST['do'], $_REQUEST['_wpnonce']);
    }
}

// ── Schema and repository ──

lcmt_it('The schema is version 3 and knows the trashed_at column', function () {
    lcmt_assert_same('3', SubmissionSchema::VERSION);
    lcmt_assert_true(in_array('trashed_at', SubmissionSchema::COLUMNS, true), 'listed');
    lcmt_assert_true(SubmissionSchema::hasColumns(['trashed_at']), 'exists in the table');
});

lcmt_it('Trashing keeps the status, sets trashed_at in UTC, and restoring brings the row back as it was', function () {
    $id = lcmt_it_insert(['status' => 'processed']);

    SubmissionRepository::trash([$id]);

    $at = lcmt_tr_trashed_at($id);
    lcmt_assert_true($at !== null && abs(strtotime($at . ' UTC') - time()) < 30, 'trashed_at is now, UTC');
    lcmt_assert_same('processed', SubmissionRepository::find($id)['status'], 'status kept in the trash');

    SubmissionRepository::restore([$id]);

    lcmt_assert_null(lcmt_tr_trashed_at($id), 'restored');
    lcmt_assert_same('processed', SubmissionRepository::find($id)['status'], 'status kept after restore');
});

lcmt_it('Trashing an already trashed row keeps its original trashed_at', function () {
    $old = gmdate('Y-m-d H:i:s', time() - 5 * 86400);
    $id  = lcmt_it_insert(['trashed_at' => $old]);

    SubmissionRepository::trash([$id]);

    lcmt_assert_same($old, lcmt_tr_trashed_at($id));
});

lcmt_it('Trashed rows are left out of search, count and unread counts, and only shown by the trash filter', function () {
    $key   = lcmt_it_key();
    $keep  = lcmt_it_insert(['form_key' => $key]);
    $gone  = lcmt_it_insert(['form_key' => $key]);
    $spam  = lcmt_it_insert(['form_key' => $key, 'status' => 'spam']);
    $unread = SubmissionRepository::countUnread();

    SubmissionRepository::trash([$gone, $spam]);

    lcmt_assert_same([$keep], lcmt_it_ids(SubmissionRepository::search(['form_key' => $key])), 'default list');
    lcmt_assert_same(1, SubmissionRepository::count(['form_key' => $key]), 'count');
    lcmt_assert_same(0, SubmissionRepository::count(['form_key' => $key, 'status' => 'spam']), 'spam view');
    lcmt_assert_same($unread - 1, SubmissionRepository::countUnread(), 'unread');

    $trash = lcmt_it_ids(SubmissionRepository::search(['form_key' => $key, 'trash' => true]));
    sort($trash);
    lcmt_assert_same([$gone, $spam], $trash, 'trash view lists every status');
    lcmt_assert_same(2, SubmissionRepository::count(['form_key' => $key, 'trash' => true]), 'trash count');
    lcmt_assert_same([$gone], lcmt_it_ids(SubmissionRepository::search(['form_key' => $key, 'trash' => true, 'status' => 'new'])), 'filters work in the trash');
});

lcmt_it('Trashed rows are left out of the failure banner queries and the dashboard widget', function () {
    lcmt_it_failure_setup_tr();
    $id = lcmt_it_insert(['mail_sent' => 0, 'mail_error' => 'boom', 'created_at' => gmdate('Y-m-d H:i:s', time() - 300)]);
    $since = gmdate('Y-m-d H:i:s', time() - 600);

    $before = SubmissionRepository::countUnresolvedFailures($since);
    SubmissionRepository::trash([$id]);

    lcmt_assert_same($before - 1, SubmissionRepository::countUnresolvedFailures($since), 'count');
    lcmt_assert_same([], array_filter(SubmissionRepository::unresolvedFailures($since, 50), static fn(array $r) => (int) $r['id'] === $id), 'list');
});

function lcmt_it_failure_setup_tr(): void
{
    lcmt_sp_admin();
    update_option(FailureNotice::OPTION_DISMISSED_AT, gmdate('Y-m-d H:i:s', time() - 600));
}

lcmt_it('Trashed rows are left out of the statistics', function () {
    $key   = lcmt_it_key();
    $since = gmdate('Y-m-d H:i:s', time() - 86400);
    $a     = lcmt_it_insert(['form_key' => $key, 'mail_sent' => 0]);
    $b     = lcmt_it_insert(['form_key' => $key]);

    $totals = SubmissionRepository::totals($since)['total'];
    $failed = SubmissionRepository::totals($since)['failed'];
    $month  = array_sum(array_column(SubmissionRepository::countByMonth($since), 'total'));

    SubmissionRepository::trash([$a]);

    lcmt_assert_same($totals - 1, SubmissionRepository::totals($since)['total'], 'total');
    lcmt_assert_same($failed - 1, SubmissionRepository::totals($since)['failed'], 'failed');
    lcmt_assert_same($month - 1, array_sum(array_column(SubmissionRepository::countByMonth($since), 'total')), 'months');

    $byForm = array_column(SubmissionRepository::countBy('form_key', $since, 500), 'total', 'label');
    lcmt_assert_same(1, $byForm[$key] ?? null, 'countBy');
    unset($b);
});

lcmt_it('deleteTrashedBefore only deletes rows trashed before the cutoff', function () {
    $old    = lcmt_it_insert(['trashed_at' => gmdate('Y-m-d H:i:s', time() - 40 * 86400)]);
    $recent = lcmt_it_insert(['trashed_at' => gmdate('Y-m-d H:i:s', time() - 5 * 86400)]);
    $live   = lcmt_it_insert(['created_at' => gmdate('Y-m-d H:i:s', time() - 400 * 86400)]);

    SubmissionRepository::deleteTrashedBefore(gmdate('Y-m-d H:i:s', time() - 30 * 86400), 500);

    lcmt_assert_null(SubmissionRepository::find($old), 'old trashed deleted');
    lcmt_assert_true(SubmissionRepository::find($recent) !== null, 'recent trashed kept');
    lcmt_assert_true(SubmissionRepository::find($live) !== null, 'old but not trashed kept');
});

// ── Trash period ──

lcmt_it('The trash period follows EMPTY_TRASH_DAYS and the lcmt_mailer_trash_days filter', function () {
    lcmt_assert_same((int) EMPTY_TRASH_DAYS, Retention::trashDays());
    lcmt_assert_true(Retention::trashEnabled() === (EMPTY_TRASH_DAYS > 0), 'enabled');

    $filter = lcmt_tr_trash_days(12);
    lcmt_assert_same(12, Retention::trashDays());
    lcmt_assert_true(Retention::trashEnabled());
    remove_filter('lcmt_mailer_trash_days', $filter);

    $filter = lcmt_tr_trash_days(0);
    lcmt_assert_same(0, Retention::trashDays());
    lcmt_assert_same(false, Retention::trashEnabled());
    remove_filter('lcmt_mailer_trash_days', $filter);

    $filter = lcmt_tr_trash_days(-4);
    lcmt_assert_same(0, Retention::trashDays(), 'negative means disabled');
    remove_filter('lcmt_mailer_trash_days', $filter);
});

// ── Cron ──

lcmt_it('Retention::run empties only the rows trashed before the trash period', function () {
    $filter = lcmt_tr_trash_days(30);
    update_option(SubmissionSettings::OPTION_DAYS, 3650);
    update_option(SubmissionSettings::OPTION_ACTION, 'anonymize');
    update_option(SubmissionSettings::OPTION_STATS_DAYS, 0);

    $old    = lcmt_it_insert(['trashed_at' => gmdate('Y-m-d H:i:s', time() - 31 * 86400)]);
    $recent = lcmt_it_insert(['trashed_at' => gmdate('Y-m-d H:i:s', time() - 29 * 86400)]);
    $live   = lcmt_it_insert();

    $done = Retention::run();

    lcmt_assert_null(SubmissionRepository::find($old), 'old trashed row deleted');
    lcmt_assert_true(SubmissionRepository::find($recent) !== null, 'recent trashed row kept');
    lcmt_assert_true(SubmissionRepository::find($live) !== null, 'live row kept');
    lcmt_assert_true($done['trash'] >= 1, 'counted');

    remove_filter('lcmt_mailer_trash_days', $filter);
});

lcmt_it('Retention still anonymizes trashed rows that passed the retention period', function () {
    $filter = lcmt_tr_trash_days(30);
    update_option(SubmissionSettings::OPTION_DAYS, 30);
    update_option(SubmissionSettings::OPTION_ACTION, 'anonymize');
    update_option(SubmissionSettings::OPTION_STATS_DAYS, 0);

    $id = lcmt_it_insert(['created_at' => gmdate('Y-m-d H:i:s', time() - 60 * 86400), 'trashed_at' => gmdate('Y-m-d H:i:s', time() - 86400)]);

    Retention::run();

    $row = SubmissionRepository::find($id);
    lcmt_assert_null($row['fields'], 'personal data gone');
    lcmt_assert_true($row['trashed_at'] !== null, 'still in the trash');

    remove_filter('lcmt_mailer_trash_days', $filter);
});

lcmt_it('Retention in delete mode deletes trashed rows too', function () {
    $filter = lcmt_tr_trash_days(30);
    update_option(SubmissionSettings::OPTION_DAYS, 30);
    update_option(SubmissionSettings::OPTION_ACTION, 'delete');
    update_option(SubmissionSettings::OPTION_STATS_DAYS, 0);

    $id = lcmt_it_insert(['created_at' => gmdate('Y-m-d H:i:s', time() - 60 * 86400), 'trashed_at' => gmdate('Y-m-d H:i:s', time() - 86400)]);

    Retention::run();

    lcmt_assert_null(SubmissionRepository::find($id));
    remove_filter('lcmt_mailer_trash_days', $filter);
});

// ── apply(), handlers ──

lcmt_it('apply trashes and restores, and deletes at once when the trash is disabled', function () {
    $id = lcmt_it_insert(['status' => 'spam']);

    lcmt_assert_same('trashed', SubmissionsPage::apply('trash', [$id]));
    lcmt_assert_true(lcmt_tr_trashed_at($id) !== null, 'in the trash');

    lcmt_assert_same('restored', SubmissionsPage::apply('restore', [$id]));
    lcmt_assert_null(lcmt_tr_trashed_at($id), 'restored');

    $filter = lcmt_tr_trash_days(0);
    lcmt_assert_same('deleted', SubmissionsPage::apply('trash', [$id]));
    lcmt_assert_null(SubmissionRepository::find($id), 'deleted for good');
    remove_filter('lcmt_mailer_trash_days', $filter);
});

lcmt_it('A bulk Move to Trash redirects with the count and the ids for the Undo link', function () {
    lcmt_sp_admin();
    $a = lcmt_it_insert();
    $b = lcmt_it_insert();

    $query = lcmt_tr_bulk('trash', [$a, $b]);

    lcmt_assert_same('trashed', $query['notice'] ?? null);
    lcmt_assert_same('2', $query['n'] ?? null);
    lcmt_assert_same([(string) $a, (string) $b], $query['ids'] ?? null);
    lcmt_assert_true(lcmt_tr_trashed_at($a) !== null && lcmt_tr_trashed_at($b) !== null, 'both trashed');
});

lcmt_it('A bulk Restore and a bulk permanent delete redirect with their notice and count', function () {
    lcmt_sp_admin();
    $a = lcmt_it_insert(['trashed_at' => gmdate('Y-m-d H:i:s')]);
    $b = lcmt_it_insert(['trashed_at' => gmdate('Y-m-d H:i:s')]);

    $query = lcmt_tr_bulk('restore', [$a]);
    lcmt_assert_same('restored', $query['notice'] ?? null);
    lcmt_assert_same('1', $query['n'] ?? null);
    lcmt_assert_null(lcmt_tr_trashed_at($a), 'restored');

    $query = lcmt_tr_bulk('delete', [$b], ['status' => 'trash']);
    lcmt_assert_same('deleted', $query['notice'] ?? null);
    lcmt_assert_same('1', $query['n'] ?? null);
    lcmt_assert_null(SubmissionRepository::find($b), 'deleted');
});

lcmt_it('A bulk action needs a valid nonce and the capability', function () {
    lcmt_sp_admin();
    $id = lcmt_it_insert();

    $_REQUEST['_wpnonce'] = 'bad';
    $failed = lcmt_tr_dies(fn() => lcmt_sp_with_get(
        ['page' => SubmissionsPage::PAGE_SLUG, 'action' => 'trash', 'ids' => [(string) $id], '_wpnonce' => 'bad'],
        fn() => SubmissionsPage::handleLoad()
    ));
    unset($_REQUEST['_wpnonce']);
    lcmt_assert_true($failed, 'bad nonce refused');
    lcmt_assert_null(lcmt_tr_trashed_at($id), 'untouched (nonce)');

    wp_set_current_user(0);
    $_REQUEST['_wpnonce'] = wp_create_nonce('bulk-submissions');
    $failed = lcmt_tr_dies(fn() => lcmt_sp_with_get(
        ['page' => SubmissionsPage::PAGE_SLUG, 'action' => 'trash', 'ids' => [(string) $id], '_wpnonce' => $_REQUEST['_wpnonce']],
        fn() => SubmissionsPage::handleLoad()
    ));
    unset($_REQUEST['_wpnonce']);
    lcmt_assert_true($failed, 'no capability refused');
    lcmt_assert_null(lcmt_tr_trashed_at($id), 'untouched (capability)');
});

lcmt_it('The single row actions trash, restore and delete for good', function () {
    lcmt_sp_admin();
    $id = lcmt_it_insert();

    $query = lcmt_tr_single($id, 'trash');
    lcmt_assert_same('trashed', $query['notice'] ?? null);
    lcmt_assert_same('1', $query['n'] ?? null);
    lcmt_assert_same([(string) $id], $query['ids'] ?? null);
    lcmt_assert_true(lcmt_tr_trashed_at($id) !== null, 'trashed');

    $query = lcmt_tr_single($id, 'restore');
    lcmt_assert_same('restored', $query['notice'] ?? null);
    lcmt_assert_null(lcmt_tr_trashed_at($id), 'restored');

    lcmt_tr_single($id, 'trash');
    $query = lcmt_tr_single($id, 'delete');
    lcmt_assert_same('deleted', $query['notice'] ?? null);
    lcmt_assert_null(SubmissionRepository::find($id), 'deleted');
});

lcmt_it('The Empty Trash button deletes every trashed row, and only them, with the nonce', function () {
    lcmt_sp_admin();
    $t1   = lcmt_it_insert(['trashed_at' => gmdate('Y-m-d H:i:s')]);
    $t2   = lcmt_it_insert(['trashed_at' => gmdate('Y-m-d H:i:s')]);
    $live = lcmt_it_insert();

    $get = ['page' => SubmissionsPage::PAGE_SLUG, 'status' => 'trash', 'empty_trash' => '1', '_wpnonce' => 'bad'];
    $_REQUEST['_wpnonce'] = 'bad';
    $failed = lcmt_tr_dies(fn() => lcmt_sp_with_get($get, fn() => SubmissionsPage::handleLoad()));
    unset($_REQUEST['_wpnonce']);
    lcmt_assert_true($failed, 'bad nonce refused');
    lcmt_assert_true(SubmissionRepository::find($t1) !== null, 'still there');

    $query = lcmt_tr_bulk('-1', [], ['status' => 'trash', 'empty_trash' => '1']);

    lcmt_assert_same('emptied', $query['notice'] ?? null);
    lcmt_assert_true((int) ($query['n'] ?? 0) >= 2, 'count');
    lcmt_assert_null(SubmissionRepository::find($t1));
    lcmt_assert_null(SubmissionRepository::find($t2));
    lcmt_assert_true(SubmissionRepository::find($live) !== null, 'live row kept');
    lcmt_assert_same(false, isset($query['status']), 'back to the list once the trash is empty');
});

// ── Screen ──

lcmt_it('The Trash view link shows with its count only when something is trashed', function () {
    lcmt_sp_admin();

    $id = lcmt_it_insert(['trashed_at' => gmdate('Y-m-d H:i:s')]);
    $n  = SubmissionRepository::count(['trash' => true]);

    $html = lcmt_sp_render(['post_type' => 'mail', 'page' => SubmissionsPage::PAGE_SLUG]);

    lcmt_assert_true(substr_count($html, 'status=trash') > 0, 'link present');
    lcmt_assert_same(1, preg_match('#<li class=\'trash\'><a href="[^"]*status=trash[^"]*">Trash <span class="count">\(' . $n . '\)</span></a></li>\s*</ul>#', $html), 'Trash (N) is the last view');

    SubmissionRepository::delete([$id]);
    global $wpdb;
    $wpdb->query('DELETE FROM ' . SubmissionRepository::table() . ' WHERE trashed_at IS NOT NULL');

    $html = lcmt_sp_render(['post_type' => 'mail', 'page' => SubmissionsPage::PAGE_SLUG]);
    lcmt_assert_true(strpos($html, 'status=trash') === false, 'hidden at zero');
});

lcmt_it('The Trash view is hidden when the trash is disabled', function () {
    lcmt_sp_admin();
    lcmt_it_insert(['trashed_at' => gmdate('Y-m-d H:i:s')]);
    $filter = lcmt_tr_trash_days(0);

    $html = lcmt_sp_render(['post_type' => 'mail', 'page' => SubmissionsPage::PAGE_SLUG]);

    lcmt_assert_true(strpos($html, 'status=trash') === false, 'no view');
    lcmt_assert_true(strpos($html, 'Move to Trash') === false, 'no trash action');
    remove_filter('lcmt_mailer_trash_days', $filter);
});

lcmt_it('The normal list offers Trash without a confirm, the trash view Restore and Delete permanently with one', function () {
    lcmt_sp_admin();
    $key = lcmt_it_key();
    lcmt_it_insert(['form_key' => $key]);

    $html = lcmt_sp_render(['post_type' => 'mail', 'page' => SubmissionsPage::PAGE_SLUG, 'form_key' => $key]);

    lcmt_assert_true(strpos($html, 'class="submitdelete"') !== false && strpos($html, '>Trash</a>') !== false, 'row Trash');
    lcmt_assert_true(strpos($html, 'onclick="return confirm(') === false, 'no confirm on a reversible action');
    lcmt_assert_true(strpos($html, '<option value="trash">Move to Trash</option>') !== false, 'bulk Move to Trash');
    lcmt_assert_true(strpos($html, 'Delete permanently') === false, 'no permanent delete in the normal list');
    lcmt_assert_true(strpos($html, 'Empty Trash') === false, 'no Empty Trash');

    $trashKey = lcmt_it_key();
    lcmt_it_insert(['form_key' => $trashKey, 'trashed_at' => gmdate('Y-m-d H:i:s')]);

    $html = lcmt_sp_render(['post_type' => 'mail', 'page' => SubmissionsPage::PAGE_SLUG, 'status' => 'trash', 'form_key' => $trashKey]);

    lcmt_assert_true(strpos($html, '>Restore</a>') !== false, 'row Restore');
    lcmt_assert_true(strpos($html, '>Delete permanently</a>') !== false, 'row Delete permanently');
    lcmt_assert_true(strpos($html, 'onclick="return confirm(') !== false, 'confirm on permanent delete');
    lcmt_assert_true(strpos($html, '<option value="restore">Restore</option>') !== false, 'bulk Restore');
    lcmt_assert_true(strpos($html, '<option value="delete">Delete permanently</option>') !== false, 'bulk Delete permanently');
    lcmt_assert_true(strpos($html, '<option value="trash">') === false, 'no bulk trash in the trash');
    lcmt_assert_true(strpos($html, 'name="empty_trash"') !== false, 'Empty Trash button');
    lcmt_assert_true(strpos($html, 'Empty Trash') !== false, 'Empty Trash label');
});

lcmt_it('The trash view lists only trashed rows and hides them from the other views', function () {
    lcmt_sp_admin();
    $key = lcmt_it_key();
    lcmt_it_insert(['form_key' => $key, 'fields' => lcmt_sp_fields(['name' => ['text', 'Liveperson']])]);
    lcmt_it_insert(['form_key' => $key, 'trashed_at' => gmdate('Y-m-d H:i:s'), 'fields' => lcmt_sp_fields(['name' => ['text', 'Trashedperson']])]);

    $list  = lcmt_sp_render(['post_type' => 'mail', 'page' => SubmissionsPage::PAGE_SLUG, 'form_key' => $key]);
    $trash = lcmt_sp_render(['post_type' => 'mail', 'page' => SubmissionsPage::PAGE_SLUG, 'form_key' => $key, 'status' => 'trash']);

    lcmt_assert_true(strpos($list, 'Liveperson') !== false && strpos($list, 'Trashedperson') === false, 'normal list');
    lcmt_assert_true(strpos($trash, 'Trashedperson') !== false && strpos($trash, 'Liveperson') === false, 'trash list');
});

lcmt_it('The CSV export follows the view: the trash exports the trash', function () {
    lcmt_sp_admin();
    $key = lcmt_it_key();
    lcmt_it_insert(['form_key' => $key, 'fields' => lcmt_sp_fields(['name' => ['text', 'Liveperson']])]);
    lcmt_it_insert(['form_key' => $key, 'trashed_at' => gmdate('Y-m-d H:i:s'), 'fields' => lcmt_sp_fields(['name' => ['text', 'Trashedperson']])]);

    $csv = function (array $get): string {
        return lcmt_sp_with_get($get, function () {
            $out = fopen('php://memory', 'w+');
            SubmissionsPage::writeCsv($out, SubmissionRepository::search(SubmissionsPage::filtersFromRequest()));
            rewind($out);

            return (string) stream_get_contents($out);
        });
    };

    $normal = $csv(['form_key' => $key]);
    $trash  = $csv(['form_key' => $key, 'status' => 'trash']);

    lcmt_assert_true(strpos($normal, 'Liveperson') !== false && strpos($normal, 'Trashedperson') === false, 'normal export');
    lcmt_assert_true(strpos($trash, 'Trashedperson') !== false && strpos($trash, 'Liveperson') === false, 'trash export');

    $url = lcmt_sp_with_get(['status' => 'trash'], fn() => SubmissionsPage::exportUrl());
    lcmt_assert_true(strpos($url, 'status=trash') !== false, 'export link keeps the trash view');
});

lcmt_it('listQueryArgs keeps the trash view', function () {
    lcmt_assert_same(['status' => 'trash'], lcmt_sp_with_get(['status' => 'trash'], fn() => SubmissionsPage::listQueryArgs()));
});

lcmt_it('The notices are counted and the trashed notice carries a nonce-protected Undo link', function () {
    lcmt_sp_admin();
    $id = lcmt_it_insert();

    $base = ['post_type' => 'mail', 'page' => SubmissionsPage::PAGE_SLUG];

    $html = lcmt_sp_render($base + ['notice' => 'trashed', 'n' => '1', 'ids' => [(string) $id]]);
    lcmt_assert_true(strpos($html, '1 message moved to the Trash.') !== false, 'singular');
    lcmt_assert_same(1, preg_match('#<a href="([^"]*)">Undo</a>#', $html, $m), 'undo link');

    $undo = lcmt_tr_query(html_entity_decode($m[1]));
    lcmt_assert_same('restore', $undo['action'] ?? null);
    lcmt_assert_same([(string) $id], $undo['ids'] ?? null);
    lcmt_assert_true((bool) wp_verify_nonce($undo['_wpnonce'] ?? '', 'bulk-submissions'), 'nonce');

    $html = lcmt_sp_render($base + ['notice' => 'trashed', 'n' => '3', 'ids' => ['1', '2', '3']]);
    lcmt_assert_true(strpos($html, '3 messages moved to the Trash.') !== false, 'plural');

    lcmt_assert_true(strpos(lcmt_sp_render($base + ['notice' => 'restored', 'n' => '2']), '2 messages restored from the Trash.') !== false, 'restored');
    lcmt_assert_true(strpos(lcmt_sp_render($base + ['notice' => 'restored', 'n' => '1']), '1 message restored from the Trash.') !== false, 'restored singular');
    lcmt_assert_true(strpos(lcmt_sp_render($base + ['notice' => 'deleted', 'n' => '2']), '2 messages permanently deleted.') !== false, 'deleted');
    lcmt_assert_true(strpos(lcmt_sp_render($base + ['notice' => 'deleted', 'n' => '1']), '1 message permanently deleted.') !== false, 'deleted singular');
    lcmt_assert_true(strpos(lcmt_sp_render($base + ['notice' => 'emptied', 'n' => '4']), '4 messages permanently deleted.') !== false, 'emptied');
});

lcmt_it('Following the Undo link restores the messages', function () {
    lcmt_sp_admin();
    $id = lcmt_it_insert(['status' => 'read']);

    SubmissionsPage::apply('trash', [$id]);
    lcmt_assert_true(lcmt_tr_trashed_at($id) !== null, 'precondition');

    // The very URL of the link, parsed back into the request the handler sees.
    $get = lcmt_tr_query(html_entity_decode(SubmissionsPage::undoUrl([$id])));
    $_REQUEST['_wpnonce'] = $get['_wpnonce'];

    try {
        $query = lcmt_tr_query(lcmt_tr_redirect(fn() => lcmt_sp_with_get($get, fn() => SubmissionsPage::handleLoad())));
    } finally {
        unset($_REQUEST['_wpnonce']);
    }

    lcmt_assert_same('restored', $query['notice'] ?? null);
    lcmt_assert_same('1', $query['n'] ?? null);
    lcmt_assert_null(lcmt_tr_trashed_at($id), 'restored');
    lcmt_assert_same('read', SubmissionRepository::find($id)['status'], 'status kept');
});

lcmt_it('Restoring a new message from the trash row action does not open it, so it stays new', function () {
    lcmt_sp_admin();
    $keep = lcmt_it_insert(['trashed_at' => gmdate('Y-m-d H:i:s')]);
    $id   = lcmt_it_insert(['status' => 'new', 'trashed_at' => gmdate('Y-m-d H:i:s')]);

    $query = lcmt_tr_single($id, 'restore');

    lcmt_assert_same(false, isset($query['submission']), 'not the detail');
    lcmt_assert_same('trash', $query['status'] ?? null, 'back to the trash while it has rows');
    lcmt_assert_same('new', SubmissionRepository::find($id)['status'], 'still unread');
    lcmt_assert_null(lcmt_tr_trashed_at($id));

    // Last message of the trash: the trash view is gone, so the normal list.
    global $wpdb;
    $wpdb->query('DELETE FROM ' . SubmissionRepository::table() . ' WHERE trashed_at IS NOT NULL');
    $last = lcmt_it_insert(['status' => 'new', 'trashed_at' => gmdate('Y-m-d H:i:s')]);

    $query = lcmt_tr_single($last, 'restore');
    lcmt_assert_same(false, isset($query['status']), 'normal list');
    lcmt_assert_same(false, isset($query['submission']), 'not the detail');
    unset($keep);
});

lcmt_it('Mark as unread on a trashed message goes back to the trash', function () {
    lcmt_sp_admin();
    lcmt_it_insert(['trashed_at' => gmdate('Y-m-d H:i:s')]);
    $id = lcmt_it_insert(['status' => 'read', 'trashed_at' => gmdate('Y-m-d H:i:s')]);

    $query = lcmt_tr_single($id, 'new');

    lcmt_assert_same('trash', $query['status'] ?? null);
    lcmt_assert_same(false, isset($query['submission']));
    lcmt_assert_true(lcmt_tr_trashed_at($id) !== null, 'still trashed');
});

lcmt_it('Notice counts are the rows really changed, and nothing is reported for zero', function () {
    lcmt_sp_admin();
    $trashed = lcmt_it_insert(['trashed_at' => gmdate('Y-m-d H:i:s')]);
    $live    = lcmt_it_insert();

    $query = lcmt_tr_bulk('trash', [$trashed, $live]);
    lcmt_assert_same('1', $query['n'] ?? null, 'only the live row moved');
    lcmt_assert_same([(string) $trashed, (string) $live], $query['ids'] ?? null, 'ids kept for the undo');

    $query = lcmt_tr_bulk('restore', [$live, $live]);
    lcmt_assert_same('1', $query['n'] ?? null, 'restored once');

    $query = lcmt_tr_bulk('restore', [$live]);
    lcmt_assert_same('0', $query['n'] ?? null, 'nothing to restore');

    $base = ['post_type' => 'mail', 'page' => SubmissionsPage::PAGE_SLUG];
    lcmt_assert_true(strpos(lcmt_sp_render($base + ['notice' => 'restored', 'n' => '0']), 'restored from the Trash') === false, 'no notice at zero');
    lcmt_assert_true(strpos(lcmt_sp_render($base + ['notice' => 'emptied', 'n' => '0']), 'permanently deleted') === false, 'no emptied notice at zero');
});

lcmt_it('A message in the trash cannot be sent again', function () {
    lcmt_it_mail_ok();
    [, $key] = lcmt_it_template();
    $id      = lcmt_it_insert(['form_key' => $key, 'mail_sent' => 0]);

    // Control: the same row is sent again when it is not trashed.
    lcmt_assert_same('resent', SubmissionsPage::apply('resend', [$id]), 'control');

    SubmissionRepository::update($id, ['mail_sent' => 0]);
    SubmissionRepository::trash([$id]);

    lcmt_assert_same('resend_failed', SubmissionsPage::apply('resend', [$id]));
    lcmt_assert_same(0, (int) SubmissionRepository::find($id)['mail_sent'], 'nothing sent');
});

lcmt_it('With the trash disabled, the cron deletes every row already in it', function () {
    $filter = lcmt_tr_trash_days(0);
    update_option(SubmissionSettings::OPTION_DAYS, 3650);
    update_option(SubmissionSettings::OPTION_ACTION, 'anonymize');
    update_option(SubmissionSettings::OPTION_STATS_DAYS, 0);

    $id   = lcmt_it_insert(['trashed_at' => gmdate('Y-m-d H:i:s', time() - 60)]);
    $live = lcmt_it_insert();

    $done = Retention::run();
    remove_filter('lcmt_mailer_trash_days', $filter);

    lcmt_assert_null(SubmissionRepository::find($id), 'trashed row deleted');
    lcmt_assert_true(SubmissionRepository::find($live) !== null, 'live row kept');
    lcmt_assert_true($done['trash'] >= 1, 'counted');
});

lcmt_it('The detail of a trashed message has a notice, Restore and Delete permanently, and no resend', function () {
    lcmt_sp_admin();
    $id = lcmt_it_insert(['mail_sent' => 0, 'mail_error' => 'boom', 'trashed_at' => gmdate('Y-m-d H:i:s')]);

    $html = lcmt_sp_render(['submission' => (string) $id]);

    lcmt_assert_true(strpos($html, 'This message is in the trash.') !== false, 'notice');
    lcmt_assert_true(strpos($html, '>Restore</a>') !== false, 'restore');
    lcmt_assert_true(strpos($html, '>Delete permanently</a>') !== false, 'delete permanently');
    lcmt_assert_true(strpos($html, 'Send the email again') === false, 'no resend');
    lcmt_assert_true(strpos($html, 'Move to Trash') === false, 'no move to trash');
});

lcmt_it('The detail of a live message offers Move to Trash instead of Delete, and still resends', function () {
    lcmt_sp_admin();
    $id = lcmt_it_insert(['mail_sent' => 0, 'mail_error' => 'boom']);

    $html = lcmt_sp_render(['submission' => (string) $id]);

    lcmt_assert_true(strpos($html, 'Move to Trash') !== false, 'move to trash');
    lcmt_assert_true(strpos($html, 'do=trash') !== false, 'trash action');
    lcmt_assert_true(strpos($html, '>Delete</a>') === false, 'no plain Delete');
    lcmt_assert_true(strpos($html, 'This message is in the trash.') === false, 'no notice');
    lcmt_assert_true(strpos($html, 'Send the email again') !== false, 'resend');
});

lcmt_it('Purge now reports the trashed messages it deleted', function () {
    lcmt_sp_admin();
    $filter = lcmt_tr_trash_days(30);
    $id     = lcmt_it_insert(['trashed_at' => gmdate('Y-m-d H:i:s', time() - 40 * 86400)]);

    $_REQUEST['_wpnonce'] = wp_create_nonce(SubmissionSettings::PURGE_ACTION);
    try {
        $query = lcmt_tr_query(lcmt_tr_redirect(fn() => SubmissionSettings::handlePurgeNow()));
    } finally {
        unset($_REQUEST['_wpnonce']);
        remove_filter('lcmt_mailer_trash_days', $filter);
    }

    lcmt_assert_null(SubmissionRepository::find($id), 'emptied');
    lcmt_assert_true((int) ($query['trash'] ?? 0) >= 1, 'count in the redirect');

    $html = lcmt_sp_page(SubmissionSettings::class, ['anonymized' => '0', 'deleted' => '0', 'trash' => '3']);
    lcmt_assert_true(strpos($html, '3 trashed messages') !== false, 'notice mentions the trash');

    $html = lcmt_sp_page(SubmissionSettings::class, ['anonymized' => '0', 'deleted' => '0']);
    lcmt_assert_true(strpos($html, 'trashed message') === false, 'no mention at zero');
});

// ── Privacy ──

lcmt_it('The privacy exporter and eraser still see trashed rows', function () {
    $email = 'trash-' . uniqid() . '@example.com';
    $id    = lcmt_it_insert([
        'trashed_at' => gmdate('Y-m-d H:i:s'),
        'fields'     => lcmt_sp_fields(['email' => ['email', $email]]),
    ]);

    $export = Privacy::export($email);
    lcmt_assert_count(1, $export['data'], 'exported');

    Privacy::erase($email);
    lcmt_assert_null(SubmissionRepository::find($id)['fields'], 'anonymized');
});

// ── Scheduled deletion ──

lcmt_it('The trash view says messages are deleted automatically after the trash period', function () {
    lcmt_sp_admin();
    lcmt_it_insert(['form_key' => lcmt_it_key(), 'trashed_at' => gmdate('Y-m-d H:i:s')]);

    $trash = lcmt_sp_render(['status' => 'trash']);
    lcmt_assert_same(false, str_contains($trash, 'notice-info'), 'no notice box pushing the list down');
    lcmt_assert_same(1, preg_match('#name="empty_trash".*?<span class="lcmt-trash-info"><span class="dashicons dashicons-clock" aria-hidden="true"></span> Messages in the trash#s', $trash), 'beside Empty Trash');
    lcmt_assert_true(str_contains($trash, 'Messages in the trash are deleted automatically after 30 days.'), 'period');

    lcmt_assert_same(false, str_contains(lcmt_sp_render([]), 'deleted automatically after'), 'not on the normal list');
});

lcmt_it('The trash view shows when each message will be deleted, in red when it is soon', function () {
    lcmt_sp_admin();
    $key = lcmt_it_key();
    $ago = static fn(int $days) => gmdate('Y-m-d H:i:s', time() - $days * 86400 - 60);

    lcmt_it_insert(['form_key' => $key, 'trashed_at' => $ago(1)]);  // due in 29 days
    lcmt_it_insert(['form_key' => $key, 'trashed_at' => $ago(27)]); // due in 3 days
    lcmt_it_insert(['form_key' => $key, 'trashed_at' => $ago(29)]); // due tomorrow
    lcmt_it_insert(['form_key' => $key, 'trashed_at' => $ago(31)]); // overdue: next run

    $html = lcmt_sp_render(['status' => 'trash', 'form_key' => $key]);

    lcmt_assert_true(str_contains($html, '>Deletion</th>'), 'column in the trash view');
    lcmt_assert_same(1, preg_match('#column-trashed_at[^>]*>\s*<span title="[^"]+">In 29 days</span>#', $html), 'far: plain');
    lcmt_assert_true(str_contains($html, 'lcmt-badge lcmt-badge--expiring" title="'), 'soon: red badge');
    lcmt_assert_same(1, preg_match('#lcmt-badge--expiring" title="[^"]+">In 3 days<#', $html), 'in 3 days');
    lcmt_assert_same(1, preg_match('#lcmt-badge--expiring" title="[^"]+">Tomorrow<#', $html), 'tomorrow');
    lcmt_assert_same(1, preg_match('#lcmt-badge--expiring" title="[^"]+">Today<#', $html), 'overdue reads today');

    lcmt_assert_same(false, str_contains(lcmt_sp_render(['form_key' => $key]), '>Deletion</th>'), 'no column outside the trash');
});

lcmt_it('A trashed message says when it will be deleted', function () {
    lcmt_sp_admin();
    $id = lcmt_it_insert(['trashed_at' => gmdate('Y-m-d H:i:s', time() - 27 * 86400 - 60)]);

    $html = lcmt_sp_render(['submission' => $id]);

    lcmt_assert_same(1, preg_match('#This message is in the trash\. It will be deleted for good in 3 days \([^)]+\)\.#', $html), 'detail');
});
