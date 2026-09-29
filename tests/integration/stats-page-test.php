<?php

use LcmtDevMailer\StatsPage;
use LcmtDevMailer\SubmissionRepository;
use LcmtDevMailer\SubmissionsPage;

require_once __DIR__ . '/submissions-page-helpers.php';

/**
 * render() aggregates the WHOLE table, so these tests first empty it. That is
 * safe: lcmt_it() runs every test in a transaction that is always rolled back,
 * so the DELETE never survives (DELETE is DML, it does not commit implicitly).
 */
function lcmt_stats_empty_table(): void
{
    global $wpdb;

    $wpdb->query('DELETE FROM ' . SubmissionRepository::table());
}

function lcmt_stats_days_ago(int $days): string
{
    return gmdate('Y-m-d H:i:s', time() - $days * 86400);
}

function lcmt_stats_render(array $get = []): string
{
    lcmt_sp_admin();
    set_current_screen('mail_page_' . SubmissionsPage::PAGE_SLUG);
    $_SERVER['HTTP_HOST'] ??= (string) wp_parse_url(home_url(), PHP_URL_HOST);

    return lcmt_sp_with_get($get, function () {
        ob_start();

        try {
            StatsPage::render();
        } finally {
            $html = ob_get_clean();
        }

        return $html;
    });
}

/**
 * Rows: two Google Ads (one with a script-tag campaign, one failed), one Direct,
 * five spam rows, and one Direct row 60 days old.
 */
function lcmt_stats_seed(): void
{
    lcmt_stats_empty_table();

    lcmt_it_insert(['channel' => 'google_ads', 'utm_campaign' => '<script>alert(1)</script>', 'created_at' => lcmt_stats_days_ago(5), 'form_seconds' => 10]);
    lcmt_it_insert(['channel' => 'google_ads', 'utm_campaign' => '', 'created_at' => lcmt_stats_days_ago(5), 'form_seconds' => 20, 'mail_sent' => 0]);
    lcmt_it_insert(['channel' => 'direct', 'utm_campaign' => '', 'created_at' => lcmt_stats_days_ago(6)]);
    lcmt_it_insert(['channel' => 'direct', 'utm_campaign' => '', 'created_at' => lcmt_stats_days_ago(60)]);

    for ($i = 0; $i < 5; $i++) {
        lcmt_it_insert(['channel' => 'google_ads', 'status' => 'spam', 'created_at' => lcmt_stats_days_ago(2)]);
    }
}

lcmt_it('StatsPage render defaults to 90 days and falls back to it for an unknown period', function () {
    lcmt_stats_seed();

    foreach ([[], ['period' => 'bogus'], ['period' => '90']] as $get) {
        $html = lcmt_stats_render($get);

        lcmt_assert_same(1, substr_count($html, 'class="current"'), 'one current link');
        lcmt_assert_same(1, preg_match('/<a [^>]*period=90[^>]*class="current"/', $html), '90 is current');
    }
});

lcmt_it('StatsPage period links stay on the Statistics tab', function () {
    lcmt_stats_seed();

    $html = lcmt_stats_render(['tab' => 'stats', 'period' => '30']);

    preg_match_all('/<li><a href="([^"]+)"/', $html, $m);
    lcmt_assert_count(4, $m[1], 'four period links');

    foreach ($m[1] as $href) {
        parse_str((string) wp_parse_url(html_entity_decode($href), PHP_URL_QUERY), $query);
        lcmt_assert_same('stats', $query['tab'] ?? null, $href);
        lcmt_assert_same(SubmissionsPage::PAGE_SLUG, $query['page'] ?? null, $href);
    }
});

lcmt_it('StatsPage render narrows by period and supports all', function () {
    lcmt_stats_seed();

    $tile = static fn(string $html, string $label): string => preg_match(
        '/<span>' . preg_quote($label, '/') . '<\/span><strong>([^<]*)<\/strong>/',
        $html,
        $m
    ) ? $m[1] : '';

    $html30 = lcmt_stats_render(['period' => '30']);
    $html90 = lcmt_stats_render(['period' => '90']);
    $html   = lcmt_stats_render(['period' => 'all']);

    lcmt_assert_same('3', $tile($html30, 'Messages'), '30 days');
    lcmt_assert_same('4', $tile($html90, 'Messages'), '90 days (60 day old row)');
    lcmt_assert_same('4', $tile($html, 'Messages'), 'all');
    lcmt_assert_same(1, preg_match('/<a [^>]*period=all[^>]*class="current"/', $html), 'all is current');

    lcmt_assert_same('1', $tile($html30, 'Emails not sent'));
    lcmt_assert_true(str_contains($html30, 'Average time on the form'));
});

lcmt_it('StatsPage render shows tiles, labels, escaped values and proportional bars', function () {
    lcmt_stats_seed();

    $html = lcmt_stats_render(['period' => '30']);

    lcmt_assert_same(3, substr_count($html, 'class="lcmt-stats__tile"'), 'three tiles');
    lcmt_assert_same(8, substr_count($html, 'class="postbox"'), 'eight boxes');

    // Spam rows (five Google Ads) are not counted: Google Ads 2, Direct 1.
    lcmt_assert_same(1, preg_match('/<td>Google Ads<\/td><td class="bar"><div class="lcmt-stats__bar" style="width: 100\.0%;"><\/div><\/td><td class="num">2<\/td>/', $html), 'Google Ads 100%');
    lcmt_assert_same(1, preg_match('/<td>Direct<\/td><td class="bar"><div class="lcmt-stats__bar" style="width: 50\.0%;"><\/div><\/td><td class="num">1<\/td>/', $html), 'Direct 50%');

    // Campaign: '' twice (top, "(not set)"), the script campaign once (50%).
    lcmt_assert_same(1, preg_match('/<td>\(not set\)<\/td><td class="bar"><div class="lcmt-stats__bar" style="width: 100\.0%;"><\/div><\/td><td class="num">2<\/td>/', $html), '(not set) top row');
    lcmt_assert_true(str_contains($html, '&lt;script&gt;alert(1)&lt;/script&gt;'), 'escaped campaign');
    lcmt_assert_same(false, str_contains($html, '<script>alert(1)</script>'), 'no raw script');
});

lcmt_it('StatsPage render says so when a box has no data', function () {
    lcmt_stats_empty_table();

    $html = lcmt_stats_render();

    lcmt_assert_same(8, substr_count($html, 'No data for this period.'));
});
