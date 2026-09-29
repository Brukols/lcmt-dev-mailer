<?php

namespace LcmtDevMailer;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Email templates → Received messages: the list, one message, and its actions.
 */
class SubmissionsPage
{
    public const PAGE_SLUG = 'lcmt-mailer-submissions';
    public const ACTION = 'lcmt_mailer_submission';

    public static function capability(): string
    {
        return (string) apply_filters('lcmt_mailer_submissions_capability', 'manage_options');
    }

    public static function url(array $args = []): string
    {
        return add_query_arg(
            array_merge(['post_type' => PostType::SLUG, 'page' => self::PAGE_SLUG], $args),
            admin_url('edit.php')
        );
    }

    public static function addSubmenu(): void
    {
        $unread = SubmissionRepository::countUnread();
        $title  = __('Received messages', 'lcmt-dev-mailer');
        $menu   = $unread
            ? $title . ' <span class="awaiting-mod">' . number_format_i18n($unread) . '</span>'
            : $title;

        $hook = add_submenu_page(
            'edit.php?post_type=' . PostType::SLUG,
            $title,
            $menu,
            self::capability(),
            self::PAGE_SLUG,
            [self::class, 'render']
        );

        if ($hook) {
            add_action('load-' . $hook, [self::class, 'handleLoad']);
        }
    }

    /**
     * @return array{form_key: string, status: string, failed: bool, channel: string, search: string}
     */
    public static function filtersFromRequest(): array
    {
        $status  = sanitize_key($_GET['status'] ?? '');
        $channel = sanitize_key($_GET['channel'] ?? '');

        return [
            'form_key' => sanitize_title(wp_unslash($_GET['form_key'] ?? '')),
            'status'   => in_array($status, SubmissionRepository::STATUSES, true) ? $status : '',
            'failed'   => !empty($_GET['failed']),
            'channel'  => in_array($channel, ChannelClassifier::CHANNELS, true) ? $channel : '',
            'search'   => sanitize_text_field(wp_unslash($_GET['s'] ?? '')),
        ];
    }

    /**
     * Before any output: mark an opened message as read, run bulk actions.
     */
    public static function handleLoad(): void
    {
        $id = absint($_GET['submission'] ?? 0);

        if ($id) {
            $row = SubmissionRepository::find($id);

            if ($row && $row['status'] === 'new') {
                SubmissionRepository::setStatus([$id], 'read');
            }

            return;
        }

        $action = sanitize_key($_GET['action'] ?? '-1');

        if ($action === '-1') {
            $action = sanitize_key($_GET['action2'] ?? '-1');
        }

        $ids = array_map('absint', (array) ($_GET['ids'] ?? []));

        if ($action === '-1' || !$ids) {
            return;
        }

        check_admin_referer('bulk-submissions');

        $notice = self::apply($action, $ids);

        wp_safe_redirect(self::url(array_filter(self::filtersFromRequest()) + ['notice' => $notice]));
        exit;
    }

    /**
     * admin-post.php handler for the buttons of one message.
     */
    public static function handleSingle(): void
    {
        $id = absint($_REQUEST['id'] ?? 0);
        $do = sanitize_key($_REQUEST['do'] ?? '');

        if (!current_user_can(self::capability())) {
            wp_die(esc_html__('You are not allowed to do this.', 'lcmt-dev-mailer'), 403);
        }

        check_admin_referer('lcmt_submission_' . $id);

        $notice = self::apply($do, [$id]);

        // Back to the list after "unread" too: reopening the message would mark it read again.
        $args = in_array($do, ['delete', 'new'], true) ? ['notice' => $notice] : ['submission' => $id, 'notice' => $notice];

        wp_safe_redirect(self::url($args));
        exit;
    }

    /**
     * @param list<int> $ids
     * @return string A notice code for render().
     */
    public static function apply(string $do, array $ids): string
    {
        if (in_array($do, SubmissionRepository::STATUSES, true)) {
            SubmissionRepository::setStatus($ids, $do);
            return 'updated';
        }

        if ($do === 'delete') {
            SubmissionRepository::delete($ids);
            return 'deleted';
        }

        return '';
    }

    public static function singleActionUrl(int $id, string $do): string
    {
        return wp_nonce_url(
            add_query_arg(['action' => self::ACTION, 'id' => $id, 'do' => $do], admin_url('admin-post.php')),
            'lcmt_submission_' . $id
        );
    }

    public static function render(): void
    {
        echo '<div class="wrap">';

        self::renderNotice();

        $id = absint($_GET['submission'] ?? 0);

        if ($id) {
            self::renderDetail($id);
        } else {
            self::renderList();
        }

        echo '</div>';
    }

    private static function renderNotice(): void
    {
        $messages = [
            'updated'       => [__('Messages updated.', 'lcmt-dev-mailer'), 'success'],
            'deleted'       => [__('Messages deleted.', 'lcmt-dev-mailer'), 'success'],
            'resent'        => [__('Email sent.', 'lcmt-dev-mailer'), 'success'],
            'resend_failed' => [__('The email could not be sent again. The reason is shown below.', 'lcmt-dev-mailer'), 'error'],
        ];

        $code = sanitize_key($_GET['notice'] ?? '');

        if (isset($messages[$code])) {
            printf(
                '<div class="notice notice-%s is-dismissible"><p>%s</p></div>',
                esc_attr($messages[$code][1]),
                esc_html($messages[$code][0])
            );
        }
    }

    private static function renderList(): void
    {
        require_once LCMT_MAILER_PATH . 'includes/submissions-list-table.php';

        $table = new SubmissionsListTable();
        $table->prepare_items();

        echo '<h1 class="wp-heading-inline">' . esc_html__('Received messages', 'lcmt-dev-mailer') . '</h1>';
        echo '<hr class="wp-header-end">';

        $table->views();

        echo '<form method="get">';
        echo '<input type="hidden" name="post_type" value="' . esc_attr(PostType::SLUG) . '" />';
        echo '<input type="hidden" name="page" value="' . esc_attr(self::PAGE_SLUG) . '" />';

        foreach (['status', 'failed'] as $kept) {
            if (!empty($_GET[$kept])) {
                echo '<input type="hidden" name="' . esc_attr($kept) . '" value="' . esc_attr(sanitize_key($_GET[$kept])) . '" />';
            }
        }

        $table->search_box(__('Search messages', 'lcmt-dev-mailer'), 'lcmt-submissions');
        $table->display();
        echo '</form>';
    }

    private static function renderDetail(int $id): void
    {
        $row = SubmissionRepository::find($id);

        echo '<h1>' . esc_html__('Received message', 'lcmt-dev-mailer') . '</h1>';
        echo '<p><a href="' . esc_url(self::url()) . '">&larr; ' . esc_html__('Back to the messages', 'lcmt-dev-mailer') . '</a></p>';

        if (!$row) {
            echo '<p>' . esc_html__('This message no longer exists.', 'lcmt-dev-mailer') . '</p>';
            return;
        }

        $format = get_option('date_format') . ' ' . get_option('time_format');

        // ── What the visitor typed ──
        echo '<h2>' . esc_html__('Message', 'lcmt-dev-mailer') . '</h2>';

        if ($row['fields'] === null) {
            printf(
                '<p><em>%s</em></p>',
                esc_html(sprintf(
                    /* translators: %s: date of the anonymization */
                    __('Personal data anonymized on %s.', 'lcmt-dev-mailer'),
                    get_date_from_gmt((string) $row['anonymized_at'], $format)
                ))
            );
        } else {
            echo '<table class="widefat striped" style="max-width: 900px;"><tbody>';

            foreach ($row['fields'] as $field) {
                echo '<tr>';
                echo '<th style="width: 200px;"><code>' . esc_html($field['name']) . '</code></th>';
                echo '<td>' . nl2br(esc_html($field['value'])) . '</td>';
                echo '</tr>';
            }

            echo '</tbody></table>';
        }

        // ── Email ──
        echo '<h2>' . esc_html__('Email', 'lcmt-dev-mailer') . '</h2>';

        if ((int) $row['mail_sent'] === 1) {
            echo '<p style="color: #008a20;">&#10003; ' . esc_html__('Sent', 'lcmt-dev-mailer') . '</p>';
        } else {
            echo '<p style="color: #d63638;">&#10007; ' . esc_html__('Not sent', 'lcmt-dev-mailer') . '</p>';
            echo '<p><code>' . esc_html($row['mail_error'] ?: __('The request stopped before the email was sent.', 'lcmt-dev-mailer')) . '</code></p>';
        }

        // ── Context ──
        $context = [
            __('Date', 'lcmt-dev-mailer')             => get_date_from_gmt((string) $row['created_at'], $format),
            __('Form', 'lcmt-dev-mailer')             => $row['form_key'],
            __('Sent from', 'lcmt-dev-mailer')        => $row['page_path'],
            __('Landing page', 'lcmt-dev-mailer')     => $row['landing_path'],
            __('Source', 'lcmt-dev-mailer')           => ChannelClassifier::label((string) $row['channel']),
            __('Referring site', 'lcmt-dev-mailer')   => $row['referrer_host'],
            __('Campaign', 'lcmt-dev-mailer')         => trim($row['utm_source'] . ' / ' . $row['utm_medium'] . ' / ' . $row['utm_campaign'], ' /'),
            __('Ad click', 'lcmt-dev-mailer')         => $row['click_id_type'],
            __('Device', 'lcmt-dev-mailer')           => $row['device'],
            __('Browser language', 'lcmt-dev-mailer') => $row['locale'],
            __('Time on the form', 'lcmt-dev-mailer') => $row['form_seconds'] === null ? '' : human_time_diff(0, (int) $row['form_seconds']),
        ];

        echo '<h2>' . esc_html__('Context', 'lcmt-dev-mailer') . '</h2>';
        echo '<table class="widefat striped" style="max-width: 900px;"><tbody>';

        foreach ($context as $label => $value) {
            if ((string) $value === '') {
                continue;
            }

            echo '<tr><th style="width: 200px;">' . esc_html($label) . '</th><td>' . esc_html((string) $value) . '</td></tr>';
        }

        echo '</tbody></table>';

        // ── Actions ──
        $buttons = [
            'processed' => __('Mark as processed', 'lcmt-dev-mailer'),
            'new'       => __('Mark as unread', 'lcmt-dev-mailer'),
            'spam'      => __('Mark as spam', 'lcmt-dev-mailer'),
        ];

        /**
         * Filter the action buttons of a received message.
         *
         * @param array<string, string> $buttons Action => label.
         * @param array                 $row     The submission.
         */
        $buttons = (array) apply_filters('lcmt_mailer_submission_actions', $buttons, $row);

        echo '<p style="margin-top: 20px; display: flex; gap: 8px; flex-wrap: wrap;">';

        foreach ($buttons as $do => $label) {
            echo '<a class="button" href="' . esc_url(self::singleActionUrl($id, $do)) . '">' . esc_html($label) . '</a>';
        }

        echo '<a class="button button-link-delete" href="' . esc_url(self::singleActionUrl($id, 'delete')) . '" onclick="return confirm(' . esc_attr(wp_json_encode(__('Delete this message for good?', 'lcmt-dev-mailer'))) . ');">' . esc_html__('Delete', 'lcmt-dev-mailer') . '</a>';
        echo '</p>';
    }
}
