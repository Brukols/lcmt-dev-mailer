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
        return [
            'cb'         => '<input type="checkbox" />',
            'summary'    => __('Sender', 'lcmt-dev-mailer'),
            'status'     => __('Status', 'lcmt-dev-mailer'),
            'form_key'   => __('Form', 'lcmt-dev-mailer'),
            'page_path'  => __('Sent from', 'lcmt-dev-mailer'),
            'channel'    => __('Source', 'lcmt-dev-mailer'),
            'mail'       => __('Email', 'lcmt-dev-mailer'),
            'created_at' => __('Date', 'lcmt-dev-mailer'),
        ];
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
            'all'    => [__('All', 'lcmt-dev-mailer'), [], $current['status'] === '' && !$current['failed']],
            'new'    => [__('Unread', 'lcmt-dev-mailer'), ['status' => 'new'], $current['status'] === 'new'],
            'failed' => [__('Email failed', 'lcmt-dev-mailer'), ['failed' => 1], $current['failed']],
            'spam'   => [__('Spam', 'lcmt-dev-mailer'), ['status' => 'spam'], $current['status'] === 'spam'],
        ];

        $links = [];

        foreach ($views as $key => [$label, $args, $active]) {
            $links[$key] = sprintf(
                '<a href="%s"%s>%s <span class="count">(%s)</span></a>',
                esc_url(SubmissionsPage::url($args)),
                $active ? ' class="current" aria-current="page"' : '',
                esc_html($label),
                number_format_i18n(SubmissionRepository::count($args))
            );
        }

        return $links;
    }

    protected function get_bulk_actions(): array
    {
        return [
            'read'      => __('Mark as read', 'lcmt-dev-mailer'),
            'processed' => __('Mark as processed', 'lcmt-dev-mailer'),
            'spam'      => __('Mark as spam', 'lcmt-dev-mailer'),
            'delete'    => __('Delete', 'lcmt-dev-mailer'),
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

        $actions = [
            'view'   => '<a href="' . esc_url(SubmissionsPage::url(['submission' => $item['id']])) . '">' . esc_html__('View', 'lcmt-dev-mailer') . '</a>',
            'delete' => '<a class="submitdelete" href="' . esc_url(SubmissionsPage::singleActionUrl((int) $item['id'], 'delete')) . '" onclick="return confirm(' . esc_attr(wp_json_encode(__('Delete this message for good?', 'lcmt-dev-mailer'))) . ');">' . esc_html__('Delete', 'lcmt-dev-mailer') . '</a>',
        ];

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
