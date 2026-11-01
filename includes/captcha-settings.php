<?php

namespace LcmtDevMailer;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Email templates → Spam protection: a General tab (site default, unavailable
 * service, blocked messages) and one tab per provider with its settings and
 * status. Each tab is its own settings group, so saving one leaves the
 * options of the others alone.
 */
class CaptchaSettings
{
    public const PAGE_SLUG   = 'lcmt-mailer-spam-protection';
    public const TAB_GENERAL = 'general';
    private const GROUP      = 'lcmt_mailer_captcha_settings';

    public static function addSubmenu(): void
    {
        add_submenu_page(
            'edit.php?post_type=' . PostType::SLUG,
            __('Spam protection', 'lcmt-dev-mailer'),
            __('Spam protection', 'lcmt-dev-mailer'),
            'manage_options',
            self::PAGE_SLUG,
            [self::class, 'renderPage']
        );
    }

    public static function registerSettings(): void
    {
        register_setting(self::GROUP, Captcha::OPTION, [
            'type'              => 'string',
            'sanitize_callback' => [Captcha::class, 'sanitize'],
            'default'           => Captcha::DEFAULT,
        ]);

        register_setting(self::GROUP, Captcha::OPTION_UNAVAILABLE, [
            'type'              => 'string',
            'sanitize_callback' => [Captcha::class, 'sanitizeUnavailable'],
            'default'           => Captcha::REFUSE,
        ]);

        // options.php sends null for an unchecked box.
        register_setting(self::GROUP, Captcha::OPTION_KEEP_BLOCKED, [
            'type'              => 'string',
            'sanitize_callback' => [Captcha::class, 'sanitizeKeepBlocked'],
            'default'           => '1',
        ]);

        register_setting(self::group(Turnstile::id()), Turnstile::OPTION_SITE_KEY, [
            'type'              => 'string',
            'sanitize_callback' => [Turnstile::class, 'sanitizeSiteKey'],
            'default'           => '',
        ]);

        register_setting(self::group(Turnstile::id()), Turnstile::OPTION_SECRET_KEY, [
            'type'              => 'string',
            'sanitize_callback' => [Turnstile::class, 'sanitizeSecret'],
            'default'           => '',
        ]);

        register_setting(self::group(Turnstile::id()), Turnstile::OPTION_APPEARANCE, [
            'type'              => 'string',
            'sanitize_callback' => [Turnstile::class, 'sanitizeAppearance'],
            'default'           => Turnstile::APPEARANCE_INTERACTION,
        ]);
    }

    /**
     * Settings group of a tab: a provider registers its options with
     * group($providerId) so they are saved from its own tab.
     */
    public static function group(string $tab): string
    {
        return $tab === self::TAB_GENERAL ? self::GROUP : self::GROUP . '_' . $tab;
    }

    /**
     * The tab asked for in the URL: "general" or a provider id.
     */
    public static function currentTab(): string
    {
        $tab = isset($_GET['tab']) ? sanitize_key(wp_unslash((string) $_GET['tab'])) : '';

        return isset(Captcha::providers()[$tab]) ? $tab : self::TAB_GENERAL;
    }

    /**
     * Label and color of a provider status, for its tab.
     *
     * @return array{label: string, color: string}
     */
    public static function statusBadge(string $status): array
    {
        switch ($status) {
            case Captcha::STATUS_ACTIVE:
                return ['label' => __('Active', 'lcmt-dev-mailer'), 'color' => '#00a32a'];
            case Captcha::STATUS_UNUSED:
                return ['label' => __('Ready, not used', 'lcmt-dev-mailer'), 'color' => '#50575e'];
            case Captcha::STATUS_UNREADABLE:
                return ['label' => __('Key unreadable', 'lcmt-dev-mailer'), 'color' => '#d63638'];
            default:
                return ['label' => __('Not set up', 'lcmt-dev-mailer'), 'color' => '#dba617'];
        }
    }

    /**
     * URL of a tab ("general" or a provider id).
     */
    public static function tabUrl(string $tab, array $args = []): string
    {
        return self::url($tab === self::TAB_GENERAL ? $args : ['tab' => $tab] + $args);
    }

    public static function url(array $args = []): string
    {
        return add_query_arg(
            $args,
            admin_url('edit.php?post_type=' . PostType::SLUG . '&page=' . self::PAGE_SLUG)
        );
    }

    public static function renderPage(): void
    {
        $tab       = self::currentTab();
        $providers = Captcha::providers();
        $inUse     = Captcha::inUseIds();
        $group     = self::group($tab);
        ?>
        <div class="wrap lcmt-captcha-settings">
            <h1><?php esc_html_e('Spam protection', 'lcmt-dev-mailer'); ?></h1>

            <?php settings_errors(); ?>

            <nav class="nav-tab-wrapper lcmt-captcha-tabs" aria-label="<?= esc_attr__('Spam protection sections', 'lcmt-dev-mailer') ?>">
                <a href="<?= esc_url(self::tabUrl(self::TAB_GENERAL)) ?>" class="nav-tab<?= $tab === self::TAB_GENERAL ? ' nav-tab-active' : '' ?>"<?= $tab === self::TAB_GENERAL ? ' aria-current="page"' : '' ?>>
                    <?php esc_html_e('General', 'lcmt-dev-mailer'); ?>
                </a>
                <?php foreach ($providers as $id => $provider): ?>
                    <?php
                    $badge = self::statusBadge(Captcha::status($id, $inUse));
                    $icon  = is_subclass_of($provider, CaptchaBadge::class) ? $provider::icon() : '';
                    ?>
                    <a href="<?= esc_url(self::tabUrl($id)) ?>" class="nav-tab<?= $tab === $id ? ' nav-tab-active' : '' ?>"<?= $tab === $id ? ' aria-current="page"' : '' ?>>
                        <?= esc_html($provider::label()) ?>
                        <span class="lcmt-tab-status" style="border-color:<?= esc_attr($badge['color']) ?>">
                            <?php if ($icon !== ''): ?>
                                <span class="dashicons <?= esc_attr($icon) ?>" style="color:<?= esc_attr($badge['color']) ?>" aria-hidden="true"></span>
                            <?php endif; ?>
                            <?= esc_html($badge['label']) ?>
                        </span>
                    </a>
                <?php endforeach; ?>
            </nav>

            <?php if (self::hasSettings($group)): ?>
                <?php // options.php sends the visitor back to _wp_http_referer, which keeps ?tab= ?>
                <form method="post" action="options.php">
                    <?php settings_fields($group); ?>
                    <table class="form-table">
                        <?php $tab === self::TAB_GENERAL ? self::renderGeneral() : $providers[$tab]::renderSettings(); ?>
                    </table>
                    <?php submit_button(); ?>
                </form>
            <?php else: ?>
                <?php // Nothing to save on this tab (ALTCHA): no form, options.php would refuse the group ?>
                <table class="form-table">
                    <?php $providers[$tab]::renderSettings(); ?>
                </table>
            <?php endif; ?>
        </div>

        <style>
            .lcmt-captcha-tabs .nav-tab { display: inline-flex; align-items: center; gap: 6px; }
            .lcmt-tab-status { display: inline-flex; align-items: center; gap: 2px; padding: 0 6px; border: 1px solid; border-radius: 10px; background: #fff; color: #1d2327; font-size: 12px; font-weight: 400; line-height: 18px; }
            .lcmt-tab-status .dashicons { font-size: 14px; width: 14px; height: 14px; }
        </style>
        <?php
    }

    /**
     * The rows of the General tab.
     */
    private static function renderGeneral(): void
    {
        $selected    = Captcha::selectedId();
        $unavailable = Captcha::sanitizeUnavailable(get_option(Captcha::OPTION_UNAVAILABLE, Captcha::REFUSE));
        ?>
        <tr>
            <th scope="row">
                <label for="lcmt-captcha"><?php esc_html_e('Site default protection', 'lcmt-dev-mailer'); ?></label>
            </th>
            <td>
                <select id="lcmt-captcha" name="<?= esc_attr(Captcha::OPTION) ?>">
                    <option value="<?= esc_attr(Captcha::NONE) ?>" <?php selected($selected, Captcha::NONE); ?>>
                        <?php esc_html_e('None', 'lcmt-dev-mailer'); ?>
                    </option>
                    <?php foreach (Captcha::providers() as $id => $provider): ?>
                        <option value="<?= esc_attr($id) ?>" <?php selected($selected, $id); ?>>
                            <?= esc_html($provider::label()) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <p class="description"><?php esc_html_e('Each email template can override it with its own Spam protection setting.', 'lcmt-dev-mailer'); ?></p>
                <p class="description" data-lcmt-captcha-none>
                    <?php esc_html_e('Forms accept any submission, including from bots.', 'lcmt-dev-mailer'); ?>
                </p>
            </td>
        </tr>

        <tr>
            <th scope="row"><?php esc_html_e('If the verification service does not answer', 'lcmt-dev-mailer'); ?></th>
            <td>
                <fieldset>
                    <label><input type="radio" name="<?= esc_attr(Captcha::OPTION_UNAVAILABLE) ?>" value="<?= esc_attr(Captcha::REFUSE) ?>" <?php checked($unavailable, Captcha::REFUSE); ?> />
                        <?php esc_html_e('Refuse the message (recommended)', 'lcmt-dev-mailer'); ?></label><br>
                    <label><input type="radio" name="<?= esc_attr(Captcha::OPTION_UNAVAILABLE) ?>" value="<?= esc_attr(Captcha::ACCEPT) ?>" <?php checked($unavailable, Captcha::ACCEPT); ?> />
                        <?php esc_html_e('Accept it', 'lcmt-dev-mailer'); ?></label>
                </fieldset>
            </td>
        </tr>

        <tr>
            <th scope="row"><?php esc_html_e('Blocked messages', 'lcmt-dev-mailer'); ?></th>
            <td>
                <label>
                    <input type="checkbox" name="<?= esc_attr(Captcha::OPTION_KEEP_BLOCKED) ?>" value="1" <?php checked(get_option(Captcha::OPTION_KEEP_BLOCKED, '1'), '1'); ?> />
                    <?php esc_html_e('Keep blocked messages in Received messages → Spam', 'lcmt-dev-mailer'); ?>
                </label>
                <p class="description"><?php esc_html_e('Never emailed. If a real visitor was blocked, open the message, take it out of Spam with "Mark as unread", then use "Send the email again".', 'lcmt-dev-mailer'); ?></p>
            </td>
        </tr>

        <script>
            (function () {
                var select = document.getElementById('lcmt-captcha');
                var none = document.querySelector('[data-lcmt-captcha-none]');

                // Only warn about unprotected forms while "None" is selected
                function toggleNone() {
                    none.style.display = select.value === <?= wp_json_encode(Captcha::NONE) ?> ? '' : 'none';
                }

                select.addEventListener('change', toggleNone);
                toggleNone();
            })();
        </script>
        <?php
    }

    /**
     * Whether a settings group has options to save.
     */
    private static function hasSettings(string $group): bool
    {
        foreach (get_registered_settings() as $setting) {
            if (($setting['group'] ?? '') === $group) return true;
        }

        return false;
    }

    /**
     * Tab of the provider storing an option among its encrypted keys, or the
     * General tab.
     */
    public static function tabForSecret(string $option): string
    {
        foreach (Captcha::providers() as $id => $provider) {
            if (is_subclass_of($provider, CaptchaSecrets::class) && in_array($option, $provider::secretOptions(), true)) {
                return $id;
            }
        }

        return self::TAB_GENERAL;
    }
}
