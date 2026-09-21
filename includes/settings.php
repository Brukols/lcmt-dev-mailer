<?php

namespace LcmtDevMailer;

if (!defined('ABSPATH')) {
    exit;
}

class Settings
{
    public const OPTION_LOGO = 'lcmt_mailer_logo';
    public const OPTION_SUCCESS_MESSAGE = 'lcmt_mailer_success_message';
    public const OPTION_COLOR_SUCCESS = 'lcmt_mailer_color_success';
    public const OPTION_COLOR_ERROR = 'lcmt_mailer_color_error';
    public const OPTION_TEXT_SUCCESS = 'lcmt_mailer_text_success';
    public const OPTION_TEXT_ERROR = 'lcmt_mailer_text_error';
    public const OPTION_FROM_EMAIL = 'lcmt_mailer_from_email';
    public const PAGE_SLUG = 'lcmt-mailer-settings';

    public const DEFAULT_COLOR_SUCCESS = '#38a169';
    public const DEFAULT_COLOR_ERROR = '#e53e3e';

    public static function enqueueAdminAssets(string $hook): void
    {
        if ($hook !== PostType::SLUG . '_page_' . self::PAGE_SLUG) {
            return;
        }

        wp_enqueue_media();
        wp_enqueue_style('wp-color-picker');
        wp_enqueue_script('wp-color-picker');
    }

    public static function addSubmenu(): void
    {
        add_submenu_page(
            'edit.php?post_type=' . PostType::SLUG,
            __('Mailer Settings', 'lcmt-dev-mailer'),
            __('Settings', 'lcmt-dev-mailer'),
            'manage_options',
            self::PAGE_SLUG,
            [self::class, 'renderPage']
        );
    }

    public static function registerSettings(): void
    {
        register_setting('lcmt_mailer_settings', self::OPTION_LOGO, [
            'type'              => 'integer',
            'sanitize_callback' => 'absint',
            'default'           => 0,
        ]);

        register_setting('lcmt_mailer_settings', self::OPTION_SUCCESS_MESSAGE, [
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => '',
        ]);

        register_setting('lcmt_mailer_settings', self::OPTION_COLOR_SUCCESS, [
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_hex_color',
            'default'           => self::DEFAULT_COLOR_SUCCESS,
        ]);

        register_setting('lcmt_mailer_settings', self::OPTION_COLOR_ERROR, [
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_hex_color',
            'default'           => self::DEFAULT_COLOR_ERROR,
        ]);

        // Empty is meaningful here: it means "work the text color out from the
        // background" rather than "fall back to a default".
        register_setting('lcmt_mailer_settings', self::OPTION_TEXT_SUCCESS, [
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_hex_color',
            'default'           => '',
        ]);

        register_setting('lcmt_mailer_settings', self::OPTION_TEXT_ERROR, [
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_hex_color',
            'default'           => '',
        ]);

        // Empty means "use the no-reply address derived from the site host".
        register_setting('lcmt_mailer_settings', self::OPTION_FROM_EMAIL, [
            'type'              => 'string',
            'sanitize_callback' => [self::class, 'sanitizeFromEmail'],
            'default'           => '',
        ]);
    }

    /**
     * Keep the stored sender address to a valid mailbox, or empty.
     *
     * A malformed address would be rejected by the MTA and silently lose every
     * form submission, so anything that is not a valid address is turned back
     * into "empty", which falls through to the derived default.
     *
     * @param mixed $value
     */
    public static function sanitizeFromEmail($value): string
    {
        $email = sanitize_email((string) $value);

        if ($email === '' || !is_email($email)) {
            return '';
        }

        return $email;
    }

    /**
     * The address emails are sent from.
     *
     * Defaults to no-reply@ the current site host, so the address follows the
     * environment instead of being frozen to one domain.
     */
    public static function getFromEmail(): string
    {
        $configured = self::sanitizeFromEmail(get_option(self::OPTION_FROM_EMAIL, ''));
        $email      = $configured !== '' ? $configured : self::getDefaultFromEmail();

        return (string) apply_filters('lcmt_mailer_from_email', $email);
    }

    /**
     * The no-reply address derived from the site host, used when none is set.
     */
    public static function getDefaultFromEmail(): string
    {
        $host = (string) parse_url(home_url(), PHP_URL_HOST);
        $host = preg_replace('/^www\./', '', $host);

        return 'no-reply@' . $host;
    }

    /**
     * The message returned to the browser after a successful submission.
     *
     * Falls back to the translated default when the site has not set one.
     */
    public static function getSuccessMessage(): string
    {
        $message = trim((string) get_option(self::OPTION_SUCCESS_MESSAGE, ''));

        if ($message === '') {
            $message = __('Email sent successfully.', 'lcmt-dev-mailer');
        }

        return (string) apply_filters('lcmt_mailer_success_message', $message);
    }

    /**
     * Alert background colors passed to the frontend snackbar.
     *
     * @return array{success: string, error: string}
     */
    public static function getAlertColors(): array
    {
        $colors = [
            'success' => sanitize_hex_color((string) get_option(self::OPTION_COLOR_SUCCESS, '')) ?: self::DEFAULT_COLOR_SUCCESS,
            'error'   => sanitize_hex_color((string) get_option(self::OPTION_COLOR_ERROR, '')) ?: self::DEFAULT_COLOR_ERROR,
        ];

        return (array) apply_filters('lcmt_mailer_alert_colors', $colors);
    }

    /**
     * Explicit alert text colors, or an empty string to derive one.
     *
     * An empty value is passed through as-is so the frontend can compute the
     * most legible text color for the configured background.
     *
     * @return array{success: string, error: string}
     */
    public static function getAlertTextColors(): array
    {
        $colors = [
            'success' => (string) sanitize_hex_color((string) get_option(self::OPTION_TEXT_SUCCESS, '')),
            'error'   => (string) sanitize_hex_color((string) get_option(self::OPTION_TEXT_ERROR, '')),
        ];

        return (array) apply_filters('lcmt_mailer_alert_text_colors', $colors);
    }

    public static function renderPage(): void
    {
        $logoId  = (int) get_option(self::OPTION_LOGO, 0);
        $logoUrl = $logoId ? wp_get_attachment_image_url($logoId, 'medium') : '';

        $successMessage = (string) get_option(self::OPTION_SUCCESS_MESSAGE, '');
        $colorSuccess   = sanitize_hex_color((string) get_option(self::OPTION_COLOR_SUCCESS, '')) ?: self::DEFAULT_COLOR_SUCCESS;
        $colorError     = sanitize_hex_color((string) get_option(self::OPTION_COLOR_ERROR, '')) ?: self::DEFAULT_COLOR_ERROR;
        $textSuccess    = (string) sanitize_hex_color((string) get_option(self::OPTION_TEXT_SUCCESS, ''));
        $textError      = (string) sanitize_hex_color((string) get_option(self::OPTION_TEXT_ERROR, ''));
        $fromEmail      = self::sanitizeFromEmail(get_option(self::OPTION_FROM_EMAIL, ''));
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Mailer Settings', 'lcmt-dev-mailer'); ?></h1>

            <form method="post" action="options.php">
                <?php settings_fields('lcmt_mailer_settings'); ?>

                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="lcmt-from-email"><?php esc_html_e('Sender address', 'lcmt-dev-mailer'); ?></label>
                        </th>
                        <td>
                            <input type="email"
                                   id="lcmt-from-email"
                                   name="<?= self::OPTION_FROM_EMAIL ?>"
                                   value="<?= esc_attr($fromEmail) ?>"
                                   class="regular-text"
                                   placeholder="<?= esc_attr(self::getDefaultFromEmail()) ?>" />
                            <p class="description">
                                <?php
                                printf(
                                    /* translators: %s: the default sender address, e.g. no-reply@example.com */
                                    esc_html__('The From address of every email the plugin sends. Leave empty to use %s, which follows the site host across environments.', 'lcmt-dev-mailer'),
                                    '<code>' . esc_html(self::getDefaultFromEmail()) . '</code>'
                                );
                                ?>
                            </p>
                            <p class="description">
                                <?php esc_html_e('Use an address on a domain this site is allowed to send for. An address on a domain you do not control (a Gmail or Outlook mailbox, for instance) fails SPF and DKIM checks, and the messages are dropped as spoofing.', 'lcmt-dev-mailer'); ?>
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label><?php esc_html_e('Email logo', 'lcmt-dev-mailer'); ?></label>
                        </th>
                        <td>
                            <div id="lcmt-logo-preview" style="margin-bottom: 10px;">
                                <?php if ($logoUrl): ?>
                                    <img src="<?= esc_url($logoUrl) ?>" style="max-width: 200px; height: auto;" />
                                <?php endif; ?>
                            </div>

                            <input type="hidden" id="lcmt-logo-id" name="<?= self::OPTION_LOGO ?>" value="<?= esc_attr($logoId) ?>" />

                            <button type="button" id="lcmt-logo-upload" class="button">
                                <?php esc_html_e('Select image', 'lcmt-dev-mailer'); ?>
                            </button>
                            <button type="button" id="lcmt-logo-remove" class="button" <?= $logoId ? '' : 'style="display:none;"' ?>>
                                <?php esc_html_e('Remove', 'lcmt-dev-mailer'); ?>
                            </button>

                            <p class="description">
                                <?php esc_html_e('This logo appears at the top of all emails sent by the plugin.', 'lcmt-dev-mailer'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="lcmt-success-message"><?php esc_html_e('Success message', 'lcmt-dev-mailer'); ?></label>
                        </th>
                        <td>
                            <input type="text"
                                   id="lcmt-success-message"
                                   name="<?= self::OPTION_SUCCESS_MESSAGE ?>"
                                   value="<?= esc_attr($successMessage) ?>"
                                   class="large-text"
                                   placeholder="<?= esc_attr__('Email sent successfully.', 'lcmt-dev-mailer') ?>" />
                            <p class="description">
                                <?php esc_html_e('Shown to the visitor after a successful submission. Leave empty to use the default.', 'lcmt-dev-mailer'); ?>
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="lcmt-color-success"><?php esc_html_e('Success color', 'lcmt-dev-mailer'); ?></label>
                        </th>
                        <td>
                            <input type="text"
                                   id="lcmt-color-success"
                                   class="lcmt-color-field"
                                   name="<?= self::OPTION_COLOR_SUCCESS ?>"
                                   value="<?= esc_attr($colorSuccess) ?>"
                                   data-default-color="<?= esc_attr(self::DEFAULT_COLOR_SUCCESS) ?>" />
                            <p class="description">
                                <?php esc_html_e('Background of the confirmation alert.', 'lcmt-dev-mailer'); ?>
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="lcmt-text-success"><?php esc_html_e('Success text color', 'lcmt-dev-mailer'); ?></label>
                        </th>
                        <td>
                            <input type="text"
                                   id="lcmt-text-success"
                                   class="lcmt-color-field"
                                   data-alt-for="lcmt-color-success"
                                   name="<?= self::OPTION_TEXT_SUCCESS ?>"
                                   value="<?= esc_attr($textSuccess) ?>" />
                            <p class="description">
                                <?php esc_html_e('Leave empty to pick automatically whichever of black or white reads best on the background.', 'lcmt-dev-mailer'); ?>
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="lcmt-color-error"><?php esc_html_e('Error color', 'lcmt-dev-mailer'); ?></label>
                        </th>
                        <td>
                            <input type="text"
                                   id="lcmt-color-error"
                                   class="lcmt-color-field"
                                   name="<?= self::OPTION_COLOR_ERROR ?>"
                                   value="<?= esc_attr($colorError) ?>"
                                   data-default-color="<?= esc_attr(self::DEFAULT_COLOR_ERROR) ?>" />
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="lcmt-text-error"><?php esc_html_e('Error text color', 'lcmt-dev-mailer'); ?></label>
                        </th>
                        <td>
                            <input type="text"
                                   id="lcmt-text-error"
                                   class="lcmt-color-field"
                                   data-alt-for="lcmt-color-error"
                                   name="<?= self::OPTION_TEXT_ERROR ?>"
                                   value="<?= esc_attr($textError) ?>" />
                            <p class="description">
                                <?php esc_html_e('Leave empty to pick automatically.', 'lcmt-dev-mailer'); ?>
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><?php esc_html_e('Preview', 'lcmt-dev-mailer'); ?></th>
                        <td>
                            <div id="lcmt-alert-preview" class="lcmt-alert-preview">
                                <div class="lcmt-alert-preview__chip" data-preview="success"
                                     data-bg="lcmt-color-success" data-fg="lcmt-text-success">
                                    <span class="lcmt-alert-preview__text"></span>
                                </div>
                                <p class="description" data-contrast="success"></p>

                                <div class="lcmt-alert-preview__chip" data-preview="error"
                                     data-bg="lcmt-color-error" data-fg="lcmt-text-error">
                                    <span class="lcmt-alert-preview__text"></span>
                                </div>
                                <p class="description" data-contrast="error"></p>
                            </div>
                            <p class="description">
                                <?php esc_html_e('WCAG 1.4.3 asks for at least 4.5:1 between the text and its background.', 'lcmt-dev-mailer'); ?>
                            </p>
                        </td>
                    </tr>
                </table>

                <style>
                    .lcmt-alert-preview__chip {
                        border-radius: 4px;
                        padding: 14px 18px;
                        margin-bottom: 6px;
                        max-width: 420px;
                        font-size: 14px;
                    }
                    .lcmt-alert-preview .description[data-contrast] {
                        margin: 0 0 14px;
                    }
                </style>

                <?php submit_button(); ?>
            </form>
        </div>

        <script>
            jQuery(function ($) {
                var SAMPLE = {
                    success: <?= wp_json_encode(__('Email sent successfully.', 'lcmt-dev-mailer')) ?>,
                    error: <?= wp_json_encode(__('An error occurred.', 'lcmt-dev-mailer')) ?>
                };

                function channels(hex) {
                    var value = String(hex || '').replace('#', '');

                    if (value.length === 3) {
                        value = value[0] + value[0] + value[1] + value[1] + value[2] + value[2];
                    }

                    if (!/^[0-9a-f]{6}$/i.test(value)) return null;

                    var n = parseInt(value, 16);
                    return [(n >> 16) & 255, (n >> 8) & 255, n & 255];
                }

                function luminance(hex) {
                    var rgb = channels(hex);
                    if (!rgb) return null;

                    var linear = rgb.map(function (channel) {
                        var c = channel / 255;
                        return c <= 0.03928 ? c / 12.92 : Math.pow((c + 0.055) / 1.055, 2.4);
                    });

                    return 0.2126 * linear[0] + 0.7152 * linear[1] + 0.0722 * linear[2];
                }

                function contrast(a, b) {
                    var la = luminance(a);
                    var lb = luminance(b);
                    if (la === null || lb === null) return null;

                    return (Math.max(la, lb) + 0.05) / (Math.min(la, lb) + 0.05);
                }

                function readable(background) {
                    var l = luminance(background);
                    if (l === null) return '#ffffff';

                    return 1.05 / (l + 0.05) >= (l + 0.05) / 0.05 ? '#ffffff' : '#000000';
                }

                function updatePreview() {
                    $('#lcmt-alert-preview [data-preview]').each(function () {
                        var chip = $(this);
                        var type = chip.data('preview');
                        var background = $('#' + chip.data('bg')).val() || '#ffffff';
                        var chosen = $('#' + chip.data('fg')).val();
                        var foreground = chosen || readable(background);

                        var text = SAMPLE[type];
                        if (type === 'success') {
                            text = $('#lcmt-success-message').val() || SAMPLE.success;
                        }

                        chip.css({ backgroundColor: background, color: foreground });
                        chip.find('.lcmt-alert-preview__text').text(text);

                        var ratio = contrast(background, foreground);
                        var label = ratio === null
                            ? ''
                            : ratio.toFixed(2) + ':1 \u00b7 ' + (ratio >= 4.5 ? 'AA \u2713' : 'AA \u2717')
                                + (chosen ? '' : ' \u00b7 auto');

                        $('[data-contrast="' + type + '"]').text(label);
                    });
                }

                // wpColorPicker fires change before the input value is updated.
                function deferredUpdate() {
                    window.setTimeout(updatePreview, 0);
                }

                $('.lcmt-color-field').wpColorPicker({
                    change: deferredUpdate,
                    clear: deferredUpdate
                });

                $('#lcmt-success-message').on('input', updatePreview);

                updatePreview();
            });

            (function () {
                var frame;
                var uploadBtn = document.getElementById('lcmt-logo-upload');
                var removeBtn = document.getElementById('lcmt-logo-remove');
                var preview   = document.getElementById('lcmt-logo-preview');
                var input     = document.getElementById('lcmt-logo-id');

                uploadBtn.addEventListener('click', function (e) {
                    e.preventDefault();

                    if (frame) { frame.open(); return; }

                    frame = wp.media({
                        title: '<?php echo esc_js(__('Select email logo', 'lcmt-dev-mailer')); ?>',
                        button: { text: '<?php echo esc_js(__('Use this image', 'lcmt-dev-mailer')); ?>' },
                        multiple: false,
                        library: { type: 'image' }
                    });

                    frame.on('select', function () {
                        var attachment = frame.state().get('selection').first().toJSON();
                        var url = attachment.sizes && attachment.sizes.medium
                            ? attachment.sizes.medium.url
                            : attachment.url;

                        input.value = attachment.id;
                        preview.innerHTML = '<img src="' + url + '" style="max-width: 200px; height: auto;" />';
                        removeBtn.style.display = '';
                    });

                    frame.open();
                });

                removeBtn.addEventListener('click', function (e) {
                    e.preventDefault();
                    input.value = '0';
                    preview.innerHTML = '';
                    removeBtn.style.display = 'none';
                });
            })();
        </script>
        <?php
    }

    /**
     * Get the logo URL for use in email templates.
     */
    public static function getLogoUrl(): string
    {
        $logoId = (int) get_option(self::OPTION_LOGO, 0);

        if (!$logoId) {
            return '';
        }

        return wp_get_attachment_image_url($logoId, 'medium') ?: '';
    }
}
