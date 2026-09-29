<?php

namespace LcmtDevMailer;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Saves a submission before its email goes out, then records whether it did.
 */
class SubmissionRecorder
{
    /**
     * The message of the last wp_mail failure during send().
     */
    private static ?string $mailError = null;

    /**
     * @param array<string, array{name: string, required: bool, type: string}> $fields
     * @param array<string, string> $values
     * @param mixed $rawContext The `_context` value of the request body.
     * @return int The submission id, or 0 when this form does not save messages.
     */
    public static function record(\WP_Post $post, string $key, array $fields, array $values, $rawContext): int
    {
        if (!MetaFields::storesSubmissions($post->ID)) {
            return 0;
        }

        $home    = home_url();
        $context = SubmissionContext::fromRequest($rawContext, (string) parse_url($home, PHP_URL_HOST));

        /**
         * Filter the channel a submission is counted under.
         *
         * @param string $channel One of ChannelClassifier::CHANNELS.
         * @param array  $context The cleaned context columns.
         */
        $channel = (string) apply_filters('lcmt_mailer_submission_channel', ChannelClassifier::classify($context), $context);

        return SubmissionRepository::insert([
            'form_key'     => $key,
            'mail_post_id' => $post->ID,
            'created_at'   => current_time('mysql', true),
            'status'       => 'new',
            'mail_sent'    => 0,
            'fields'       => wp_json_encode(SubmissionData::snapshot($fields, $values), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'page_id'      => self::pageId($home, $context['page_path']),
            'channel'      => substr($channel, 0, 30),
        ] + $context);
    }

    /**
     * Send the email of a submission and store the outcome on it.
     *
     * @param int $id 0 sends without recording anything.
     */
    public static function send(int $id, string $key, array $placeholders): bool
    {
        self::$mailError = null;

        $sent = Mailer::sendByKey($key, $placeholders);

        if ($id) {
            SubmissionRepository::update($id, [
                'mail_sent'  => $sent ? 1 : 0,
                'mail_error' => $sent ? null : (self::$mailError ?? __('The email could not be built: check that its template is published and has a recipient.', 'lcmt-dev-mailer')),
            ]);
        }

        self::$mailError = null;

        return $sent;
    }

    public static function captureMailError(\WP_Error $error): void
    {
        self::$mailError = $error->get_error_message();
    }

    /**
     * The post the form was sent from, when the path resolves to one.
     */
    private static function pageId(string $home, string $path): int
    {
        if ($path === '') {
            return 0;
        }

        // The path already holds any subdirectory of home_url().
        $origin = (string) preg_replace('#^(https?://[^/]+).*$#', '$1', $home);

        return (int) url_to_postid($origin . $path);
    }
}
