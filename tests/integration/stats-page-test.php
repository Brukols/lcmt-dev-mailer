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
    lcmt_assert_same(10, substr_count($html, 'class="postbox lcmt-stats__box"'), 'ten boxes');

    // Spam rows (five Google Ads) are not counted: Google Ads 2, Direct 1.
    lcmt_assert_true(str_contains($html, '<tr><td>Google Ads</td><td class="num">2</td></tr>'), 'Google Ads row');
    lcmt_assert_true(str_contains($html, '<tr><td>Direct</td><td class="num">1</td></tr>'), 'Direct row');
    lcmt_assert_same(false, str_contains($html, 'lcmt-stats__bar"'), 'no bar cell in the table');
    lcmt_assert_same(10, substr_count($html, '<thead><tr><th>'), 'a header row per table');
    lcmt_assert_true(str_contains($html, '<thead><tr><th>By source</th><th class="num">Messages</th></tr></thead>'), 'label and count headers');

    // Campaign: '' twice (top, "(not set)"), the script campaign once (50%).
    lcmt_assert_true(str_contains($html, '<tr><td>(not set)</td><td class="num">2</td></tr>'), '(not set) top row');
    lcmt_assert_true(str_contains($html, '&lt;script&gt;alert(1)&lt;/script&gt;'), 'escaped campaign');
    lcmt_assert_same(false, str_contains($html, '<script>alert(1)</script>'), 'no raw script');
});

lcmt_it('StatsPage render says so when a box has no data', function () {
    lcmt_stats_empty_table();

    $html = lcmt_stats_render();

    lcmt_assert_same(10, substr_count($html, 'No data for this period.'));
    lcmt_assert_same(0, substr_count($html, '<svg'), 'no empty chart');
    lcmt_assert_same(0, substr_count($html, 'data-lcmt-toggle'), 'no toggle without data');
});


/**
 * The column chart of the "By month" box: its <svg>.
 */
function lcmt_stats_month_svg(string $html): string
{
    return preg_match('#<svg[^>]*lcmt-stats__months.*?</svg>#s', $html, $m) ? $m[0] : '';
}

/**
 * Height in viewBox units of each column of the chart, in order.
 *
 * @return list<float>
 */
function lcmt_stats_column_heights(string $svg): array
{
    preg_match_all('#<path class="lcmt-stats__col-mark" d="M[\d.]+ ([\d.]+)V[\d.]+Q[\d.]+ ([\d.]+) #', $svg, $m, PREG_SET_ORDER);

    return array_map(static fn(array $c) => round((float) $c[1] - (float) $c[2], 1), $m);
}

function lcmt_stats_seed_months(): void
{
    lcmt_stats_empty_table();

    foreach ([['2026-01-15 10:00:00', 3], ['2026-02-10 10:00:00', 1], ['2026-03-05 10:00:00', 2]] as [$when, $n]) {
        for ($i = 0; $i < $n; $i++) {
            lcmt_it_insert(['created_at' => $when]);
        }
    }

    lcmt_it_insert(['created_at' => '2026-02-11 10:00:00', 'status' => 'spam']);
}

lcmt_it('StatsPage draws the months as a column chart with one column per month', function () {
    lcmt_stats_seed_months();

    $svg = lcmt_stats_month_svg(lcmt_stats_render(['period' => 'all']));

    lcmt_assert_true($svg !== '', 'svg');
    lcmt_assert_same(1, preg_match('/viewBox="0 0 \d+ \d+"/', $svg), 'viewBox');
    lcmt_assert_same(1, preg_match('/<svg[^>]* width="100%"/', $svg), 'fits the box');
    lcmt_assert_same(1, preg_match('/<svg[^>]* role="img"[^>]* aria-label="By month: highest 3 in January 2026"/', $svg), 'accessible name summarizes the chart');
    lcmt_assert_same(3, substr_count($svg, 'class="lcmt-stats__col"'), 'one column per month, spam left out');
    lcmt_assert_same(3, substr_count($svg, 'fill="#2271b1"'), 'one hue');
});

lcmt_it('StatsPage scales the columns to the tallest one, from a baseline at zero', function () {
    lcmt_stats_seed_months();

    $svg     = lcmt_stats_month_svg(lcmt_stats_render(['period' => 'all']));
    $heights = lcmt_stats_column_heights($svg);

    lcmt_assert_count(3, $heights);
    lcmt_assert_same(round($heights[0] / 3, 1), $heights[1], 'one is a third of three');
    lcmt_assert_same(round($heights[0] * 2 / 3, 1), $heights[2], 'two is two thirds of three');

    // The tallest column reaches the top of the plot: the top gridline sits at its height.
    preg_match_all('#<line class="lcmt-stats__grid-line" x1="[\d.]+" x2="[\d.]+" y1="([\d.]+)"#', $svg, $lines);
    preg_match('#<line class="lcmt-stats__baseline"[^>]* y1="([\d.]+)"#', $svg, $base);
    lcmt_assert_true(isset($base[1]), 'baseline');
    $tops = array_map(static fn($y) => round((float) $base[1] - (float) $y, 1), $lines[1]);
    lcmt_assert_same($heights[0], max($tops), 'the y ticks stop at the tallest value (3)');
});

lcmt_it('StatsPage labels the maximum only, and round ticks on the y axis', function () {
    lcmt_stats_seed_months();

    $svg = lcmt_stats_month_svg(lcmt_stats_render(['period' => 'all']));

    lcmt_assert_same(1, substr_count($svg, 'class="lcmt-stats__max"'), 'one direct label');
    lcmt_assert_same(1, preg_match('#<text class="lcmt-stats__max"[^>]*>3</text>#', $svg), 'its value');

    foreach (['0', '1', '2', '3'] as $tick) {
        lcmt_assert_same(1, preg_match('#<text class="lcmt-stats__tick"[^>]*>' . $tick . '</text>#', $svg), 'tick ' . $tick);
    }
});

lcmt_it('StatsPage abbreviates the month labels in the site locale', function () {
    lcmt_stats_seed_months();

    $svg = lcmt_stats_month_svg(lcmt_stats_render(['period' => 'all']));

    lcmt_assert_true(str_contains($svg, '>Jan 2026</text>'), 'first label carries the year');
    lcmt_assert_true(str_contains($svg, '>Feb</text>'), 'abbreviated month');
    lcmt_assert_true(str_contains($svg, '>Mar</text>'), 'abbreviated month');
    lcmt_assert_same(1, substr_count($svg, '2026</text>'), 'the year shows once');
});

lcmt_it('StatsPage gives every month column a full-slot tooltip', function () {
    lcmt_stats_seed_months();

    $svg = lcmt_stats_month_svg(lcmt_stats_render(['period' => 'all']));

    lcmt_assert_true(str_contains($svg, '<title>January 2026 — 3</title>'), 'January');
    lcmt_assert_true(str_contains($svg, '<title>February 2026 — 1</title>'), 'February');
    lcmt_assert_true(str_contains($svg, '<title>March 2026 — 2</title>'), 'March');
    lcmt_assert_same(3, substr_count($svg, 'class="lcmt-stats__col-hit"'), 'one hit target per column');
});

lcmt_it('StatsPage leaves an empty slot for a month without message', function () {
    lcmt_stats_empty_table();
    lcmt_it_insert(['created_at' => '2025-11-15 10:00:00']);
    lcmt_it_insert(['created_at' => '2026-02-10 10:00:00']);
    lcmt_it_insert(['created_at' => '2026-02-12 10:00:00']);

    $html = lcmt_stats_render(['period' => 'all']);
    $svg  = lcmt_stats_month_svg($html);

    lcmt_assert_same(4, substr_count($svg, 'class="lcmt-stats__col"'), 'Nov, Dec, Jan, Feb');
    lcmt_assert_same(2, count(lcmt_stats_column_heights($svg)), 'only two months are drawn');
    lcmt_assert_true(str_contains($svg, '<title>December 2025 — 0</title>'), 'tooltip of an empty month');
    lcmt_assert_same(2, substr_count($html, '<td>November 2025</td>') + substr_count($html, '<td>February 2026</td>'), 'the table lists months with data only');
    lcmt_assert_same(false, str_contains($html, '<td>December 2025</td>'), 'no zero row in the table');
});

lcmt_it('StatsPage shows the year on the first label of each new year', function () {
    lcmt_stats_empty_table();
    lcmt_it_insert(['created_at' => '2025-11-15 10:00:00']);
    lcmt_it_insert(['created_at' => '2026-02-10 10:00:00']);

    $svg = lcmt_stats_month_svg(lcmt_stats_render(['period' => 'all']));

    preg_match_all('#<text class="lcmt-stats__month"[^>]*>([^<]*)</text>#', $svg, $m);
    lcmt_assert_same(['Nov 2025', 'Dec', 'Jan 2026', 'Feb'], $m[1]);
});

lcmt_it('StatsPage puts the year on the first drawn label of a year even when labels are skipped', function () {
    lcmt_stats_empty_table();
    lcmt_it_insert(['created_at' => '2024-05-15 10:00:00']);
    lcmt_it_insert(['created_at' => '2026-05-10 10:00:00']);

    $svg = lcmt_stats_month_svg(lcmt_stats_render(['period' => 'all']));

    preg_match_all('#<text class="lcmt-stats__month"[^>]*>([^<]*)</text>#', $svg, $m);
    lcmt_assert_same(1, count(array_filter($m[1], static fn($l) => str_contains($l, '2024'))), 'first label has 2024');
    lcmt_assert_same(1, count(array_filter($m[1], static fn($l) => str_contains($l, '2025'))), 'one label has 2025');
    lcmt_assert_same(1, count(array_filter($m[1], static fn($l) => str_contains($l, '2026'))), 'one label has 2026');
});

lcmt_it('StatsPage keeps a single month readable', function () {
    lcmt_stats_empty_table();
    lcmt_it_insert(['created_at' => '2026-05-05 10:00:00']);

    $svg = lcmt_stats_month_svg(lcmt_stats_render(['period' => 'all']));

    lcmt_assert_same(1, substr_count($svg, 'class="lcmt-stats__col"'));
    lcmt_assert_same(1, preg_match('#<text class="lcmt-stats__max"[^>]*>1</text>#', $svg), 'max label');
});

lcmt_it('StatsPage draws the other boxes as horizontal bars with tooltips and an escaped label', function () {
    lcmt_stats_seed();

    $html = lcmt_stats_render(['period' => '30']);

    lcmt_assert_same(1, preg_match('#<li class="lcmt-stats__hbar" title="Google Ads — 2">.*?<span class="lcmt-stats__hbar-fill" style="width: 100\.0%;"></span>.*?<span class="lcmt-stats__hbar-value">2</span></li>#s', $html), 'Google Ads bar');
    lcmt_assert_same(1, preg_match('#<li class="lcmt-stats__hbar" title="Direct — 1">.*?style="width: 50\.0%;"#s', $html), 'Direct bar, half of the top one');
    lcmt_assert_true(str_contains($html, 'title="&lt;script&gt;alert(1)&lt;/script&gt; — 1"'), 'escaped tooltip');
    lcmt_assert_true(str_contains($html, '<span class="lcmt-stats__hbar-label">(not set)</span>'), '(not set)');
    lcmt_assert_same(false, str_contains($html, '<script>alert(1)</script>'), 'no raw script');
});

lcmt_it('StatsPage gives each box a Chart | Table toggle, chart first, with the table still there', function () {
    lcmt_stats_seed();

    $html = lcmt_stats_render(['period' => '30']);

    lcmt_assert_same(10 - 0, substr_count($html, 'data-lcmt-stats-box="'), 'ten boxes');
    lcmt_assert_same(10, substr_count($html, 'data-lcmt-toggle'), 'ten toggles');
    lcmt_assert_same(10, substr_count($html, '<button type="button" class="button button-small" data-lcmt-set-view="chart" aria-pressed="true">Chart</button>'), 'chart pressed');
    lcmt_assert_same(10, substr_count($html, '<button type="button" class="button button-small" data-lcmt-set-view="table" aria-pressed="false">Table</button>'), 'table not pressed');
    lcmt_assert_same(10, substr_count($html, '<div class="lcmt-stats__panel" data-lcmt-view="chart">'), 'chart panels shown');
    lcmt_assert_same(10, substr_count($html, '<div class="lcmt-stats__panel" data-lcmt-view="table" hidden>'), 'table panels hidden');
    lcmt_assert_same(10, substr_count($html, '<table class="widefat striped">'), 'tables kept');
    lcmt_assert_same(10, preg_match_all('#<div class="lcmt-stats__toggle" role="group" aria-label="[^"]+" data-lcmt-toggle hidden>#', $html), 'toggle group named and hidden until scripted');
});

lcmt_it('StatsPage adds the browser and operating system boxes', function () {
    lcmt_stats_empty_table();
    lcmt_it_insert(['browser' => 'Chrome', 'os' => 'Windows', 'created_at' => lcmt_stats_days_ago(1)]);
    lcmt_it_insert(['browser' => 'Chrome', 'os' => 'macOS', 'created_at' => lcmt_stats_days_ago(1)]);

    $html = lcmt_stats_render(['period' => '30']);

    lcmt_assert_true(str_contains($html, '>Browser</h2>'), 'Browser box');
    lcmt_assert_true(str_contains($html, '>Operating system</h2>'), 'OS box');
    lcmt_assert_true(str_contains($html, 'title="Chrome — 2"'), 'browser count');
    lcmt_assert_true(str_contains($html, 'title="macOS — 1"'), 'os count');
});

lcmt_it('StatsPage loads its script on the Statistics tab only', function () {
    lcmt_sp_admin();
    $hook = 'mail_page_' . SubmissionsPage::PAGE_SLUG;
    wp_dequeue_script('lcmt-admin-stats');
    wp_deregister_script('lcmt-admin-stats');

    foreach ([['tab' => 'messages'], [], ['tab' => 'retention']] as $get) {
        lcmt_sp_with_get($get, static fn() => StatsPage::enqueue($hook));
        lcmt_assert_same(false, wp_script_is('lcmt-admin-stats', 'enqueued'), 'tab ' . ($get['tab'] ?? 'default'));
    }

    lcmt_sp_with_get(['tab' => 'stats'], static fn() => StatsPage::enqueue('index.php'));
    lcmt_assert_same(false, wp_script_is('lcmt-admin-stats', 'enqueued'), 'other screen');

    lcmt_sp_with_get(['tab' => 'stats'], static fn() => StatsPage::enqueue($hook));
    lcmt_assert_true(wp_script_is('lcmt-admin-stats', 'enqueued'), 'stats tab');
    lcmt_assert_true(str_ends_with((string) wp_scripts()->registered['lcmt-admin-stats']->src, 'assets/dist/admin-stats.js'), 'built file');

    wp_dequeue_script('lcmt-admin-stats');
    wp_deregister_script('lcmt-admin-stats');
});

lcmt_it('StatsPage registers its script on admin_enqueue_scripts', function () {
    lcmt_assert_same(10, has_action('admin_enqueue_scripts', ['LcmtDevMailer\\StatsPage', 'enqueue']));
});
