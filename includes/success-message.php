<?php

namespace LcmtDevMailer;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * The message shown after a successful submission. Each form can have its own
 * (meta MetaFields::FIELD_SUCCESS_MESSAGE); without one, the site's message
 * (Settings) is shown. A confirmation page, when the form has one, still wins:
 * the visitor is taken there instead.
 */
class SuccessMessage
{
    /**
     * The first message that is not blank, trimmed.
     */
    public static function pick(string ...$messages): string
    {
        foreach ($messages as $message) {
            $message = trim($message);

            if ($message !== '') {
                return $message;
            }
        }

        return '';
    }

    /**
     * The message of a form: its translation's in the submission language
     * when that one has a message, else its own, else the site's.
     *
     * @param int $translationId The form's translation in the submission language, 0 for none.
     */
    public static function message(int $postId, int $translationId): string
    {
        return self::pick(
            $translationId > 0 && $translationId !== $postId ? self::formMessage($translationId) : '',
            self::formMessage($postId),
            Settings::siteSuccessMessage()
        );
    }

    /**
     * The message returned to the browser after a successful submission of
     * this form.
     */
    public static function forForm(\WP_Post $post, string $key): string
    {
        $message = self::message($post->ID, self::translationId($post->ID, RequestLanguage::current()));

        /** This filter is documented in includes/settings.php */
        return (string) apply_filters('lcmt_mailer_success_message', $message, $key, $post);
    }

    private static function formMessage(int $postId): string
    {
        return (string) get_post_meta($postId, MetaFields::FIELD_SUCCESS_MESSAGE, true);
    }

    /**
     * The published translation of a form in the submission language
     * (Polylang), 0 without Polylang, without a language or without one.
     */
    private static function translationId(int $postId, ?string $language): int
    {
        if ($language === null || $language === '' || !function_exists('pll_get_post')) {
            return 0;
        }

        $translationId = (int) pll_get_post($postId, $language);

        return $translationId > 0 && get_post_status($translationId) === 'publish' ? $translationId : 0;
    }
}
