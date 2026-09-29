<?php

use LcmtDevMailer\Attribution;
use LcmtDevMailer\Retention;
use LcmtDevMailer\SubmissionRepository;
use LcmtDevMailer\SubmissionSettings;

require_once __DIR__ . '/submissions-page-helpers.php';

/**
 * A row created $days days ago, with a value in every context column.
 */
function lcmt_rt_row(string $key, int $days, array $overrides = []): int
{
    return lcmt_it_insert($overrides + [
        'form_key'      => $key,
        'created_at'    => gmdate('Y-m-d H:i:s', time() - $days * 86400),
        'mail_error'    => 'SMTP connect() failed.',
        'mail_sent'     => 0,
        'status'        => 'read',
        'page_path'     => '/contact/',
        'landing_path'  => '/landing/',
        'referrer_host' => 'www.google.com',
        'channel'       => 'paid',
        'utm_source'    => 'google',
        'utm_medium'    => 'cpc',
        'utm_campaign'  => 'spring',
        'click_id_type' => 'gclid',
        'device'        => 'mobile',
        'locale'        => 'fr-FR',
        'form_seconds'  => 42,
    ]);
}

function lcmt_rt_row_data(int $id): array
{
    global $wpdb;

    return $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . SubmissionRepository::table() . ' WHERE id = %d', $id), ARRAY_A) ?: [];
}

function lcmt_rt_exists(int $id): bool
{
    return lcmt_rt_row_data($id) !== [];
}

function lcmt_rt_options(int $days, string $action, int $stats): void
{
    update_option(SubmissionSettings::OPTION_DAYS, $days);
    update_option(SubmissionSettings::OPTION_ACTION, $action);
    update_option(SubmissionSettings::OPTION_STATS_DAYS, $stats);
    wp_cache_delete('alloptions', 'options');
}

// ── Settings getters ──

lcmt_it('SubmissionSettings getters have defaults when the options are absent', function () {
    delete_option(SubmissionSettings::OPTION_DAYS);
    delete_option(SubmissionSettings::OPTION_ACTION);
    delete_option(SubmissionSettings::OPTION_STATS_DAYS);

    lcmt_assert_same(1095, SubmissionSettings::retentionDays());
    lcmt_assert_same('anonymize', SubmissionSettings::retentionAction());
    lcmt_assert_same(0, SubmissionSettings::statsRetentionDays());
});

lcmt_it('SubmissionSettings getters fall back on garbage values', function () {
    foreach (['abc', '-5', '0', ''] as $value) {
        update_option(SubmissionSettings::OPTION_DAYS, $value);
        lcmt_assert_same(1095, SubmissionSettings::retentionDays(), var_export($value, true));
    }

    update_option(SubmissionSettings::OPTION_DAYS, '99999');
    lcmt_assert_same(3650, SubmissionSettings::retentionDays());

    update_option(SubmissionSettings::OPTION_STATS_DAYS, '-3');
    lcmt_assert_same(0, SubmissionSettings::statsRetentionDays());

    update_option(SubmissionSettings::OPTION_ACTION, 'weird');
    lcmt_assert_same('anonymize', SubmissionSettings::retentionAction());

    update_option(SubmissionSettings::OPTION_ACTION, 'delete');
    lcmt_assert_same('delete', SubmissionSettings::retentionAction());
});

// ── Registered sanitize callbacks ──

lcmt_it('registered sanitize callbacks clamp and normalize what the form sends', function () {
    SubmissionSettings::registerSettings();

    lcmt_assert_same(1095, sanitize_option(SubmissionSettings::OPTION_DAYS, '0'));
    lcmt_assert_same(3650, sanitize_option(SubmissionSettings::OPTION_DAYS, '99999'));
    lcmt_assert_same(365, sanitize_option(SubmissionSettings::OPTION_DAYS, '365'));
    lcmt_assert_same(1095, sanitize_option(SubmissionSettings::OPTION_DAYS, '-5'));

    lcmt_assert_same('delete', sanitize_option(SubmissionSettings::OPTION_ACTION, 'delete'));
    lcmt_assert_same('anonymize', sanitize_option(SubmissionSettings::OPTION_ACTION, 'x'));
    lcmt_assert_same('anonymize', sanitize_option(SubmissionSettings::OPTION_ACTION, 'anonymize'));

    lcmt_assert_same(0, sanitize_option(SubmissionSettings::OPTION_STATS_DAYS, '0'));
    lcmt_assert_same(0, sanitize_option(SubmissionSettings::OPTION_STATS_DAYS, '-4'));
    lcmt_assert_same(90, sanitize_option(SubmissionSettings::OPTION_STATS_DAYS, '90'));

    lcmt_assert_same('0', sanitize_option(Attribution::OPTION_WITHOUT_CONSENT, null));
    lcmt_assert_same('1', sanitize_option(Attribution::OPTION_WITHOUT_CONSENT, '1'));
});

// ── Retention::run() ──

lcmt_it('Retention anonymizes an old row, keeps its context and leaves recent rows alone', function () {
    lcmt_rt_options(30, 'anonymize', 0);
    $key = lcmt_it_key();
    $old = lcmt_rt_row($key, 40);
    $new = lcmt_rt_row($key, 10);

    $before = lcmt_rt_row_data($old);
    lcmt_assert_true($before['fields'] !== null && $before['mail_error'] !== null, 'precondition');

    $done = Retention::run();

    $after = lcmt_rt_row_data($old);
    lcmt_assert_null($after['fields'], 'fields');
    lcmt_assert_null($after['mail_error'], 'mail_error');
    lcmt_assert_true($after['anonymized_at'] !== null, 'anonymized_at set');

    foreach (['form_key', 'page_path', 'landing_path', 'referrer_host', 'channel', 'utm_source', 'utm_medium',
        'utm_campaign', 'click_id_type', 'device', 'locale', 'status', 'mail_sent', 'mail_post_id', 'page_id'] as $column) {
        lcmt_assert_same($before[$column], $after[$column], $column);
    }

    // Only the day stays, and no exact duration, so the row cannot be matched back to a visit.
    lcmt_assert_same(substr($before['created_at'], 0, 10) . ' 00:00:00', $after['created_at'], 'created_at truncated to the day');
    lcmt_assert_null($after['form_seconds'], 'form_seconds dropped');

    $recent = lcmt_rt_row_data($new);
    lcmt_assert_true($recent['fields'] !== null, 'recent fields kept');
    lcmt_assert_same('SMTP connect() failed.', $recent['mail_error'], 'recent mail_error kept');
    lcmt_assert_null($recent['anonymized_at'], 'recent not anonymized');

    lcmt_assert_true($done['anonymized'] >= 1, 'counts anonymized');
    lcmt_assert_same(0, $done['deleted'], 'nothing deleted with stats at 0');
});

lcmt_it('Retention keeps the original anonymized_at of an already anonymized row', function () {
    lcmt_rt_options(30, 'anonymize', 0);
    $key = lcmt_it_key();
    $id  = lcmt_rt_row($key, 400, ['fields' => null, 'mail_error' => null, 'anonymized_at' => '2021-02-03 04:05:06']);

    Retention::run();

    lcmt_assert_same('2021-02-03 04:05:06', lcmt_rt_row_data($id)['anonymized_at']);
});

lcmt_it('Retention leaves a row 29 days old alone with a 30 day period', function () {
    lcmt_rt_options(30, 'anonymize', 0);
    $id = lcmt_rt_row(lcmt_it_key(), 29);

    Retention::run();

    lcmt_assert_null(lcmt_rt_row_data($id)['anonymized_at']);
});

lcmt_it('Retention deletes old rows in delete mode', function () {
    lcmt_rt_options(30, 'delete', 0);
    $key = lcmt_it_key();
    $old = lcmt_rt_row($key, 40);
    $new = lcmt_rt_row($key, 10);

    $done = Retention::run();

    lcmt_assert_same(false, lcmt_rt_exists($old), 'old row gone');
    lcmt_assert_true(lcmt_rt_exists($new), 'recent row stays');
    lcmt_assert_true($done['deleted'] >= 1, 'counts deleted');
    lcmt_assert_same(0, $done['anonymized']);
});

lcmt_it('Retention deletes old anonymized statistics only', function () {
    lcmt_rt_options(30, 'anonymize', 30);
    $key      = lcmt_it_key();
    $oldAnon  = lcmt_rt_row($key, 100, ['fields' => null, 'mail_error' => null, 'anonymized_at' => '2024-01-01 00:00:00']);
    $oldPlain = lcmt_rt_row($key, 100);
    $recent   = lcmt_rt_row($key, 10);
    $recentAn = lcmt_rt_row($key, 10, ['fields' => null, 'mail_error' => null, 'anonymized_at' => '2024-01-01 00:00:00']);

    $done = Retention::run();

    lcmt_assert_same(false, lcmt_rt_exists($oldAnon), 'old anonymized row deleted');
    // The plain old row is anonymized by the first step, then deleted by the second in the same run.
    lcmt_assert_same(false, lcmt_rt_exists($oldPlain), 'old row anonymized then deleted as statistics');
    lcmt_assert_true(lcmt_rt_exists($recent), 'recent row stays');
    lcmt_assert_null(lcmt_rt_row_data($recent)['anonymized_at'], 'recent row untouched');
    lcmt_assert_true(lcmt_rt_exists($recentAn), 'recent anonymized row stays');
    lcmt_assert_true($done['anonymized'] >= 1 && $done['deleted'] >= 2, 'counts');
});

lcmt_it('Retention with statistics at 0 deletes nothing', function () {
    lcmt_rt_options(30, 'anonymize', 0);
    $id = lcmt_rt_row(lcmt_it_key(), 5000, ['fields' => null, 'mail_error' => null, 'anonymized_at' => '2020-01-01 00:00:00']);

    $done = Retention::run();

    lcmt_assert_true(lcmt_rt_exists($id), 'anonymized statistics kept forever');
    lcmt_assert_same(0, $done['deleted']);
});

lcmt_it('Retention drains more rows than one batch', function () {
    lcmt_rt_options(30, 'anonymize', 0);
    $key = lcmt_it_key();
    $ids = [];

    for ($i = 0; $i < Retention::BATCH + 1; $i++) {
        $ids[] = lcmt_rt_row($key, 40);
    }

    $done = Retention::run();

    global $wpdb;
    $left = (int) $wpdb->get_var($wpdb->prepare(
        'SELECT COUNT(*) FROM ' . SubmissionRepository::table() . ' WHERE form_key = %s AND (anonymized_at IS NULL OR fields IS NOT NULL)',
        $key
    ));

    lcmt_assert_same(0, $left, 'every row anonymized');
    lcmt_assert_true($done['anonymized'] >= Retention::BATCH + 1, 'count spans two batches');
});

// ── Scheduling ──

function lcmt_rt_events(): array
{
    $events = [];

    foreach ((array) _get_cron_array() as $timestamp => $hooks) {
        foreach ($hooks[Retention::CRON_HOOK] ?? [] as $event) {
            $events[] = ['timestamp' => $timestamp, 'schedule' => $event['schedule']];
        }
    }

    return $events;
}

lcmt_it('Retention schedule registers one daily event and is idempotent', function () {
    Retention::unschedule();
    lcmt_assert_same(false, wp_next_scheduled(Retention::CRON_HOOK), 'cleared first');

    Retention::schedule();
    Retention::schedule();

    $events = lcmt_rt_events();
    lcmt_assert_count(1, $events, 'one event');
    lcmt_assert_same('daily', $events[0]['schedule']);
    lcmt_assert_true(wp_next_scheduled(Retention::CRON_HOOK) > time(), 'in the future');
});

lcmt_it('Retention unschedule clears the event', function () {
    Retention::schedule();
    Retention::unschedule();

    lcmt_assert_same(false, wp_next_scheduled(Retention::CRON_HOOK));
    lcmt_assert_count(0, lcmt_rt_events());
});

// ── Settings page ──

function lcmt_rt_render(array $get): string
{
    lcmt_sp_admin();
    set_current_screen('mail_page_' . SubmissionSettings::PAGE_SLUG);

    return lcmt_sp_with_get($get, function () {
        ob_start();

        try {
            SubmissionSettings::renderPage();
        } finally {
            $html = ob_get_clean();
        }

        return $html;
    });
}

lcmt_it('Settings page shows the fields, the advanced details and the purge form', function () {
    lcmt_rt_options(365, 'delete', 90);
    update_option(Attribution::OPTION_WITHOUT_CONSENT, '0');

    $html = lcmt_rt_render([]);

    foreach ([SubmissionSettings::OPTION_DAYS, SubmissionSettings::OPTION_ACTION, SubmissionSettings::OPTION_STATS_DAYS, Attribution::OPTION_WITHOUT_CONSENT] as $name) {
        lcmt_assert_true(strpos($html, 'name="' . $name . '"') !== false, $name);
    }

    lcmt_assert_true(strpos($html, 'value="365"') !== false, 'days value');
    lcmt_assert_true(strpos($html, 'value="90"') !== false, 'stats value');
    lcmt_assert_true(preg_match('/value="delete"\s+checked/', $html) === 1, 'delete selected');
    lcmt_assert_true(strpos($html, '<details') !== false, 'advanced details');
    lcmt_assert_true(strpos($html, 'value="' . SubmissionSettings::PURGE_ACTION . '"') !== false, 'purge action');
    lcmt_assert_true(strpos($html, 'name="_wpnonce"') !== false, 'nonce field');
    lcmt_assert_true(strpos($html, 'admin-post.php') !== false, 'posts to admin-post');
    lcmt_assert_true(strpos($html, 'notice-success') === false, 'no notice by default');
    lcmt_assert_true(preg_match('/attribution_without_consent"[^>]*checked/', $html) !== 1, 'advanced box unticked');
});

lcmt_it('Settings page shows the purge counts', function () {
    $html = lcmt_rt_render(['anonymized' => '7', 'deleted' => '3']);

    lcmt_assert_true(strpos($html, 'Purge done: 7 messages anonymized, 3 deleted.') !== false, 'notice');
});
