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
            'args'                => [
                // The page language, sent by the front-end script on a multilingual site.
                'lang' => [
                    'type'              => 'string',
                    'required'          => false,
                    'sanitize_callback' => 'sanitize_key',
                ],
            ],
        ]);
    }

    public static function handle(\WP_REST_Request $request): \WP_REST_Response
    {
        // Answer in the language of the page the form was sent from.
        $switched = RequestLanguage::switchFor($request);

        try {
            return self::respond($request);
        } finally {
            if ($switched) {
                restore_current_locale();
            }
        }
    }

    private static function respond(\WP_REST_Request $request): \WP_REST_Response
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
        $fields = FieldParser::formFields(FieldParser::parse($to, $replyTo, $subject, $content));

        // A field that fills a recipient header must hold one valid address,
        // whatever type its placeholder declares.
        $addressFields = FieldParser::parse($to, $replyTo);

        [$body, $files, $multipart] = self::readBody($request);

        // A multipart body over post_max_size reaches PHP empty: say why
        // rather than failing the spam protection.
        if ($multipart && FileField::bodyTooLarge($body, $files)) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => __('Some fields are invalid or missing. Please check and try again.', 'lcmt-dev-mailer'),
                'errors'  => [sprintf(
                    /* translators: %s: a size such as "10 MB" */
                    __('The files sent are too large: %s maximum in all.', 'lcmt-dev-mailer'),
                    FileField::sizeText(FileField::serverLimit())
                )],
            ], 422);
        }

        // Check the spam protection this template uses, if any. A blocked
        // message is still validated below, so it can be kept as spam.
        $outcome = Captcha::check($post->ID, $body);

        if ($outcome === Captcha::UNAVAILABLE && Captcha::sanitizeUnavailable(get_option(Captcha::OPTION_UNAVAILABLE, Captcha::REFUSE)) === Captcha::ACCEPT) {
            $outcome = Captcha::PASSED;
        }

        $blocked = $outcome !== Captcha::PASSED;

        $refusal = static fn() => new \WP_REST_Response([
            'success' => false,
            'message' => __('Security verification failed. Please try again.', 'lcmt-dev-mailer'),
        ], 403);

        // Collect and validate submitted data
        $errors = [];
        $placeholders = [];
        $values = [];

        // Checked uploads of the file fields, by field name.
        $uploads = [];
        $limits  = null;

        foreach ($fields as $field) {
            if ($field['type'] === FileField::TYPE) {
                // Only from the uploaded files, never from a text value.
                $limits ??= FileField::limitsFor($post, (string) $key);
                $upload   = FileField::check($files[$field['name']] ?? null, $limits, $field['name']);

                if ($upload['error'] !== null) {
                    $errors[] = $upload['error'];
                    continue;
                }

                // The mail and the received message show the file name.
                $value = $upload['name'];

                if ($value !== '') {
                    $uploads[$field['name']] = $upload;
                }
            } else {
                $value = FieldValidator::sanitize($body[$field['name']] ?? '', $field['type']);
            }

            if ($value === '') {
                if ($field['required']) {
                    $errors[] = sprintf(
                        __('The field "%s" is required.', 'lcmt-dev-mailer'),
                        $field['name']
                    );
                }
            } elseif ($field['type'] !== FileField::TYPE) {
                $type  = isset($addressFields[$field['name']]) ? 'email' : $field['type'];
                $error = FieldValidator::error($value, $type, $field['name']);

                if ($error) {
                    $errors[] = $error;
                    continue;
                }
            }

            $placeholders['[' . $field['name'] . ']']  = $value;
            $placeholders['[' . $field['name'] . '*]'] = $value;
            $values[$field['name']] = $value;
        }

        if ($blocked && !empty($errors)) {
            // Nothing to keep, and the same answer as any blocked message.
            return $refusal();
        }

        if (!empty($errors)) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => __('Some fields are invalid or missing. Please check and try again.', 'lcmt-dev-mailer'),
                'errors'  => $errors,
            ], 422);
        }

        $context = FileField::decodeContext($body['_context'] ?? null);

        if ($blocked) {
            // Kept for the site owner, never emailed, and the visitor (or bot) learns nothing.
            // Its files are left to PHP, which deletes them at the end of the request.
            if (MetaFields::storesSubmissions($post->ID)
                && Captcha::sanitizeKeepBlocked(get_option(Captcha::OPTION_KEEP_BLOCKED, '1')) === '1'
                && SubmissionRecorder::canKeepBlocked()
            ) {
                SubmissionRecorder::record(
                    $post,
                    $key,
                    $fields,
                    $values,
                    $context,
                    (string) $request->get_header('user_agent'),
                    $outcome === Captcha::UNAVAILABLE ? SubmissionRecorder::REASON_UNAVAILABLE : SubmissionRecorder::REASON_FAILED
                );
            }

            return $refusal();
        }

        // Each file goes to a private folder under its clean name, so the
        // email carries the visitor's file name; deleted once sent.
        $attachments = [];

        foreach ($uploads as $upload) {
            $path = FileField::privateCopy($upload['tmp'], $upload['name']);

            if ($path === null) {
                FileField::cleanup($attachments);

                return new \WP_REST_Response([
                    'success' => false,
                    'message' => __('Your file could not be processed. Please try again later or contact us by email.', 'lcmt-dev-mailer'),
                ], 500);
            }

            $attachments[] = $path;
        }

        try {
            $submissionId = SubmissionRecorder::record($post, $key, $fields, $values, $context, (string) $request->get_header('user_agent'));

            /**
             * Action fired before sending the form email.
             *
             * @param string $key          The form key.
             * @param array  $placeholders The sanitized form data as placeholders.
             * @param \WP_Post $post       The mail post.
             */
            do_action('lcmt_mailer_before_send', $key, $placeholders, $post);

            $sent = SubmissionRecorder::send($submissionId, $key, $placeholders, $attachments);
        } finally {
            // Sent or not, the files are never kept.
            FileField::cleanup($attachments);
        }

        // The visitor hears about a failure even when the message was saved:
        // the site owner may never open the admin to see it, and the visitor
        // can still reach out another way.
        if (!$sent) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => __('Your message could not be sent. Please try again later or contact us by email.', 'lcmt-dev-mailer'),
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

        $response = [
            'success' => true,
            'message' => SuccessMessage::forForm($post, $key),
        ];

        // The form's confirmation page, in the submission language: the
        // front script sends the visitor there instead of showing the message.
        $redirect = ConfirmationPage::url($post, $key);

        if ($redirect !== '') {
            $response['redirect'] = $redirect;
        }

        return new \WP_REST_Response($response, 200);
    }

    /**
     * The fields and files of the request: a JSON body (forms without a file
     * field), or a multipart one (forms with one), whose `_context` is a JSON
     * string.
     *
     * @return array{0: array, 1: array, 2: bool} Fields, files, whether the body is multipart.
     */
    private static function readBody(\WP_REST_Request $request): array
    {
        $type = $request->get_content_type();

        if (($type['value'] ?? '') === 'multipart/form-data') {
            return [(array) $request->get_body_params(), (array) $request->get_file_params(), true];
        }

        return [(array) $request->get_json_params(), [], false];
    }
}
