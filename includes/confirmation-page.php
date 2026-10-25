<?php

namespace LcmtDevMailer;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * The page a visitor is sent to after a successful submission, set per form
 * (meta MetaFields::FIELD_CONFIRMATION_PAGE). Without one, the form shows the
 * success message as usual.
 */
class ConfirmationPage
{
    /**
     * The page ID held by a stored or submitted value, 0 for none or for
     * anything that is not a plain positive integer.
     */
    public static function normalizeId(mixed $raw): int
    {
        if (is_int($raw)) {
            return max(0, $raw);
        }

        if (!is_string($raw)) {
            return 0;
        }

        $raw = trim($raw);

        return ctype_digit($raw) ? (int) $raw : 0;
    }

    /**
     * The page to send the visitor to: the translation in the submission
     * language when it is published, else the configured page when it is
     * published, else none (0).
     *
     * @param callable(int): bool $isPublished
     */
    public static function pick(int $pageId, int $translationId, callable $isPublished): int
    {
        if ($translationId > 0 && $isPublished($translationId)) {
            return $translationId;
        }

        if ($pageId > 0 && $isPublished($pageId)) {
            return $pageId;
        }

        return 0;
    }

    /**
     * The confirmation page of a form, 0 when none is set.
     */
    public static function pageId(int $postId): int
    {
        return self::normalizeId(get_post_meta($postId, MetaFields::FIELD_CONFIRMATION_PAGE, true));
    }

    /**
     * The URL to send the visitor to after a successful submission of this
     * form, or '' to show the success message. Only a URL of this site (or a
     * host allowed through allowed_redirect_hosts) is returned, so the REST
     * endpoint never redirects elsewhere, whatever a filter returns.
     */
    public static function url(\WP_Post $post, string $key): string
    {
        $pageId = self::pageId($post->ID);
        $target = 0;

        if ($pageId) {
            $target = self::pick(
                $pageId,
                self::translationId($pageId, RequestLanguage::current()),
                static fn (int $id): bool => get_post_status($id) === 'publish'
            );
        }

        $url = $target ? (string) get_permalink($target) : '';

        /**
         * Filters the URL a visitor is sent to after a successful submission.
         * Return '' to show the success message instead. Any URL outside the
         * site (and allowed_redirect_hosts) is dropped.
         *
         * @param string   $url    The confirmation page URL, '' when none.
         * @param string   $key    The form key.
         * @param \WP_Post $post   The mail post.
         * @param int      $target The page ID the URL points to, 0 when none.
         */
        $url = apply_filters('lcmt_mailer_confirmation_url', $url, $key, $post, $target);

        if (!is_string($url) || trim($url) === '') {
            return '';
        }

        return (string) wp_validate_redirect(wp_sanitize_redirect(trim($url)), '');
    }

    /**
     * The translation of a page in the submission language (Polylang), 0
     * without Polylang, without a language or without a translation.
     */
    private static function translationId(int $pageId, ?string $language): int
    {
        if ($language === null || $language === '' || !function_exists('pll_get_post')) {
            return 0;
        }

        return (int) pll_get_post($pageId, $language);
    }
}
