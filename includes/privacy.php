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
            $items = [];

            foreach (SubmissionsPage::contextItems($row) as $name => $value) {
                $items[] = ['name' => $name, 'value' => $value];
            }

            $items[] = [
                'name'  => __('Email', 'lcmt-dev-mailer'),
                'value' => (int) $row['mail_sent'] === 1 ? __('Sent', 'lcmt-dev-mailer') : __('Not sent', 'lcmt-dev-mailer'),
            ];

            if ((string) $row['mail_error'] !== '') {
                $items[] = ['name' => __('Email error', 'lcmt-dev-mailer'), 'value' => (string) $row['mail_error']];
            }

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
            : __('they are then anonymized: only the day, the form, the page, the origin of the visit, the type of device, the language of your browser and the browser and operating system families are kept, for statistics', 'lcmt-dev-mailer');

        $paragraphs = [
            sprintf(
                /* translators: 1: number of days, 2: what happens after */
                __('The messages you send through our forms are saved on this site for %1$d days so we can answer and follow up on your request; %2$s. We record the page you sent it from and how you reached the site (search engine, ad, other site, campaign), never your IP address.', 'lcmt-dev-mailer'),
                SubmissionSettings::retentionDays(),
                $end
            ),
            __('With your message, we also record the type of device, the language of your browser and the time spent on the form.', 'lcmt-dev-mailer'),
            SubmissionSettings::retentionAction() === 'delete'
                ? __('We also record the user agent of your browser, a technical description of your browser and device. It is deleted with the message.', 'lcmt-dev-mailer')
                : __('We also record the user agent of your browser, a technical description of your browser and device. It is erased when the message is anonymized.', 'lcmt-dev-mailer'),
        ];

        if (Attribution::consentApiActive()) {
            $paragraphs[] = __('Once you accept statistics, the page you arrived on and the campaign that brought you are kept in your browser until you close the tab, so a form sent later in the visit knows where it started.', 'lcmt-dev-mailer');
        } elseif (Attribution::storesWithoutConsent()) {
            $paragraphs[] = __('The page you arrived on and the campaign that brought you are kept in your browser until you close the tab, so a form sent later in the visit knows where it started.', 'lcmt-dev-mailer');
        }

        $text = implode("\n\n", $paragraphs);

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
