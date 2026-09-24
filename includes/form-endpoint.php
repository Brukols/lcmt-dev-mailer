<?php

namespace LcmtDevMailer;

if (!defined('ABSPATH')) {
    exit;
}

class FormEndpoint
{
    public static function register(): void
    {
        register_rest_route('lcmt-mailer/v1', '/forms/(?P<key>[a-z0-9\-]+)', [
            'methods'             => 'POST',
            'callback'            => [self::class, 'handle'],
            'permission_callback' => '__return_true',
        ]);
    }

    public static function handle(\WP_REST_Request $request): \WP_REST_Response
    {
        $key  = $request->get_param('key');
        $post = Mailer::getPostByKey($key);

        if (!$post) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => __('Unknown form.', 'lcmt-dev-mailer'),
            ], 404);
        }

        $to      = MetaFields::get($post->ID, 'to');
        $replyTo = MetaFields::get($post->ID, 'reply_to');
        $subject = MetaFields::get($post->ID, 'subject');
        $content = MetaFields::get($post->ID, 'content');

        // Parse fields schema from all mail fields
        $fields = FieldParser::parse($to, $replyTo, $subject, $content);

        // A field that fills a recipient header must hold one valid address,
        // whatever type its placeholder declares.
        $addressFields = FieldParser::parse($to, $replyTo);

        $body = (array) $request->get_json_params();

        // Check the spam protection selected in the settings, if any
        if (!Captcha::verify($body)) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => __('Security verification failed. Please try again.', 'lcmt-dev-mailer'),
            ], 403);
        }

        // Collect and validate submitted data
        $errors = [];
        $placeholders = [];

        foreach ($fields as $field) {
            $value = self::sanitizeValue($body[$field['name']] ?? '', $field['type']);

            if ($value === '') {
                if ($field['required']) {
                    $errors[] = sprintf(
                        __('The field "%s" is required.', 'lcmt-dev-mailer'),
                        $field['name']
                    );
                }
            } else {
                $type  = isset($addressFields[$field['name']]) ? 'email' : $field['type'];
                $error = self::validateValue($value, $type, $field['name']);

                if ($error) {
                    $errors[] = $error;
                    continue;
                }
            }

            $placeholders['[' . $field['name'] . ']']  = $value;
            $placeholders['[' . $field['name'] . '*]'] = $value;
        }

        if (!empty($errors)) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => __('Some fields are invalid or missing. Please check and try again.', 'lcmt-dev-mailer'),
                'errors'  => $errors,
            ], 422);
        }

        /**
         * Action fired before sending the form email.
         *
         * @param string $key          The form key.
         * @param array  $placeholders The sanitized form data as placeholders.
         * @param \WP_Post $post       The mail post.
         */
        do_action('lcmt_mailer_before_send', $key, $placeholders, $post);

        $sent = Mailer::sendByKey($key, $placeholders);

        if (!$sent) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => __('Failed to send email.', 'lcmt-dev-mailer'),
            ], 500);
        }

        /**
         * Action fired after the form email was sent successfully.
         *
         * @param string $key          The form key.
         * @param array  $placeholders The sanitized form data as placeholders.
         * @param \WP_Post $post       The mail post.
         */
        do_action('lcmt_mailer_after_send', $key, $placeholders, $post);

        return new \WP_REST_Response([
            'success' => true,
            'message' => Settings::getSuccessMessage(),
        ], 200);
    }

    /**
     * Turn a submitted value into plain text, keeping line breaks for textareas.
     *
     * @param mixed $raw
     */
    private static function sanitizeValue($raw, string $type): string
    {
        if (!is_scalar($raw)) {
            return '';
        }

        $value = $type === 'textarea'
            ? sanitize_textarea_field((string) $raw)
            : sanitize_text_field((string) $raw);

        return trim($value);
    }

    /**
     * Check a non-empty value against its field type.
     *
     * @return string|null The error message, or null when the value is valid.
     */
    private static function validateValue(string $value, string $type, string $name): ?string
    {
        $valid = match ($type) {
            'email'  => is_email($value) !== false,
            'number' => is_numeric($value),
            'url'    => filter_var($value, FILTER_VALIDATE_URL) !== false
                && in_array(strtolower((string) parse_url($value, PHP_URL_SCHEME)), ['http', 'https'], true),
            'tel'    => (bool) preg_match('/^\+?[0-9\s().-]{4,}$/', $value),
            default  => true,
        };

        if ($valid) {
            return null;
        }

        return match ($type) {
            /* translators: %s: the field name */
            'email'  => sprintf(__('The field "%s" must be a valid email address.', 'lcmt-dev-mailer'), $name),
            /* translators: %s: the field name */
            'number' => sprintf(__('The field "%s" must be a number.', 'lcmt-dev-mailer'), $name),
            /* translators: %s: the field name */
            'url'    => sprintf(__('The field "%s" must be a valid URL.', 'lcmt-dev-mailer'), $name),
            /* translators: %s: the field name */
            default  => sprintf(__('The field "%s" must be a valid phone number.', 'lcmt-dev-mailer'), $name),
        };
    }
}
