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

    /**
     * wp_enqueue_scripts priority: after the consent tools, which register
     * the WP Consent API script at the default priority.
     */
    public const PRIORITY = 100;

    public static function enqueue(): void
    {
        wp_enqueue_script(
            'lcmt-attribution',
            LCMT_MAILER_URL . 'assets/dist/attribution.js',
            self::dependencies(),
            lcmt_mailer_asset_version('assets/dist/attribution.js'),
            true
        );

        wp_localize_script('lcmt-attribution', 'lcmtMailerAttribution', [
            'storeWithoutConsent' => self::storesWithoutConsent(),
            'consentApi'          => self::consentApiActive(),
        ]);
    }

    /**
     * Run after WP Consent API when it is there, so wp_has_consent exists.
     * WordPress resolves dependencies when it prints the scripts, so the
     * handle may still be registered after this call when the plugin is active.
     *
     * @return list<string>
     */
    public static function dependencies(): array
    {
        return self::consentApiActive() || wp_script_is('wp-consent-api', 'registered') ? ['wp-consent-api'] : [];
    }

    /**
     * Whether WP Consent API is installed and active. The script then never
     * falls back to the site setting, even before wp_has_consent is loaded.
     */
    public static function consentApiActive(): bool
    {
        return class_exists('WP_CONSENT_API') || function_exists('wp_has_consent');
    }

    /**
     * Whether the landing is remembered when no consent tool is installed.
     */
    public static function storesWithoutConsent(): bool
    {
        return get_option(self::OPTION_WITHOUT_CONSENT, '1') === '1';
    }
}
