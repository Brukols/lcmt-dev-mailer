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
        self::bars(__('By month', 'lcmt-dev-mailer'), SubmissionRepository::countByMonth($since), static fn(string $month) => date_i18n('F Y', strtotime($month . '-01')));
        self::bars(__('By source', 'lcmt-dev-mailer'), SubmissionRepository::countBy('channel', $since), [ChannelClassifier::class, 'label']);
        self::bars(__('By campaign', 'lcmt-dev-mailer'), SubmissionRepository::countBy('utm_campaign', $since));
        self::bars(__('By form', 'lcmt-dev-mailer'), SubmissionRepository::countBy('form_key', $since));
        self::bars(__('Sent from', 'lcmt-dev-mailer'), SubmissionRepository::countBy('page_path', $since));
        self::bars(__('Landing page', 'lcmt-dev-mailer'), SubmissionRepository::countBy('landing_path', $since));
        self::bars(__('Referring site', 'lcmt-dev-mailer'), SubmissionRepository::countBy('referrer_host', $since));
        self::bars(__('Device', 'lcmt-dev-mailer'), SubmissionRepository::countBy('device', $since));
        echo '</div>';

        ?>
        <style>
            .lcmt-stats__tiles { display: flex; gap: 16px; margin: 16px 0; flex-wrap: wrap; }
            .lcmt-stats__tile { background: #fff; border: 1px solid #c3c4c7; padding: 12px 16px; min-width: 180px; }
            .lcmt-stats__tile strong { display: block; font-size: 24px; line-height: 1.3; }
            .lcmt-stats__grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(420px, 1fr)); gap: 16px; }
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
     * @param list<array{label: string, total: int}> $rows
     */
    private static function bars(string $title, array $rows, ?callable $label = null): void
    {
        echo '<div class="postbox"><div class="inside">';
        echo '<h2 style="padding: 0;">' . esc_html($title) . '</h2>';

        if (!$rows) {
            echo '<p>' . esc_html__('No data for this period.', 'lcmt-dev-mailer') . '</p></div></div>';
            return;
        }

        $max = max(array_column($rows, 'total')) ?: 1;

        echo '<table class="widefat striped"><tbody>';

        foreach ($rows as $row) {
            $text = $row['label'] === '' ? __('(not set)', 'lcmt-dev-mailer') : ($label ? $label($row['label']) : $row['label']);

            printf(
                '<tr><td>%s</td><td class="bar"><div class="lcmt-stats__bar" style="width: %.1f%%;"></div></td><td class="num">%s</td></tr>',
                esc_html((string) $text),
                $row['total'] / $max * 100,
                esc_html(number_format_i18n($row['total']))
            );
        }

        echo '</tbody></table></div></div>';
    }
}
