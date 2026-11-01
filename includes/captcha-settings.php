<?php

namespace LcmtDevMailer;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Email templates → Spam protection: pick the protection and set up its provider.
 */
class CaptchaSettings
{
    public const PAGE_SLUG = 'lcmt-mailer-spam-protection';
    private const GROUP = 'lcmt_mailer_captcha_settings';

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

        register_setting(self::GROUP, Turnstile::OPTION_SITE_KEY, [
            'type'              => 'string',
            'sanitize_callback' => [Turnstile::class, 'sanitizeSiteKey'],
            'default'           => '',
        ]);

        register_setting(self::GROUP, Turnstile::OPTION_SECRET_KEY, [
            'type'              => 'string',
            'sanitize_callback' => [Turnstile::class, 'sanitizeSecret'],
            'default'           => '',
        ]);

        register_setting(self::GROUP, Turnstile::OPTION_APPEARANCE, [
            'type'              => 'string',
            'sanitize_callback' => [Turnstile::class, 'sanitizeAppearance'],
            'default'           => Turnstile::APPEARANCE_INTERACTION,
        ]);
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
        $selected    = Captcha::selectedId();
        $unavailable = Captcha::sanitizeUnavailable(get_option(Captcha::OPTION_UNAVAILABLE, Captcha::REFUSE));
        ?>
        <div class="wrap lcmt-captcha-settings">
            <h1><?php esc_html_e('Spam protection', 'lcmt-dev-mailer'); ?></h1>

            <?php settings_errors(); ?>

            <form method="post" action="options.php">
                <?php settings_fields(self::GROUP); ?>

                <table class="form-table">
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
                </table>

                <?php // Every provider, whatever the site default: a template may pick any of them ?>
                <?php foreach (Captcha::providers() as $provider): ?>
                    <h2><?= esc_html($provider::label()) ?></h2>
                    <table class="form-table">
                        <?php $provider::renderSettings(); ?>
                    </table>
                <?php endforeach; ?>

                <?php submit_button(); ?>
            </form>
        </div>

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
}
