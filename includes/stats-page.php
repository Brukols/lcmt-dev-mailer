<?php

namespace LcmtDevMailer;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Received messages → Statistics tab: where the received messages come from.
 * Counts include anonymized messages and leave spam out.
 */
class StatsPage
{
    /**
     * Period key => days (0 = everything).
     */
    /**
     * The one hue of every mark: WordPress admin blue, over 3:1 on white.
     */
    private const MARK_COLOR = '#2271b1';

    private const PERIODS = ['30' => 30, '90' => 90, '365' => 365, 'all' => 0];

    /**
     * The content of the Statistics tab (SubmissionsPage prints the heading and tabs).
     */
    public static function render(): void
    {
        $period = sanitize_key($_GET['period'] ?? '90');
        $period = isset(self::PERIODS[$period]) ? $period : '90';
        $since  = Retention::cutoff(self::PERIODS[$period], time()) ?? '1970-01-01 00:00:00';
        $totals = SubmissionRepository::totals($since);

        $labels = [
            '30'  => __('Last 30 days', 'lcmt-dev-mailer'),
            '90'  => __('Last 90 days', 'lcmt-dev-mailer'),
            '365' => __('Last 12 months', 'lcmt-dev-mailer'),
            'all' => __('Everything', 'lcmt-dev-mailer'),
        ];

        echo '<div class="lcmt-stats">';

        echo '<ul class="subsubsub">';
        $links = [];
        foreach ($labels as $key => $label) {
            $url     = SubmissionsPage::url(['tab' => SubmissionsPage::TAB_STATS, 'period' => $key]);
            $links[] = '<li><a href="' . esc_url($url) . '"' . ((string) $key === $period ? ' class="current" aria-current="page"' : '') . '>' . esc_html($label) . '</a>';
        }
        echo implode(' | </li>', $links) . '</li></ul><br class="clear">';

        echo '<div class="lcmt-stats__tiles">';
        self::tile(__('Messages', 'lcmt-dev-mailer'), number_format_i18n($totals['total']));
        self::tile(__('Emails not sent', 'lcmt-dev-mailer'), number_format_i18n($totals['failed']));
        self::tile(
            __('Average time on the form', 'lcmt-dev-mailer'),
            $totals['avg_seconds'] === null ? '–' : human_time_diff(0, $totals['avg_seconds'])
        );
        echo '</div>';

        echo '<div class="lcmt-stats__grid">';
        self::monthsBox(SubmissionRepository::countByMonth($since));
        self::barsBox('by-source', __('By source', 'lcmt-dev-mailer'), SubmissionRepository::countBy('channel', $since), [ChannelClassifier::class, 'label']);
        self::barsBox('by-campaign', __('By campaign', 'lcmt-dev-mailer'), SubmissionRepository::countBy('utm_campaign', $since));
        self::barsBox('by-form', __('By form', 'lcmt-dev-mailer'), SubmissionRepository::countBy('form_key', $since));
        self::barsBox('sent-from', __('Sent from', 'lcmt-dev-mailer'), SubmissionRepository::countBy('page_path', $since));
        self::barsBox('landing-page', __('Landing page', 'lcmt-dev-mailer'), SubmissionRepository::countBy('landing_path', $since));
        self::barsBox('referring-site', __('Referring site', 'lcmt-dev-mailer'), SubmissionRepository::countBy('referrer_host', $since));
        self::barsBox('device', __('Device', 'lcmt-dev-mailer'), SubmissionRepository::countBy('device', $since));
        self::barsBox('browser', __('Browser', 'lcmt-dev-mailer'), SubmissionRepository::countBy('browser', $since));
        self::barsBox('os', __('Operating system', 'lcmt-dev-mailer'), SubmissionRepository::countBy('os', $since));
        echo '</div>';

        ?>
        <style>
            .lcmt-stats__tiles { display: flex; gap: 16px; margin: 16px 0; flex-wrap: wrap; }
            .lcmt-stats__tile { background: #fff; border: 1px solid #c3c4c7; padding: 12px 16px; min-width: 180px; }
            .lcmt-stats__tile strong { display: block; font-size: 24px; line-height: 1.3; }
            .lcmt-stats__grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(420px, 1fr)); gap: 16px; }
            .lcmt-stats__box { margin: 0; min-width: 0; }
            .lcmt-stats__head { display: flex; align-items: center; justify-content: space-between; gap: 8px; margin-bottom: 8px; }
            .lcmt-stats__head h2 { padding: 0; }
            .lcmt-stats__toggle { display: inline-flex; flex: none; }
            .lcmt-stats__toggle[hidden] { display: none; }
            .lcmt-stats__toggle .button { border-radius: 0; margin: 0 0 0 -1px; }
            .lcmt-stats__toggle .button:first-child { border-radius: 3px 0 0 3px; margin: 0; }
            .lcmt-stats__toggle .button:last-child { border-radius: 0 3px 3px 0; }
            .lcmt-stats__toggle .button[aria-pressed="true"] { background: #2271b1; border-color: #2271b1; color: #fff; position: relative; }
            .lcmt-stats:not(.lcmt-stats--js) .lcmt-stats__panel[hidden] { display: block; }
            .lcmt-stats__months { display: block; width: 100%; height: auto; }
            .lcmt-stats__months text { font-family: inherit; font-size: 11px; fill: #50575e; }
            .lcmt-stats__months .lcmt-stats__max { fill: #1d2327; font-weight: 600; }
            .lcmt-stats__col:hover .lcmt-stats__col-mark { fill: #135e96; }
            .lcmt-stats__hbars { margin: 0; padding: 0; list-style: none; }
            .lcmt-stats__hbar { display: grid; grid-template-columns: minmax(80px, 30%) 1fr auto; align-items: center; gap: 8px; margin: 0; padding: 4px 0; }
            .lcmt-stats__hbar:hover { background: #f6f7f7; }
            .lcmt-stats__hbar-label { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
            .lcmt-stats__hbar-track { display: block; min-width: 0; }
            .lcmt-stats__hbar-fill { display: block; height: 12px; min-height: 8px; min-width: 2px; background: #2271b1; border-radius: 4px; }
            .lcmt-stats__hbar-value { min-width: 2.5em; text-align: right; font-variant-numeric: tabular-nums; }
            .lcmt-stats__bar { background: #2271b1; height: 8px; border-radius: 4px; min-width: 2px; }
            .lcmt-stats td.num { width: 60px; text-align: right; font-variant-numeric: tabular-nums; }
            .lcmt-stats td.bar { width: 40%; }
        </style>
        <?php

        echo '</div>';
    }

    private static function tile(string $label, string $value): void
    {
        echo '<div class="lcmt-stats__tile"><span>' . esc_html($label) . '</span><strong>' . esc_html($value) . '</strong></div>';
    }

    /**
     * Load the toggle script on the Statistics tab only.
     */
    public static function enqueue(string $hook): void
    {
        if (
            $hook !== PostType::SLUG . '_page_' . SubmissionsPage::PAGE_SLUG
            || SubmissionsPage::currentTab() !== SubmissionsPage::TAB_STATS
        ) {
            return;
        }

        wp_enqueue_script(
            'lcmt-admin-stats',
            LCMT_MAILER_URL . 'assets/dist/admin-stats.js',
            [],
            lcmt_mailer_asset_version('assets/dist/admin-stats.js'),
            true
        );
    }

    /**
     * The "By month" box: a column chart, time on x and count on y.
     *
     * @param list<array{label: string, total: int}> $rows Label as YYYY-MM, oldest first.
     */
    private static function monthsBox(array $rows): void
    {
        $title = __('By month', 'lcmt-dev-mailer');

        if (!$rows) {
            self::emptyBox('by-month', $title);
            return;
        }

        $table = self::table($rows, static fn(string $month) => date_i18n('F Y', strtotime($month . '-01')));
        // Time is continuous on the x axis: a month without message is an empty slot.
        $rows  = self::withEmptyMonths($rows);
        $mark  = self::MARK_COLOR;
        $width = 420;
        $top   = 22;
        $base  = 176;
        $left  = 30;
        $right = $width - 6;
        $plotH = $base - $top;
        $slot  = ($right - $left) / count($rows);
        $colW  = max(1.0, min($slot - 2, 40.0));
        $max   = max(array_column($rows, 'total')) ?: 1;
        $step  = self::tickStep($max);
        // A label needs about 46 units; skip months when the slots are narrower.
        $every = max(1, (int) ceil(46 / $slot));
        $f     = static fn(float $n): string => sprintf('%.1F', $n);

        $svg  = sprintf(
            '<svg class="lcmt-stats__months" viewBox="0 0 %d %d" width="100%%" role="img" aria-label="%s" focusable="false">',
            $width,
            $base + 24,
            esc_attr($title)
        );

        for ($tick = $step; $tick <= $max; $tick += $step) {
            $y    = $base - $tick / $max * $plotH;
            $svg .= sprintf(
                '<line class="lcmt-stats__grid-line" x1="%s" x2="%s" y1="%s" y2="%s" stroke="#dcdcde" stroke-width="1"/>',
                $f($left),
                $f($right),
                $f($y),
                $f($y)
            );
            $svg .= sprintf('<text class="lcmt-stats__tick" x="%s" y="%s" text-anchor="end">%s</text>', $f($left - 4), $f($y + 4), esc_html(number_format_i18n($tick)));
        }

        $svg .= sprintf('<line class="lcmt-stats__baseline" x1="%s" x2="%s" y1="%s" y2="%s" stroke="#c3c4c7" stroke-width="1"/>', $f($left), $f($right), $f($base), $f($base));
        $svg .= sprintf('<text class="lcmt-stats__tick" x="%s" y="%s" text-anchor="end">0</text>', $f($left - 4), $f($base + 4));

        $labelled = false;

        foreach ($rows as $i => $row) {
            $time  = strtotime($row['label'] . '-01');
            $slotX = $left + $i * $slot;
            $x     = $slotX + ($slot - $colW) / 2;
            $h     = $row['total'] > 0 ? max(2.0, $row['total'] / $max * $plotH) : 0.0;
            $y     = $base - $h;
            $r     = min(4.0, $colW / 2, $h);
            $text  = sprintf('%s — %s', date_i18n('F Y', $time), number_format_i18n($row['total']));

            $svg .= '<g class="lcmt-stats__col"><title>' . esc_html($text) . '</title>';
            $svg .= sprintf('<rect class="lcmt-stats__col-hit" x="%s" y="%s" width="%s" height="%s" fill="transparent"/>', $f($slotX), $f($top), $f($slot), $f($plotH));

            if ($h > 0) {
                $svg .= sprintf(
                    '<path class="lcmt-stats__col-mark" d="M%s %sV%sQ%s %s %s %sH%sQ%s %s %s %sV%sZ" fill="%s"/>',
                    $f($x), $f($base), $f($y + $r),
                    $f($x), $f($y), $f($x + $r), $f($y),
                    $f($x + $colW - $r),
                    $f($x + $colW), $f($y), $f($x + $colW), $f($y + $r),
                    $f($base),
                    $mark
                );
            }

            $svg .= '</g>';

            // Only the highest column is labelled with its value.
            if (!$labelled && $row['total'] === $max) {
                $labelled = true;
                $svg     .= sprintf('<text class="lcmt-stats__max" x="%s" y="%s" text-anchor="middle">%s</text>', $f($slotX + $slot / 2), $f($y - 4), esc_html(number_format_i18n($row['total'])));
            }

            if ($i % $every === 0) {
                $format = $i === 0 || substr($row['label'], 5, 2) === '01' ? 'M Y' : 'M';
                $svg   .= sprintf('<text class="lcmt-stats__month" x="%s" y="%s" text-anchor="middle">%s</text>', $f($slotX + $slot / 2), $f($base + 16), esc_html(date_i18n($format, $time)));
            }
        }

        $svg .= '</svg>';

        self::box('by-month', $title, $svg, $table);
    }

    /**
     * A box of horizontal bars, one per category, highest first.
     *
     * @param list<array{label: string, total: int}> $rows
     */
    private static function barsBox(string $id, string $title, array $rows, ?callable $label = null): void
    {
        if (!$rows) {
            self::emptyBox($id, $title);
            return;
        }

        $max  = max(array_column($rows, 'total')) ?: 1;
        $list = '<ul class="lcmt-stats__hbars">';

        foreach ($rows as $row) {
            $text = self::rowLabel($row['label'], $label);

            $list .= sprintf(
                '<li class="lcmt-stats__hbar" title="%s"><span class="lcmt-stats__hbar-label">%s</span><span class="lcmt-stats__hbar-track"><span class="lcmt-stats__hbar-fill" style="width: %.1F%%;"></span></span><span class="lcmt-stats__hbar-value">%s</span></li>',
                esc_attr($text . ' — ' . number_format_i18n($row['total'])),
                esc_html($text),
                $row['total'] / $max * 100,
                esc_html(number_format_i18n($row['total']))
            );
        }

        $list .= '</ul>';

        self::box($id, $title, $list, self::table($rows, $label));
    }

    /**
     * A box with its title, the Chart | Table toggle and both views. The
     * toggle stays hidden (and both views show) until the script runs.
     */
    private static function box(string $id, string $title, string $chart, string $table): void
    {
        echo '<div class="postbox lcmt-stats__box" data-lcmt-stats-box="' . esc_attr($id) . '"><div class="inside">';
        echo '<div class="lcmt-stats__head"><h2>' . esc_html($title) . '</h2>';
        echo '<div class="lcmt-stats__toggle" role="group" aria-label="' . esc_attr__('View', 'lcmt-dev-mailer') . '" data-lcmt-toggle hidden>';
        echo '<button type="button" class="button button-small" data-lcmt-set-view="chart" aria-pressed="true">' . esc_html__('Chart', 'lcmt-dev-mailer') . '</button>';
        echo '<button type="button" class="button button-small" data-lcmt-set-view="table" aria-pressed="false">' . esc_html__('Table', 'lcmt-dev-mailer') . '</button>';
        echo '</div></div>';
        echo '<div class="lcmt-stats__panel" data-lcmt-view="chart">' . $chart . '</div>';
        echo '<div class="lcmt-stats__panel" data-lcmt-view="table" hidden>' . $table . '</div>';
        echo '</div></div>';
    }

    private static function emptyBox(string $id, string $title): void
    {
        echo '<div class="postbox lcmt-stats__box" data-lcmt-stats-box="' . esc_attr($id) . '"><div class="inside">';
        echo '<h2 style="padding: 0;">' . esc_html($title) . '</h2>';
        echo '<p>' . esc_html__('No data for this period.', 'lcmt-dev-mailer') . '</p></div></div>';
    }

    /**
     * @param list<array{label: string, total: int}> $rows
     */
    private static function table(array $rows, ?callable $label): string
    {
        $max  = max(array_column($rows, 'total')) ?: 1;
        $html = '<table class="widefat striped"><tbody>';

        foreach ($rows as $row) {
            $html .= sprintf(
                '<tr><td>%s</td><td class="bar"><div class="lcmt-stats__bar" style="width: %.1f%%;"></div></td><td class="num">%s</td></tr>',
                esc_html(self::rowLabel($row['label'], $label)),
                $row['total'] / $max * 100,
                esc_html(number_format_i18n($row['total']))
            );
        }

        return $html . '</tbody></table>';
    }

    private static function rowLabel(string $raw, ?callable $label): string
    {
        return $raw === '' ? __('(not set)', 'lcmt-dev-mailer') : (string) ($label ? $label($raw) : $raw);
    }

    /**
     * The months from the first to the last one, 0 for those without a message.
     *
     * @param list<array{label: string, total: int}> $rows Label as YYYY-MM, oldest first.
     * @return list<array{label: string, total: int}>
     */
    private static function withEmptyMonths(array $rows): array
    {
        $totals = array_column($rows, 'total', 'label');
        $series = [];
        $month  = new \DateTimeImmutable($rows[0]['label'] . '-01', new \DateTimeZone('UTC'));
        $last   = $rows[count($rows) - 1]['label'];

        for ($label = $month->format('Y-m'); $label <= $last; $label = ($month = $month->modify('+1 month'))->format('Y-m')) {
            $series[] = ['label' => $label, 'total' => $totals[$label] ?? 0];
        }

        return $series;
    }

    /**
     * A round step (1, 2, 5, 10, 20, 50...) giving at most four gridlines.
     */
    private static function tickStep(int $max): int
    {
        $raw       = max(1, $max) / 4;
        $magnitude = 10 ** (int) floor(log10($raw));

        foreach ([1, 2, 5, 10] as $multiple) {
            if ($raw <= $multiple * $magnitude) {
                return max(1, (int) ($multiple * $magnitude));
            }
        }

        return max(1, (int) (10 * $magnitude));
    }
}
