<?php

namespace LcmtDevMailer;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * A spam protection that can guard the form endpoint.
 *
 * Providers are listed by Captcha::providers() and one of them is picked in
 * the plugin settings. Add one through the `lcmt_mailer_captcha_providers`
 * filter.
 */
interface CaptchaProvider
{
    /**
     * Stable identifier stored in the settings, e.g. "altcha".
     */
    public static function id(): string;

    /**
     * Name shown in the settings select.
     */
    public static function label(): string;

    /**
     * Whether the provider has everything it needs to protect a form.
     */
    public static function isReady(): bool;

    /**
     * Register the REST routes the widget relies on, if any.
     */
    public static function registerRoutes(): void;

    /**
     * Markup appended inside every rendered form.
     */
    public static function widget(): string;

    /**
     * Check the proof sent along with a submission.
     *
     * @param array<string, mixed> $body The decoded JSON body of the request.
     */
    public static function verify(array $body): bool;

    /**
     * Print the provider's own rows of the settings table.
     *
     * Each row must carry data-captcha-provider="{id}" so it only shows
     * while the provider is selected.
     */
    public static function renderSettings(): void;
}
