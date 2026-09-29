<?php

namespace LcmtDevMailer;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Loads the script that remembers the landing page on every public page, so
 * a form sent later in the visit knows where the visitor came from.
 */
class Attribution
{
    public const OPTION_WITHOUT_CONSENT = 'lcmt_mailer_attribution_without_consent';

    public static function enqueue(): void
    {
        // Run after WP Consent API when it is there, so wp_has_consent exists.
        $deps = wp_script_is('wp-consent-api', 'registered') ? ['wp-consent-api'] : [];

        wp_enqueue_script(
            'lcmt-attribution',
            LCMT_MAILER_URL . 'assets/dist/attribution.js',
            $deps,
            lcmt_mailer_asset_version('assets/dist/attribution.js'),
            true
        );

        wp_localize_script('lcmt-attribution', 'lcmtMailerAttribution', [
            'storeWithoutConsent' => self::storesWithoutConsent(),
        ]);
    }

    /**
     * Whether the landing is remembered when no consent tool is installed.
     */
    public static function storesWithoutConsent(): bool
    {
        return get_option(self::OPTION_WITHOUT_CONSENT, '1') === '1';
    }
}
