<?php

namespace LcmtDevMailer;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * The language a form was sent in, on a multilingual site.
 *
 * The front-end script adds the page language to the REST URL as `lang`,
 * which Polylang (3.8+) reads to set the language of the request. Without
 * Polylang nothing here does anything: no language, no locale switch.
 */
class RequestLanguage
{
    /**
     * The language set by using(), wrapped so that "no language" (null) and
     * "not set" can be told apart.
     *
     * @var array{slug: ?string}|null
     */
    private static ?array $override = null;

    /**
     * The locale of a site language, or null for a slug the site does not have.
     *
     * @param array<string, string> $languages Slug => locale.
     */
    public static function locale(?string $requested, array $languages): ?string
    {
        if ($requested === null || $requested === '' || !isset($languages[$requested])) {
            return null;
        }

        $locale = (string) $languages[$requested];

        return $locale === '' ? null : $locale;
    }

    /**
     * Switch the locale to the requested language for the messages of this
     * request. Only a language of the site is accepted, so a tampered `lang`
     * never loads an arbitrary locale.
     *
     * @return bool Whether the locale was switched (restore_current_locale() then).
     */
    public static function switchFor(\WP_REST_Request $request): bool
    {
        if (!function_exists('pll_languages_list')) {
            return false;
        }

        $lang   = $request->get_param('lang');
        $locale = self::locale(is_string($lang) ? $lang : null, self::locales());

        if ($locale === null || $locale === determine_locale()) {
            return false;
        }

        return switch_to_locale($locale);
    }

    /**
     * The submission language slug (Polylang sets it from `lang` for REST
     * requests), or null on a single-language site.
     */
    public static function current(): ?string
    {
        if (self::$override !== null) {
            return self::$override['slug'];
        }

        if (!function_exists('pll_current_language')) {
            return null;
        }

        $slug = pll_current_language();

        return is_string($slug) && $slug !== '' ? $slug : null;
    }

    /**
     * Run a callback with another submission language, e.g. to resend a
     * received message in the language it was sent in, from the admin.
     *
     * @template T
     * @param callable(): T $callback
     * @return T
     */
    public static function using(?string $slug, callable $callback)
    {
        $previous       = self::$override;
        self::$override = ['slug' => $slug === '' ? null : $slug];

        try {
            return $callback();
        } finally {
            self::$override = $previous;
        }
    }

    /**
     * The name of a site language ("English"), or '' when unknown.
     */
    public static function name(?string $slug): string
    {
        if ($slug === null || $slug === '' || !function_exists('pll_languages_list')) {
            return '';
        }

        return self::names()[$slug] ?? '';
    }

    /**
     * Whether the site has more than one language.
     */
    public static function isMultilingual(): bool
    {
        return count(self::names()) > 1;
    }

    /**
     * @return array<string, string> Slug => locale.
     */
    private static function locales(): array
    {
        return self::combine((array) pll_languages_list(['fields' => 'locale']));
    }

    /**
     * @return array<string, string> Slug => name.
     */
    private static function names(): array
    {
        if (!function_exists('pll_languages_list')) {
            return [];
        }

        return self::combine((array) pll_languages_list(['fields' => 'name']));
    }

    /**
     * @param list<string> $values One per language, in the order of the slugs.
     * @return array<string, string>
     */
    private static function combine(array $values): array
    {
        $slugs = (array) pll_languages_list(['fields' => 'slug']);

        if (count($slugs) !== count($values)) {
            return [];
        }

        return array_combine(array_map('strval', $slugs), array_map('strval', $values));
    }
}
