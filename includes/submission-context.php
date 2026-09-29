<?php

namespace LcmtDevMailer;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Cleans the context the browser sends with a form into stored columns.
 *
 * Nothing in it is trusted: each value is checked against the shape it must
 * have and cut to its column size, anything else becomes empty. Only paths
 * and hosts are kept, never query strings, which can carry personal data.
 */
class SubmissionContext
{
    public const CLICK_IDS = ['gclid', 'gbraid', 'wbraid', 'msclkid', 'fbclid', 'ttclid', 'li_fat_id'];

    private const DEVICES = ['mobile', 'tablet', 'desktop'];

    private const MAX_SECONDS = 86400;

    /**
     * @param mixed  $raw      The `_context` value from the JSON body.
     * @param string $siteHost The host of home_url(), to spot internal referrers.
     * @return array{page_path: string, landing_path: string, referrer_host: string, utm_source: string, utm_medium: string, utm_campaign: string, click_id_type: string, device: string, locale: string, form_seconds: ?int}
     */
    public static function fromRequest($raw, string $siteHost): array
    {
        $raw     = is_array($raw) ? $raw : [];
        $landing = isset($raw['landing']) && is_array($raw['landing']) ? $raw['landing'] : [];

        return [
            'page_path'     => self::path($raw['page'] ?? ''),
            'landing_path'  => self::path($landing['path'] ?? ''),
            'referrer_host' => self::externalHost($landing['referrer'] ?? '', $siteHost),
            'utm_source'    => strtolower(self::token($landing['utm_source'] ?? '', 100)),
            'utm_medium'    => strtolower(self::token($landing['utm_medium'] ?? '', 100)),
            'utm_campaign'  => self::token($landing['utm_campaign'] ?? '', 150),
            'click_id_type' => self::oneOf($landing['click_id'] ?? '', self::CLICK_IDS),
            'device'        => self::oneOf($raw['device'] ?? '', self::DEVICES),
            'locale'        => self::locale($raw['locale'] ?? ''),
            'form_seconds'  => self::seconds($raw['seconds'] ?? null),
        ];
    }

    /**
     * A path on this site, without its query string or fragment.
     *
     * @param mixed $value
     */
    private static function path($value): string
    {
        if (!is_string($value) || !str_starts_with($value, '/') || str_starts_with($value, '//')) {
            return '';
        }

        $path = (string) parse_url($value, PHP_URL_PATH);

        if (!preg_match('#^/[A-Za-z0-9\-._~/%]*$#', $path)) {
            return '';
        }

        return substr($path, 0, 255);
    }

    /**
     * The host of a web page on another site, without "www.", or empty.
     *
     * @param mixed $url
     */
    private static function externalHost($url, string $siteHost): string
    {
        if (!is_string($url) || $url === '') {
            return '';
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        $host   = strtolower((string) parse_url($url, PHP_URL_HOST));

        if (!in_array($scheme, ['http', 'https'], true) || !preg_match('/^[a-z0-9.-]+$/', $host)) {
            return '';
        }

        $host = preg_replace('/^www\./', '', $host);
        $site = preg_replace('/^www\./', '', strtolower($siteHost));

        return $host === $site ? '' : substr($host, 0, 191);
    }

    /**
     * Free text from a campaign parameter, without control characters,
     * tags delimiters or quotes.
     *
     * @param mixed $value
     */
    private static function token($value, int $max): string
    {
        if (!is_string($value)) {
            return '';
        }

        $clean = preg_replace('/[\x00-\x1F\x7F<>"\']/u', '', $value);

        // preg_replace returns null on invalid UTF-8.
        if ($clean === null) {
            return '';
        }

        return mb_substr(trim($clean), 0, $max);
    }

    /**
     * @param mixed         $value
     * @param list<string>  $allowed
     */
    private static function oneOf($value, array $allowed): string
    {
        return is_string($value) && in_array($value, $allowed, true) ? $value : '';
    }

    /**
     * @param mixed $value
     */
    private static function locale($value): string
    {
        return is_string($value) && preg_match('/^[a-z]{2,3}(-[A-Za-z]{2})?$/', $value) ? $value : '';
    }

    /**
     * @param mixed $value
     */
    private static function seconds($value): ?int
    {
        if (!is_numeric($value)) {
            return null;
        }

        $seconds = (int) $value;

        return $seconds >= 0 && $seconds <= self::MAX_SECONDS ? $seconds : null;
    }
}
