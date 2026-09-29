<?php

namespace LcmtDevMailer;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Hooks received messages into Tools → Export / Erase Personal Data and the
 * privacy policy guide.
 */
class Privacy
{
    private const ID = 'lcmt-dev-mailer';

    public static function registerExporter(array $exporters): array
    {
        $exporters[self::ID] = [
            'exporter_friendly_name' => __('Received messages', 'lcmt-dev-mailer'),
            'callback'               => [self::class, 'export'],
        ];

        return $exporters;
    }

    public static function registerEraser(array $erasers): array
    {
        $erasers[self::ID] = [
            'eraser_friendly_name' => __('Received messages', 'lcmt-dev-mailer'),
            'callback'             => [self::class, 'erase'],
        ];

        return $erasers;
    }

    /**
     * One person sends a handful of messages, so everything is done in one page.
     */
    public static function export(string $email, int $page = 1): array
    {
        $data = [];

        foreach (self::matching($email) as $row) {
            $items = [
                ['name' => __('Date', 'lcmt-dev-mailer'), 'value' => get_date_from_gmt((string) $row['created_at'])],
                ['name' => __('Form', 'lcmt-dev-mailer'), 'value' => $row['form_key']],
                ['name' => __('Sent from', 'lcmt-dev-mailer'), 'value' => $row['page_path']],
            ];

            foreach ($row['fields'] as $field) {
                $items[] = ['name' => $field['name'], 'value' => $field['value']];
            }

            $data[] = [
                'group_id'    => self::ID,
                'group_label' => __('Received messages', 'lcmt-dev-mailer'),
                'item_id'     => 'lcmt-submission-' . $row['id'],
                'data'        => $items,
            ];
        }

        return ['data' => $data, 'done' => true];
    }

    /**
     * Anonymizes rather than deletes, so statistics keep the message.
     */
    public static function erase(string $email, int $page = 1): array
    {
        $ids = array_map(static fn(array $row) => (int) $row['id'], self::matching($email));

        SubmissionRepository::anonymize($ids);

        return [
            'items_removed'  => count($ids) > 0,
            'items_retained' => false,
            'messages'       => [],
            'done'           => true,
        ];
    }

    public static function addPolicyContent(): void
    {
        if (!function_exists('wp_add_privacy_policy_content')) {
            return;
        }

        $end = SubmissionSettings::retentionAction() === 'delete'
            ? __('they are then deleted', 'lcmt-dev-mailer')
            : __('they are then anonymized: only the date, the form, the page and the origin of the visit are kept, for statistics', 'lcmt-dev-mailer');

        $text = sprintf(
            /* translators: 1: number of days, 2: what happens after */
            __('The messages you send through our forms are saved on this site for %1$d days so we can answer and follow up on your request; %2$s. We record the page you sent it from and how you reached the site (search engine, ad, other site, campaign), never your IP address.', 'lcmt-dev-mailer'),
            SubmissionSettings::retentionDays(),
            $end
        );

        wp_add_privacy_policy_content('LCMT Mailer', wp_kses_post(wpautop($text)));
    }

    /**
     * [lcmt-retention-days] prints the retention period, for privacy notices
     * that follow the setting.
     */
    public static function retentionShortcode(): string
    {
        return (string) SubmissionSettings::retentionDays();
    }

    /**
     * Rows holding personal data where one value is exactly this address.
     */
    private static function matching(string $email): array
    {
        $email = trim($email);

        if ($email === '') {
            return [];
        }

        return array_values(array_filter(
            SubmissionRepository::findContaining($email),
            static fn(array $row) => SubmissionData::containsEmail($row['fields'] ?? [], $email)
        ));
    }
}
