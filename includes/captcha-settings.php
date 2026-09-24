<?php

namespace LcmtDevMailer;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Mails → Spam protection: pick the protection and set up its provider.
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
        $selected = Captcha::selectedId();
        ?>
        <div class="wrap">
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
                </table>

                <?php submit_button(); ?>
            </form>
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
