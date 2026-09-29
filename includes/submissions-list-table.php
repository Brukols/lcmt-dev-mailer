<?php

namespace LcmtDevMailer;

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('WP_List_Table')) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * The table of Email templates → Received messages. Loaded only on that screen.
 */
class SubmissionsListTable extends \WP_List_Table
{
    private const PER_PAGE = 20;

    public function __construct()
    {
        parent::__construct([
            'singular' => 'submission',
            'plural'   => 'submissions',
            'ajax'     => false,
        ]);
    }

    public function get_columns(): array
    {
        $columns = [
            'cb'            => '<input type="checkbox" />',
            'summary'       => __('Sender', 'lcmt-dev-mailer'),
            'status'        => __('Status', 'lcmt-dev-mailer'),
            'form_key'      => __('Form', 'lcmt-dev-mailer'),
            'page_path'     => __('Sent from', 'lcmt-dev-mailer'),
            'channel'       => __('Source', 'lcmt-dev-mailer'),
            'referrer_host' => __('Referring site', 'lcmt-dev-mailer'),
            'mail'          => __('Email', 'lcmt-dev-mailer'),
            'created_at'    => __('Date', 'lcmt-dev-mailer'),
        ];

        if (SubmissionsPage::filtersFromRequest()['trash']) {
            $columns['trashed_at'] = __('Deletion', 'lcmt-dev-mailer');
        }

        return $columns;
    }

    protected function column_trashed_at(array $item): string
    {
        return $item['trashed_at'] === null ? '' : SubmissionsPage::deletionLabel($item);
    }

    public function prepare_items(): void
    {
        $filters = SubmissionsPage::filtersFromRequest();

        $this->items = SubmissionRepository::search($filters, self::PER_PAGE, $this->get_pagenum());

        $this->set_pagination_args([
            'total_items' => SubmissionRepository::count($filters),
            'per_page'    => self::PER_PAGE,
        ]);

        $this->_column_headers = [$this->get_columns(), [], []];
    }

    public function no_items(): void
    {
        esc_html_e('No messages yet.', 'lcmt-dev-mailer');
    }

    protected function get_views(): array
    {
        $current = SubmissionsPage::filtersFromRequest();

        $views = [
            'all'    => [__('All', 'lcmt-dev-mailer'), [], $current['status'] === '' && !$current['failed'] && !$current['trash']],
            'new'    => [__('Unread', 'lcmt-dev-mailer'), ['status' => 'new'], $current['status'] === 'new' && !$current['trash']],
            'failed' => [__('Email failed', 'lcmt-dev-mailer'), ['failed' => 1], $current['failed']],
            'spam'   => [__('Spam', 'lcmt-dev-mailer'), ['status' => 'spam'], $current['status'] === 'spam' && !$current['trash']],
        ];

        // Like WordPress: only once something is in it, and last.
        if (Retention::trashEnabled() && SubmissionRepository::countTrashed()) {
            $views['trash'] = [__('Trash', 'lcmt-dev-mailer'), ['status' => 'trash'], $current['trash']];
        }

        $links = [];

        foreach ($views as $key => [$label, $args, $active]) {
            $links[$key] = sprintf(
                '<a href="%s"%s>%s <span class="count">(%s)</span></a>',
                esc_url(SubmissionsPage::url($args)),
                $active ? ' class="current" aria-current="page"' : '',
                esc_html($label),
                number_format_i18n($key === 'trash' ? SubmissionRepository::countTrashed() : SubmissionRepository::count($args))
            );
        }

        return $links;
    }

    protected function get_bulk_actions(): array
    {
        if (SubmissionsPage::filtersFromRequest()['trash']) {
            return [
                'restore' => __('Restore', 'lcmt-dev-mailer'),
                'delete'  => __('Delete permanently', 'lcmt-dev-mailer'),
            ];
        }

        return [
            'read'      => __('Mark as read', 'lcmt-dev-mailer'),
            'processed' => __('Mark as processed', 'lcmt-dev-mailer'),
            'spam'      => __('Mark as spam', 'lcmt-dev-mailer'),
            Retention::trashEnabled() ? 'trash' : 'delete' => Retention::trashEnabled() ? __('Move to Trash', 'lcmt-dev-mailer') : __('Delete', 'lcmt-dev-mailer'),
        ];
    }

    protected function extra_tablenav($which): void
    {
        if ($which !== 'top') {
            return;
        }

        $filters = SubmissionsPage::filtersFromRequest();

        echo '<div class="alignleft actions">';

        echo '<select name="form_key"><option value="">' . esc_html__('All forms', 'lcmt-dev-mailer') . '</option>';
        foreach (SubmissionRepository::formKeys() as $key) {
            printf('<option value="%1$s"%2$s>%1$s</option>', esc_attr($key), selected($filters['form_key'], $key, false));
        }
        echo '</select>';

        echo '<select name="channel"><option value="">' . esc_html__('All sources', 'lcmt-dev-mailer') . '</option>';
        foreach (ChannelClassifier::CHANNELS as $channel) {
            printf('<option value="%s"%s>%s</option>', esc_attr($channel), selected($filters['channel'], $channel, false), esc_html(ChannelClassifier::label($channel)));
        }
        echo '</select>';

        submit_button(__('Filter', 'lcmt-dev-mailer'), '', 'filter_action', false);

        if ($filters['trash']) {
            submit_button(
                __('Empty Trash', 'lcmt-dev-mailer'),
                'apply',
                'empty_trash',
                false,
                ['value' => '1', 'onclick' => 'return confirm(' . wp_json_encode(__('Delete every message in the trash for good?', 'lcmt-dev-mailer')) . ');']
            );

            // Beside the button rather than in a notice box, which would push the list down.
            $days = Retention::trashDays();

            echo '<span class="lcmt-trash-info"><span class="dashicons dashicons-clock" aria-hidden="true"></span> ' . esc_html(sprintf(
                /* translators: %s: number of days */
                _n('Messages in the trash are deleted automatically after %s day.', 'Messages in the trash are deleted automatically after %s days.', $days, 'lcmt-dev-mailer'),
                number_format_i18n($days)
            )) . '</span>';
        }

        echo '</div>';
    }

    protected function column_cb($item): string
    {
        return '<input type="checkbox" name="ids[]" value="' . (int) $item['id'] . '" />';
    }

    protected function column_summary(array $item): string
    {
        $label = $item['fields'] === null
            ? __('Anonymized', 'lcmt-dev-mailer')
            : (SubmissionData::summary($item['fields']) ?: __('(no text)', 'lcmt-dev-mailer'));

        $text = esc_html($label);

        if ($item['status'] === 'new') {
            $text = '<strong>' . $text . '</strong>';
        }

        $view    = '<a href="' . esc_url(SubmissionsPage::url(['submission' => $item['id']])) . '">' . esc_html__('View', 'lcmt-dev-mailer') . '</a>';
        $confirm = ' onclick="return confirm(' . esc_attr(wp_json_encode(__('Delete this message for good?', 'lcmt-dev-mailer'))) . ');"';
        $url     = static fn(string $do) => esc_url(SubmissionsPage::singleActionUrl((int) $item['id'], $do));

        if ($item['trashed_at'] !== null) {
            $actions = [
                'view'    => $view,
                'restore' => '<a href="' . $url('restore') . '">' . esc_html__('Restore', 'lcmt-dev-mailer') . '</a>',
                'delete'  => '<a class="submitdelete" href="' . $url('delete') . '"' . $confirm . '>' . esc_html__('Delete permanently', 'lcmt-dev-mailer') . '</a>',
            ];
        } elseif (Retention::trashEnabled()) {
            $actions = [
                'view'  => $view,
                'trash' => '<a class="submitdelete" href="' . $url('trash') . '">' . esc_html__('Trash', 'lcmt-dev-mailer') . '</a>',
            ];
        } else {
            $actions = [
                'view'   => $view,
                'delete' => '<a class="submitdelete" href="' . $url('delete') . '"' . $confirm . '>' . esc_html__('Delete', 'lcmt-dev-mailer') . '</a>',
            ];
        }

        return '<a href="' . esc_url(SubmissionsPage::url(['submission' => $item['id']])) . '">' . $text . '</a>' . $this->row_actions($actions);
    }

    protected function column_channel(array $item): string
    {
        $html = esc_html(ChannelClassifier::label((string) $item['channel']));

        if ($item['utm_campaign'] !== '') {
            $html .= '<br><small>' . esc_html($item['utm_campaign']) . '</small>';
        }

        return $html;
    }

    // Empty for a direct visit, or when the browser hid where it came from.
    protected function column_referrer_host(array $item): string
    {
        $host = (string) ($item['referrer_host'] ?? '');

        return $host === '' ? '<span aria-hidden="true">&mdash;</span>' : esc_html($host);
    }

    protected function column_status(array $item): string
    {
        return SubmissionsPage::statusBadge((string) $item['status']);
    }

    protected function column_mail(array $item): string
    {
        return SubmissionsPage::mailBadge((int) $item['mail_sent'] === 1);
    }

    protected function column_created_at(array $item): string
    {
        return esc_html(SubmissionsPage::formatDate($item, get_option('date_format'), get_option('time_format')));
    }

    protected function column_default($item, $column_name): string
    {
        return esc_html((string) ($item[$column_name] ?? ''));
    }
}
