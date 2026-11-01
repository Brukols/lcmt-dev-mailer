<?php

namespace LcmtDevMailer;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Keeps track of the encrypted keys that can no longer be decrypted (security
 * keys changed, database copied from another site), tells the admin by email
 * once and by a notice in the back office until the key is saved again.
 */
class UnreadableSecrets
{
    public const OPTION = 'lcmt_mailer_unreadable_secrets';
    private const LOCK_PREFIX = 'lcmt_mailer_unreadable_mail_';

    /** @var array<string, string> Option => constant that takes its place. */
    private static array $constants = [];

    /**
     * Declare the wp-config.php constant that replaces a stored key: once it
     * is defined, an unreadable copy of the key no longer matters.
     */
    public static function replacedByConstant(string $option, string $constant): void
    {
        self::$constants[$option] = $constant;
    }

    public static function report(string $option, string $label): void
    {
        $list = self::all();
        $list[$option] = $label;
        update_option(self::OPTION, $list, false);

        // add_option only succeeds for one request, so concurrent requests
        // send a single email.
        if (!add_option(self::LOCK_PREFIX . $option, time(), '', false)) return;

        // get_bloginfo() returns HTML-escaped text; this mail is plain text
        $siteName = wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES);

        $subject = sprintf(
            /* translators: %s: site name */
            __('[%s] A spam protection key can no longer be read', 'lcmt-dev-mailer'),
            $siteName
        );

        $body = sprintf(
            /* translators: 1: site name, 2: key label, 3: settings page URL */
            __("The spam protection key \"%2\$s\" stored on %1\$s can no longer be read. The WordPress security keys have probably changed, or the database was copied from another site.\n\nUntil it is entered again, the forms that use it are not protected by it.\n\nEnter it again here: %3\$s", 'lcmt-dev-mailer'),
            $siteName,
            $label,
            CaptchaSettings::url()
        );

        wp_mail((string) get_option('admin_email'), $subject, $body);
    }

    /**
     * Forget a key once it is saved again, and allow a new email if it breaks later.
     */
    public static function clear(string $option): void
    {
        delete_option(self::LOCK_PREFIX . $option);

        $list = self::all();
        if (!isset($list[$option])) return;

        unset($list[$option]);
        if ($list === []) {
            delete_option(self::OPTION);
        } else {
            update_option(self::OPTION, $list, false);
        }
    }

    /**
     * Forget the keys now set by their constant, and delete the stored copy
     * nobody can read any more (the settings page no longer offers to).
     */
    public static function resolveReplaced(): void
    {
        foreach (array_keys(self::all()) as $option) {
            $constant = self::$constants[$option] ?? '';

            if ($constant === '' || !defined($constant) || constant($constant) === '') continue;

            delete_option($option);
            self::clear($option);
        }
    }

    public static function notice(): void
    {
        if (!current_user_can('manage_options')) return;

        self::resolveReplaced();

        $list = self::all();
        if ($list === []) return;

        ?>
        <div class="notice notice-error">
            <p>
                <?php
                printf(
                    /* translators: %s: comma-separated key names */
                    esc_html__('LCMT Mailer can no longer read these spam protection keys: %s. The forms that use them are not protected by them.', 'lcmt-dev-mailer'),
                    '<strong>' . esc_html(implode(', ', $list)) . '</strong>'
                );
                ?>
                <a href="<?= esc_url(CaptchaSettings::url()) ?>"><?php esc_html_e('Open the Spam protection page', 'lcmt-dev-mailer'); ?></a>
            </p>
        </div>
        <?php
    }

    private static function all(): array
    {
        $list = get_option(self::OPTION, []);

        return is_array($list) ? $list : [];
    }
}
