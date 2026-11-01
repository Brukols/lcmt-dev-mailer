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

    /**
     * The copy buttons of the provider rows, on this screen only.
     */
    public static function enqueue(string $hook): void
    {
        if ($hook !== PostType::SLUG . '_page_' . self::PAGE_SLUG) {
            return;
        }

        wp_enqueue_script(
            'lcmt-admin-captcha-settings',
            LCMT_MAILER_URL . 'assets/dist/admin-captcha-settings.js',
            [],
            lcmt_mailer_asset_version('assets/dist/admin-captcha-settings.js'),
            true
        );
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
                            <label for="lcmt-captcha"><?php esc_html_e('Protection', 'lcmt-dev-mailer'); ?></label>
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
                            <p class="description" data-captcha-provider="<?= esc_attr(Captcha::NONE) ?>">
                                <?php esc_html_e('Forms accept any submission, including from bots.', 'lcmt-dev-mailer'); ?>
                            </p>
                        </td>
                    </tr>

                    <?php foreach (Captcha::providers() as $provider) {
                        $provider::renderSettings();
                    } ?>

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
                            <p class="description"><?php esc_html_e('Never emailed. Mark one as not spam, then resend it, if a real visitor was blocked.', 'lcmt-dev-mailer'); ?></p>
                        </td>
                    </tr>
                </table>

                <?php submit_button(); ?>
            </form>

            <p class="screen-reader-text" role="status" aria-live="polite" data-lcmt-copy-status></p>
        </div>

        <script>
            (function () {
                var select = document.getElementById('lcmt-captcha');

                // Only show the settings of the selected protection
                function toggleRows() {
                    document.querySelectorAll('[data-captcha-provider]').forEach(function (row) {
                        row.style.display = row.getAttribute('data-captcha-provider') === select.value ? '' : 'none';
                    });
                }

                select.addEventListener('change', toggleRows);
                toggleRows();
            })();
        </script>
        <?php
    }
}
