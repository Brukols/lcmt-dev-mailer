<?php

namespace LcmtDevMailer;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Cloudflare Turnstile.
 *
 * Each key comes from its wp-config.php constant when defined, otherwise from
 * an option. The secret key is kept encrypted in the database (see SecretBox);
 * the site key is public and stored as it is.
 */
class Turnstile implements CaptchaProvider, CaptchaAssets
{
    public const CONST_SITE_KEY   = 'LCMT_MAILER_TURNSTILE_SITE_KEY';
    public const CONST_SECRET_KEY = 'LCMT_MAILER_TURNSTILE_SECRET_KEY';

    public const OPTION_SITE_KEY   = 'lcmt_mailer_turnstile_site_key';
    public const OPTION_SECRET_KEY = 'lcmt_mailer_turnstile_secret_key';
    public const OPTION_APPEARANCE = 'lcmt_mailer_turnstile_appearance';

    public const APPEARANCE_INTERACTION = 'interaction-only';
    public const APPEARANCE_ALWAYS      = 'always';

    /**
     * Name of the body key carrying the token.
     */
    public const FIELD = 'turnstile';

    public const ACTION = 'lcmt_form';

    public const TIMEOUT_SECONDS = 5;

    public const STATUS_TRANSIENT = 'lcmt_mailer_turnstile_status';

    private const VERIFY_URL    = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';
    private const FORGET_FIELD  = 'lcmt_turnstile_forget_secret';
    private const KEY_CHECK     = 'lcmt-key-check';

    public const STATUS_CONNECTED   = 'connected';
    public const STATUS_INVALID     = 'invalid';
    public const STATUS_UNREACHABLE = 'unreachable';

    public static function id(): string
    {
        return 'turnstile';
    }

    public static function label(): string
    {
        return 'Cloudflare Turnstile';
    }

    /**
     * Forget the cached key check whenever a key changes, and keep the keys
     * out of autoload: they are only read on pages that render a form.
     */
    public static function registerHooks(): void
    {
        foreach ([self::OPTION_SITE_KEY, self::OPTION_SECRET_KEY] as $option) {
            add_action('update_option_' . $option, [self::class, 'forgetStatus']);
            add_action('add_option_' . $option, [self::class, 'forgetStatus']);
        }

        foreach ([self::OPTION_SITE_KEY, self::OPTION_SECRET_KEY, self::OPTION_APPEARANCE] as $option) {
            add_action('add_option_' . $option, static function () use ($option) {
                self::keepOutOfAutoload($option);
            });
        }
    }

    public static function forgetStatus(): void
    {
        delete_transient(self::STATUS_TRANSIENT);
    }

    /**
     * Where a key comes from: "constant", "database", "none", or for the
     * secret only "unreadable" (stored, but it can no longer be decrypted).
     *
     * @param string $which "site" or "secret".
     */
    public static function keySource(string $which): string
    {
        $secret   = $which === 'secret';
        $constant = $secret ? self::CONST_SECRET_KEY : self::CONST_SITE_KEY;

        if (defined($constant) && constant($constant) !== '') return 'constant';

        // The raw option: reading the secret through SecretBox::readOption()
        // would send the "unreadable" email from the settings page.
        $stored = (string) get_option($secret ? self::OPTION_SECRET_KEY : self::OPTION_SITE_KEY, '');
        if ($stored === '') return 'none';

        if ($secret && SecretBox::isEncrypted($stored) && SecretBox::decrypt($stored) === null) return 'unreadable';

        return 'database';
    }

    public static function siteKey(): string
    {
        if (self::keySource('site') === 'constant') {
            return (string) constant(self::CONST_SITE_KEY);
        }

        return (string) get_option(self::OPTION_SITE_KEY, '');
    }

    private static function secretKey(): string
    {
        if (self::keySource('secret') === 'constant') {
            return (string) constant(self::CONST_SECRET_KEY);
        }

        return SecretBox::readOption(self::OPTION_SECRET_KEY, __('Cloudflare Turnstile secret key', 'lcmt-dev-mailer'));
    }

    public static function isReady(): bool
    {
        return self::siteKey() !== '' && self::secretKey() !== '';
    }

    public static function registerRoutes(): void
    {
        // Turnstile talks to Cloudflare only, no route of ours
    }

    public static function widget(): string
    {
        return '<div data-lcmt-captcha="turnstile"'
            . ' data-sitekey="' . esc_attr(self::siteKey()) . '"'
            . ' data-action="' . esc_attr(self::ACTION) . '"'
            . ' data-appearance="' . esc_attr(self::appearance()) . '"></div>';
    }

    public static function enqueueAssets(): void
    {
        wp_enqueue_script(
            'lcmt-captcha-turnstile',
            LCMT_MAILER_URL . 'assets/dist/captcha-turnstile.js',
            [],
            lcmt_mailer_asset_version('assets/dist/captcha-turnstile.js'),
            ['in_footer' => true, 'strategy' => 'defer']
        );
    }

    public static function appearance(): string
    {
        return self::sanitizeAppearance(get_option(self::OPTION_APPEARANCE, self::APPEARANCE_INTERACTION));
    }

    /**
     * @param mixed $value
     */
    public static function sanitizeAppearance($value): string
    {
        return $value === self::APPEARANCE_ALWAYS ? self::APPEARANCE_ALWAYS : self::APPEARANCE_INTERACTION;
    }

    /**
     * Site keys are letters, digits, "_" and "-".
     *
     * @param mixed $value
     */
    public static function sanitizeSiteKey($value): string
    {
        return preg_replace('/[^A-Za-z0-9_\-]/', '', (string) $value);
    }

    /**
     * Sanitize callback of the secret key setting: returns the value to store.
     *
     * @param mixed $value What the form sent; empty keeps the stored secret.
     */
    public static function sanitizeSecret($value): string
    {
        $stored = (string) get_option(self::OPTION_SECRET_KEY, '');

        if (!empty($_POST[self::FORGET_FIELD])) {
            UnreadableSecrets::clear(self::OPTION_SECRET_KEY);

            return '';
        }

        $value = trim((string) $value);
        if ($value === '') return $stored;

        // WordPress sanitizes twice when the option does not exist yet
        // (update_option, then add_option): do not encrypt the result again.
        if (SecretBox::isEncrypted($value)) return $value;

        UnreadableSecrets::clear(self::OPTION_SECRET_KEY);

        return SecretBox::seal($value);
    }

    public static function verify(array $body): bool
    {
        $token = $body[self::FIELD] ?? '';
        if (!is_string($token) || $token === '') return false;

        $response = self::siteverify($token);

        return self::parseVerification($response, (string) wp_parse_url(home_url(), PHP_URL_HOST));
    }

    /**
     * Turn a siteverify HTTP response into a verdict.
     *
     * @param array|\WP_Error $response Result of wp_remote_post().
     * @throws CaptchaUnavailable When Cloudflare gave no usable answer.
     */
    public static function parseVerification($response, string $expectedHost): bool
    {
        $data = self::decodeResponse($response);

        if (in_array('internal-error', (array) ($data['error-codes'] ?? []), true)) {
            throw new CaptchaUnavailable('Turnstile reported an internal error.');
        }

        return ($data['success'] ?? false) === true
            && ($data['action'] ?? null) === self::ACTION
            && ($data['hostname'] ?? null) === $expectedHost;
    }

    /**
     * Ask Cloudflare whether it accepts the configured keys, using a response
     * token that cannot be valid: a wrong secret is refused first, a good one
     * only complains about the token. Cached for a day, forgotten when a key
     * changes; call it on the settings page only.
     */
    public static function checkKeys(): string
    {
        $cached = get_transient(self::STATUS_TRANSIENT);
        if (is_string($cached) && $cached !== '') return $cached;

        try {
            $data   = self::decodeResponse(self::siteverify(self::KEY_CHECK));
            $errors = (array) ($data['error-codes'] ?? []);

            if (in_array('invalid-input-secret', $errors, true)) {
                $status = self::STATUS_INVALID;
            } elseif (in_array('invalid-input-response', $errors, true) || ($data['success'] ?? false) === true) {
                $status = self::STATUS_CONNECTED;
            } else {
                $status = self::STATUS_UNREACHABLE;
            }
        } catch (CaptchaUnavailable $e) {
            $status = self::STATUS_UNREACHABLE;
        }

        // An outage should not be remembered for a day
        set_transient(self::STATUS_TRANSIENT, $status, $status === self::STATUS_UNREACHABLE ? 5 * MINUTE_IN_SECONDS : DAY_IN_SECONDS);

        return $status;
    }

    public static function constantLine(string $constant): string
    {
        $placeholder = $constant === self::CONST_SITE_KEY ? 'your-site-key' : 'your-secret-key';

        return "define( '" . $constant . "', '" . $placeholder . "' );";
    }

    public static function renderSettings(): void
    {
        $siteSource   = self::keySource('site');
        $secretSource = self::keySource('secret');
        $ready        = $siteSource !== 'none' && in_array($secretSource, ['constant', 'database'], true);
        $appearance   = self::appearance();
        $provider     = esc_attr(self::id());
        ?>
        <tr data-captcha-provider="<?= $provider ?>">
            <th scope="row"><?php esc_html_e('Cloudflare setup', 'lcmt-dev-mailer'); ?></th>
            <td>
                <p>
                    <?php
                    printf(
                        /* translators: %s: link to the Cloudflare dashboard */
                        esc_html__('Create a widget in the Cloudflare dashboard (%s), list the hostnames of this site (production and staging), then copy both keys here.', 'lcmt-dev-mailer'),
                        '<a href="https://dash.cloudflare.com/?to=/:account/turnstile" target="_blank" rel="noopener noreferrer">' . esc_html__('Turnstile, Add widget', 'lcmt-dev-mailer') . '</a>'
                    );
                    ?>
                </p>
            </td>
        </tr>
        <tr data-captcha-provider="<?= $provider ?>">
            <th scope="row"><label for="lcmt-turnstile-site-key"><?php esc_html_e('Site key', 'lcmt-dev-mailer'); ?></label></th>
            <td>
                <?php if ($siteSource === 'constant'): ?>
                    <p>
                        <?php
                        printf(
                            /* translators: 1: constant name, 2: file name */
                            esc_html__('Set by the %1$s constant in %2$s.', 'lcmt-dev-mailer'),
                            '<code>' . esc_html(self::CONST_SITE_KEY) . '</code>',
                            '<code>wp-config.php</code>'
                        );
                        ?>
                    </p>
                <?php else: ?>
                    <input type="text" id="lcmt-turnstile-site-key" class="regular-text code"
                           name="<?= esc_attr(self::OPTION_SITE_KEY) ?>"
                           value="<?= esc_attr((string) get_option(self::OPTION_SITE_KEY, '')) ?>"
                           autocomplete="off" spellcheck="false">
                <?php endif; ?>
            </td>
        </tr>
        <tr data-captcha-provider="<?= $provider ?>">
            <th scope="row"><label for="lcmt-turnstile-secret-key"><?php esc_html_e('Secret key', 'lcmt-dev-mailer'); ?></label></th>
            <td>
                <?php if ($secretSource === 'constant'): ?>
                    <p>
                        <?php
                        printf(
                            /* translators: 1: constant name, 2: file name */
                            esc_html__('Set by the %1$s constant in %2$s.', 'lcmt-dev-mailer'),
                            '<code>' . esc_html(self::CONST_SECRET_KEY) . '</code>',
                            '<code>wp-config.php</code>'
                        );
                        ?>
                    </p>
                <?php else: ?>
                    <input type="password" id="lcmt-turnstile-secret-key" class="regular-text code"
                           name="<?= esc_attr(self::OPTION_SECRET_KEY) ?>" value=""
                           autocomplete="off" spellcheck="false">

                    <?php if ($secretSource === 'unreadable'): ?>
                        <p class="description">
                            <?php esc_html_e('The saved key can no longer be read (WordPress security keys changed or database copied from another site). Enter it again.', 'lcmt-dev-mailer'); ?>
                        </p>
                    <?php endif; ?>

                    <?php if ($secretSource !== 'none'): ?>
                        <?php if ($secretSource === 'database'): ?>
                            <p class="description"><?php esc_html_e('Saved in the database. Leave empty to keep it.', 'lcmt-dev-mailer'); ?></p>
                        <?php endif; ?>
                        <p>
                            <label>
                                <input type="checkbox" name="<?= esc_attr(self::FORGET_FIELD) ?>" value="1">
                                <?php esc_html_e('Forget the saved secret key', 'lcmt-dev-mailer'); ?>
                            </label>
                        </p>
                    <?php endif; ?>

                    <?php if (!SecretBox::canEncrypt()): ?>
                        <p class="description">
                            <?php esc_html_e('Stored without encryption: this site has no WordPress security keys.', 'lcmt-dev-mailer'); ?>
                        </p>
                    <?php endif; ?>

                    <?php if ($secretSource === 'database'): ?>
                        <div class="notice notice-warning inline">
                            <p><?php esc_html_e('A key in the database ends up in backups and copies of the database. Prefer adding this line to wp-config.php, then empty the field here:', 'lcmt-dev-mailer'); ?></p>
                            <p>
                                <code><?= esc_html(self::constantLine(self::CONST_SECRET_KEY)) ?></code>
                                <button type="button" class="button" data-lcmt-copy="<?= esc_attr(self::constantLine(self::CONST_SECRET_KEY)) ?>"
                                        data-copied="<?= esc_attr__('Copied', 'lcmt-dev-mailer') ?>"><?php esc_html_e('Copy', 'lcmt-dev-mailer'); ?></button>
                            </p>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </td>
        </tr>
        <tr data-captcha-provider="<?= $provider ?>">
            <th scope="row"><?php esc_html_e('Status', 'lcmt-dev-mailer'); ?></th>
            <td>
                <?php
                // The check calls Cloudflare, so only when there is something to check
                $status = $ready ? self::checkKeys() : '';

                if ($status === self::STATUS_CONNECTED) {
                    $class   = 'notice-success';
                    $message = __('Cloudflare accepts the keys.', 'lcmt-dev-mailer');
                } elseif ($status === self::STATUS_INVALID) {
                    $class   = 'notice-error';
                    $message = __('Cloudflare refuses the secret key.', 'lcmt-dev-mailer');
                } elseif ($status === self::STATUS_UNREACHABLE) {
                    $class   = 'notice-error';
                    $message = __('Cloudflare could not be reached.', 'lcmt-dev-mailer');
                } else {
                    $class   = 'notice-warning';
                    $message = __('Add both keys to protect forms with Turnstile.', 'lcmt-dev-mailer');
                }
                ?>
                <div class="notice <?= esc_attr($class) ?> inline" style="margin:0;"><p><?= esc_html($message) ?></p></div>
            </td>
        </tr>
        <tr data-captcha-provider="<?= $provider ?>">
            <th scope="row"><label for="lcmt-turnstile-appearance"><?php esc_html_e('Appearance', 'lcmt-dev-mailer'); ?></label></th>
            <td>
                <select id="lcmt-turnstile-appearance" name="<?= esc_attr(self::OPTION_APPEARANCE) ?>">
                    <option value="<?= esc_attr(self::APPEARANCE_INTERACTION) ?>" <?php selected($appearance, self::APPEARANCE_INTERACTION); ?>>
                        <?php esc_html_e('Only when Cloudflare needs the visitor (recommended)', 'lcmt-dev-mailer'); ?>
                    </option>
                    <option value="<?= esc_attr(self::APPEARANCE_ALWAYS) ?>" <?php selected($appearance, self::APPEARANCE_ALWAYS); ?>>
                        <?php esc_html_e('Always visible', 'lcmt-dev-mailer'); ?>
                    </option>
                </select>
                <p class="description">
                    <?php
                    printf(
                        /* translators: 1-2: site key and secret key that always pass, 3-4: site key and secret key that always fail */
                        esc_html__('To test, Cloudflare publishes keys: site key %1$s with secret key %2$s always passes; site key %3$s with secret key %4$s always fails.', 'lcmt-dev-mailer'),
                        '<code>1x00000000000000000000AA</code>',
                        '<code>1x0000000000000000000000000000000AA</code>',
                        '<code>2x00000000000000000000AB</code>',
                        '<code>2x0000000000000000000000000000000AA</code>'
                    );
                    ?>
                </p>
            </td>
        </tr>
        <?php
    }

    /**
     * @return array|\WP_Error
     */
    private static function siteverify(string $token)
    {
        $args = [
            'secret'   => self::secretKey(),
            'response' => $token,
        ];

        if (!empty($_SERVER['REMOTE_ADDR'])) {
            $args['remoteip'] = (string) $_SERVER['REMOTE_ADDR'];
        }

        return wp_remote_post(self::VERIFY_URL, [
            'timeout' => self::TIMEOUT_SECONDS,
            'body'    => $args,
        ]);
    }

    /**
     * @param array|\WP_Error $response
     * @throws CaptchaUnavailable
     */
    private static function decodeResponse($response): array
    {
        if (is_wp_error($response)) {
            throw new CaptchaUnavailable('Turnstile could not be reached.');
        }

        if ((int) wp_remote_retrieve_response_code($response) !== 200) {
            throw new CaptchaUnavailable('Turnstile answered with an HTTP error.');
        }

        $data = json_decode((string) wp_remote_retrieve_body($response), true);
        if (!is_array($data)) {
            throw new CaptchaUnavailable('Turnstile sent an unreadable answer.');
        }

        return $data;
    }

    /**
     * New options are autoloaded by default (WordPress 6.4+ has a helper to
     * change that; older versions need the query).
     */
    private static function keepOutOfAutoload(string $option): void
    {
        if (function_exists('wp_set_option_autoload')) {
            wp_set_option_autoload($option, false);

            return;
        }

        global $wpdb;

        $wpdb->update($wpdb->options, ['autoload' => 'no'], ['option_name' => $option]);
        wp_cache_delete('alloptions', 'options');
    }
}
