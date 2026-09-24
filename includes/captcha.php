<?php

namespace LcmtDevMailer;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Picks the spam protection selected in the settings and routes calls to it.
 */
class Captcha
{
    public const OPTION = 'lcmt_mailer_captcha';
    public const NONE   = 'none';

    /**
     * Protection used until the setting is saved once.
     */
    public const DEFAULT = 'altcha';

    /**
     * Available providers, keyed by id.
     *
     * @return array<string, class-string<CaptchaProvider>>
     */
    public static function providers(): array
    {
        $providers = (array) apply_filters('lcmt_mailer_captcha_providers', [
            Altcha::id() => Altcha::class,
        ]);

        return array_filter(
            $providers,
            static fn($class) => is_string($class) && is_subclass_of($class, CaptchaProvider::class)
        );
    }

    /**
     * Id of the selected provider, or self::NONE.
     */
    public static function selectedId(): string
    {
        $id = (string) get_option(self::OPTION, self::DEFAULT);

        return isset(self::providers()[$id]) ? $id : self::NONE;
    }

    /**
     * The selected provider, when it is ready to protect forms.
     *
     * @return class-string<CaptchaProvider>|null
     */
    public static function active(): ?string
    {
        $provider = self::providers()[self::selectedId()] ?? null;

        return $provider && $provider::isReady() ? $provider : null;
    }

    public static function registerRoutes(): void
    {
        $provider = self::active();

        if ($provider) {
            $provider::registerRoutes();
        }
    }

    public static function widget(): string
    {
        $provider = self::active();

        return $provider ? $provider::widget() : '';
    }

    /**
     * @param array<string, mixed> $body The decoded JSON body of the request.
     */
    public static function verify(array $body): bool
    {
        $provider = self::active();

        return !$provider || $provider::verify($body);
    }

    /**
     * Keep the stored value to a known provider id, or self::NONE.
     *
     * @param mixed $value
     */
    public static function sanitize($value): string
    {
        $value = sanitize_key((string) $value);

        return isset(self::providers()[$value]) ? $value : self::NONE;
    }
}
