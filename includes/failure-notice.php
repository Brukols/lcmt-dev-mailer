<?php

namespace LcmtDevMailer;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Warns administrators about form emails that could not be sent, with the
 * reason, until each one is resent, marked processed or spam, or dismissed.
 */
class FailureNotice
{
    public const OPTION_DISMISSED_AT = 'lcmt_mailer_failures_dismissed_at';
    public const DISMISS_ACTION = 'lcmt_mailer_dismiss_failures';

    public static function banner(): void
    {
        if (!current_user_can(SubmissionsPage::capability())) {
            return;
        }

        $since = self::since();
        $count = SubmissionRepository::countUnresolvedFailures($since);

        if (!$count) {
            return;
        }

        $latest = SubmissionRepository::unresolvedFailures($since, 1)[0] ?? [];

        echo '<div class="notice notice-error"><p>';
        echo '<strong>' . esc_html(sprintf(
            /* translators: %d: number of messages */
            _n('%d form message could not be emailed.', '%d form messages could not be emailed.', $count, 'lcmt-dev-mailer'),
            $count
        )) . '</strong> ';
        echo esc_html__('They are saved: you can read them and send them again.', 'lcmt-dev-mailer');
        echo '</p>';

        if (!empty($latest['mail_error'])) {
            echo '<p>' . esc_html__('Last error:', 'lcmt-dev-mailer') . ' <code>' . esc_html($latest['mail_error']) . '</code></p>';
        }

        echo '<p>';
        echo '<a class="button button-primary" href="' . esc_url(SubmissionsPage::url(['failed' => 1])) . '">' . esc_html__('See the messages', 'lcmt-dev-mailer') . '</a> ';
        echo '<a class="button" href="' . esc_url(self::dismissUrl()) . '">' . esc_html__('Dismiss', 'lcmt-dev-mailer') . '</a>';
        echo '</p></div>';
    }

    public static function addDashboardWidget(): void
    {
        if (!current_user_can(SubmissionsPage::capability()) || !SubmissionRepository::countUnresolvedFailures(self::since())) {
            return;
        }

        wp_add_dashboard_widget('lcmt_mailer_failures', __('Form emails that failed', 'lcmt-dev-mailer'), [self::class, 'renderWidget']);
    }

    public static function renderWidget(): void
    {
        $format = get_option('date_format') . ' ' . get_option('time_format');

        echo '<ul>';

        foreach (SubmissionRepository::unresolvedFailures(self::since(), 5) as $failure) {
            printf(
                '<li><a href="%s">%s</a> · <code>%s</code><br><span style="color: #d63638;">%s</span></li>',
                esc_url(SubmissionsPage::url(['submission' => $failure['id']])),
                esc_html(get_date_from_gmt((string) $failure['created_at'], $format)),
                esc_html($failure['form_key']),
                esc_html($failure['mail_error'] ?: __('The request stopped before the email was sent.', 'lcmt-dev-mailer'))
            );
        }

        echo '</ul>';
        echo '<p><a href="' . esc_url(SubmissionsPage::url(['failed' => 1])) . '">' . esc_html__('All failed emails', 'lcmt-dev-mailer') . '</a></p>';
    }

    /**
     * Hides the failures seen so far; a new failure brings the banner back.
     */
    public static function handleDismiss(): void
    {
        if (!current_user_can(SubmissionsPage::capability())) {
            wp_die(esc_html__('You are not allowed to do this.', 'lcmt-dev-mailer'), 403);
        }

        check_admin_referer(self::DISMISS_ACTION);

        self::dismiss();

        wp_safe_redirect(wp_get_referer() ?: admin_url());
        exit;
    }

    public static function dismiss(): void
    {
        update_option(self::OPTION_DISMISSED_AT, current_time('mysql', true), false);
    }

    private static function since(): string
    {
        return (string) get_option(self::OPTION_DISMISSED_AT, '1970-01-01 00:00:00');
    }

    private static function dismissUrl(): string
    {
        return wp_nonce_url(add_query_arg('action', self::DISMISS_ACTION, admin_url('admin-post.php')), self::DISMISS_ACTION);
    }
}
