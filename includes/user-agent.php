<?php

namespace LcmtDevMailer;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * The User-Agent request header of a submission. The raw string can single
 * out one device, so it is personal data; the browser and system families
 * derived from it are not, and are what statistics use.
 */
class UserAgent
{
    /**
     * Characters of the raw header that are stored (its column is varchar(500)).
     */
    public const MAX_LENGTH = 500;

    /**
     * Crawlers, monitors and command-line clients.
     */
    private const BOT_PATTERN = '/bot[\/;)]|crawl|spider|slurp|facebookexternalhit|bingpreview|headlesschrome|lighthouse|^curl\/|^wget\/|python-requests|go-http-client|okhttp\/|libwww|httpclient/i';

    /**
     * The header as it is stored: no control characters, at most MAX_LENGTH
     * characters, empty when it is not valid UTF-8.
     */
    public static function clean(string $raw): string
    {
        $clean = preg_replace('/[\x00-\x1F\x7F]/u', '', $raw);

        // preg_replace returns null on invalid UTF-8.
        if ($clean === null) {
            return '';
        }

        return mb_substr(trim($clean), 0, self::MAX_LENGTH, 'UTF-8');
    }

    /**
     * Browser and operating system families, without versions.
     *
     * @return array{browser: string, os: string}
     */
    public static function parse(string $ua): array
    {
        if ($ua === '') {
            return ['browser' => '', 'os' => ''];
        }

        return ['browser' => self::browser($ua), 'os' => self::os($ua)];
    }

    /**
     * Order matters: most browsers still say "Chrome" and "Safari" too.
     */
    private static function browser(string $ua): string
    {
        if (preg_match(self::BOT_PATTERN, $ua)) {
            return 'Bot';
        }

        $families = [
            'Edge'             => '#\bEdg(e|A|iOS)?/#',
            'Opera'            => '#\bOPR/|\bOpera\b#',
            'Samsung Internet' => '#SamsungBrowser/#',
            'Firefox'          => '#\b(Firefox|FxiOS)/#',
            'Chrome'           => '#\b(Chrome|CriOS)/#',
            'Safari'           => '#\bVersion/[\d.]+.*\bSafari/#',
        ];

        foreach ($families as $name => $pattern) {
            if (preg_match($pattern, $ua)) {
                return $name;
            }
        }

        return 'Other';
    }

    /**
     * Order matters: Android and ChromeOS also say "Linux", an iPad "Mac OS X".
     */
    private static function os(string $ua): string
    {
        $systems = [
            'iPadOS'   => '/\biPad\b/',
            'iOS'      => '/\b(iPhone|iPod)\b/',
            'Android'  => '/\bAndroid\b/',
            'ChromeOS' => '/\bCrOS\b/',
            'Windows'  => '/\bWindows\b/',
            'macOS'    => '/\bMacintosh\b/',
            'Linux'    => '/\b(Linux|X11)\b/',
        ];

        foreach ($systems as $name => $pattern) {
            if (preg_match($pattern, $ua)) {
                return $name;
            }
        }

        return 'Other';
    }
}
