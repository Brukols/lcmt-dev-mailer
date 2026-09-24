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
            $value = FieldValidator::sanitize($body[$field['name']] ?? '', $field['type']);

            if ($value === '') {
                if ($field['required']) {
                    $errors[] = sprintf(
                        __('The field "%s" is required.', 'lcmt-dev-mailer'),
                        $field['name']
                    );
                }
            } else {
                $type  = isset($addressFields[$field['name']]) ? 'email' : $field['type'];
                $error = FieldValidator::error($value, $type, $field['name']);

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
}
