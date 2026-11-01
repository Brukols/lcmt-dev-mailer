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
     * Markup placed inside every rendered form (FormRenderer::placeWidget()).
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
     * Always shown, under the provider's label: each mail template can pick
     * any provider, whatever the site default.
     */
    public static function renderSettings(): void;
}

/**
 * A provider that loads front-end scripts of its own.
 *
 * Captcha::widget() calls enqueueAssets() the first time a page renders a
 * form protected by the provider, so pages without such a form load nothing.
 */
interface CaptchaAssets
{
    public static function enqueueAssets(): void;
}

/**
 * A provider with its own badge in the "Protection" column of the email
 * templates list. Optional: providers without it get a neutral shield.
 */
interface CaptchaBadge
{
    /**
     * Dashicon class, e.g. "dashicons-shield".
     */
    public static function icon(): string;

    /**
     * Hex color of the icon, e.g. "#2271b1".
     */
    public static function color(): string;
}

/**
 * Thrown by CaptchaProvider::verify() when the provider's service could not
 * give an answer, as opposed to the proof being wrong.
 */
class CaptchaUnavailable extends \RuntimeException
{
}
