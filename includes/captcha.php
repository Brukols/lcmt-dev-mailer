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
     * A mail template without its own choice follows the site default.
     */
    public const INHERIT = '';

    /**
     * What to do when the verification service does not answer: refuse the
     * message (default) or accept it.
     */
    public const OPTION_UNAVAILABLE = 'lcmt_mailer_captcha_unavailable';
    public const REFUSE             = 'refuse';
    public const ACCEPT             = 'accept';

    /**
     * Whether blocked messages are kept in Received messages → Spam ('1', default).
     */
    public const OPTION_KEEP_BLOCKED = 'lcmt_mailer_keep_blocked';

    /**
     * Status of a provider on the Spam protection page (Captcha::status()).
     */
    public const STATUS_ACTIVE     = 'active';
    public const STATUS_UNUSED     = 'unused';
    public const STATUS_NOT_SET_UP = 'not-set-up';
    public const STATUS_UNREADABLE = 'unreadable';

    public const PASSED      = 'passed';
    public const FAILED      = 'failed';
    public const UNAVAILABLE = 'unavailable';

    /**
     * isReady() of each provider, kept for the request: a page may resolve
     * many templates, and Turnstile decrypts its secret to answer.
     *
     * @var array<string, bool>
     */
    private static array $ready = [];

    /**
     * Providers whose assets were already enqueued on this page.
     *
     * @var string[]
     */
    private static array $enqueued = [];

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
            Altcha::id()    => Altcha::class,
            Turnstile::id() => Turnstile::class,
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
     * Id of the protection a mail template uses: its own choice when it
     * names "none" or a known provider, else the site default.
     */
    public static function selectedIdFor(int $postId): string
    {
        $choice = (string) get_post_meta($postId, MetaFields::FIELD_CAPTCHA, true);

        if ($choice === self::NONE || isset(self::providers()[$choice])) {
            return $choice;
        }

        return self::selectedId();
    }

    /**
     * The provider protecting a mail template, when it is ready. A template
     * choice that is not ready falls back to the site default's provider,
     * if that one is ready; "none" always means no protection.
     *
     * @return class-string<CaptchaProvider>|null
     */
    public static function activeFor(int $postId): ?string
    {
        $providers = self::providers();
        $id        = self::selectedIdFor($postId);
        $provider  = $providers[$id] ?? null;

        if ($provider && self::isReady($provider)) {
            return $provider;
        }

        if ($id === self::NONE) {
            return null;
        }

        $default = $providers[self::selectedId()] ?? null;

        return $default && self::isReady($default) ? $default : null;
    }

    /**
     * A provider's isReady(), asked once per request.
     *
     * @param class-string<CaptchaProvider> $provider
     */
    public static function isReady(string $provider): bool
    {
        return self::$ready[$provider] ??= $provider::isReady();
    }

    /**
     * Forget the readiness kept for the request (hooked on every option change).
     */
    public static function forgetReady(): void
    {
        self::$ready = [];
    }

    /**
     * Encrypted keys of a provider that can no longer be decrypted, from
     * the raw options: no report, no email.
     *
     * @param class-string<CaptchaProvider> $provider
     * @return array<string, string> Option => label.
     */
    public static function unreadableSecrets(string $provider): array
    {
        if (!is_subclass_of($provider, CaptchaSecrets::class)) return [];

        return array_filter(
            $provider::secretOptions(),
            static fn($option) => SecretBox::isUnreadable((string) $option),
            ARRAY_FILTER_USE_KEY
        );
    }

    /**
     * A form of this template is really used (rendered or submitted): report
     * the unreadable keys of the providers it relies on (its choice and the
     * site default), and clear the reports of the keys that read fine again.
     */
    public static function useSecrets(int $postId): void
    {
        $providers = self::providers();
        $involved  = array_filter([$providers[self::selectedIdFor($postId)] ?? null, $providers[self::selectedId()] ?? null]);

        foreach (array_unique($involved) as $provider) {
            if (!is_subclass_of($provider, CaptchaSecrets::class)) continue;

            $unreadable = self::unreadableSecrets($provider);

            foreach ($provider::secretOptions() as $option => $label) {
                if (isset($unreadable[$option])) {
                    UnreadableSecrets::report($option, $label);
                } elseif (UnreadableSecrets::has($option)) {
                    UnreadableSecrets::clear($option);
                }
            }
        }
    }

    /**
     * Ids of the ready providers that protect something: the site default,
     * and the protection each mail template actually gets (activeFor(), so a
     * choice that is not set up counts as the site default). In provider order.
     *
     * @param int[]|null $postIds Mail templates to look at; null for the published ones.
     * @return string[]
     */
    public static function inUseIds(?array $postIds = null): array
    {
        $providers = self::providers();
        $used      = [];

        $default = $providers[self::selectedId()] ?? null;
        if ($default && self::isReady($default)) {
            $used[] = $default;
        }

        foreach ($postIds ?? self::publishedMailIds() as $postId) {
            $active = self::activeFor((int) $postId);
            if ($active) {
                $used[] = $active;
            }
        }

        return array_keys(array_filter($providers, static fn($class) => in_array($class, $used, true)));
    }

    /**
     * A provider's status: one of its keys can no longer be read, it is not
     * set up, it protects something, or it is ready but used by nothing.
     *
     * @param string[] $inUseIds From inUseIds().
     */
    public static function status(string $id, array $inUseIds): string
    {
        $provider = self::providers()[$id] ?? null;

        if (!$provider) {
            return self::STATUS_NOT_SET_UP;
        }

        // From the raw options: computing a status records and mails nothing
        if (self::unreadableSecrets($provider) !== []) {
            return self::STATUS_UNREADABLE;
        }

        if (!self::isReady($provider)) {
            return self::STATUS_NOT_SET_UP;
        }

        return in_array($id, $inUseIds, true) ? self::STATUS_ACTIVE : self::STATUS_UNUSED;
    }

    /**
     * @return int[]
     */
    private static function publishedMailIds(): array
    {
        $ids = array_map('intval', get_posts([
            'post_type'   => PostType::SLUG,
            'post_status' => 'publish',
            'numberposts' => -1,
            'fields'      => 'ids',
            // Every language: Polylang would keep the current one only
            'lang'        => '',
        ]));

        // One query for the spam protection meta of every template
        update_meta_cache('post', $ids);

        return $ids;
    }

    /**
     * Register the routes of every ready provider: two forms may use two
     * different protections.
     */
    public static function registerRoutes(): void
    {
        foreach (self::providers() as $provider) {
            if (self::isReady($provider)) {
                $provider::registerRoutes();
            }
        }
    }

    public static function widget(int $postId): string
    {
        self::useSecrets($postId);

        $provider = self::activeFor($postId);

        if (!$provider) {
            return '';
        }

        // Scripts are enqueued once per page, the first time the provider renders.
        if (is_subclass_of($provider, CaptchaAssets::class) && !in_array($provider, self::$enqueued, true)) {
            self::$enqueued[] = $provider;
            $provider::enqueueAssets();
        }

        return $provider::widget();
    }

    /**
     * Check a submission against the protection of its mail template.
     *
     * @param array<string, mixed> $body The decoded JSON body of the request.
     * @return string One of self::PASSED, self::FAILED or self::UNAVAILABLE.
     */
    public static function check(int $postId, array $body): string
    {
        self::useSecrets($postId);

        $provider = self::activeFor($postId);

        if (!$provider) {
            return self::PASSED;
        }

        try {
            return $provider::verify($body) ? self::PASSED : self::FAILED;
        } catch (CaptchaUnavailable $e) {
            return self::UNAVAILABLE;
        }
    }

    /**
     * Keep the policy to a known value.
     *
     * @param mixed $value
     */
    public static function sanitizeUnavailable($value): string
    {
        return $value === self::ACCEPT ? self::ACCEPT : self::REFUSE;
    }

    /**
     * @param mixed $value
     */
    public static function sanitizeKeepBlocked($value): string
    {
        return $value === '0' || !$value ? '0' : '1';
    }

    /**
     * Keep a template's choice to "" (site default), "none" or a known provider id.
     *
     * @param mixed $value
     */
    public static function sanitizeForMail($value): string
    {
        $value = sanitize_key((string) $value);

        return $value === self::NONE || isset(self::providers()[$value]) ? $value : self::INHERIT;
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
