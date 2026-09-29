<?php

namespace LcmtDevMailer;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Decides what deleting the plugin removes. uninstall.php cannot ask, so the
 * answer is stored beforehand: by a prompt on the Delete link of the plugins
 * screen, or by a checkbox on the Data retention page. Without an answer, the
 * received messages are kept.
 */
class Uninstaller
{
    public const OPTION_DELETE_DATA = 'lcmt_mailer_delete_data_on_uninstall';
    public const AJAX_ACTION = 'lcmt_mailer_set_uninstall_data';
    public const CRON_HOOK = 'lcmt_mailer_purge_submissions';

    /**
     * Options removed with the messages, this one included.
     */
    public const DATA_OPTIONS = [
        'lcmt_mailer_db_version',
        'lcmt_mailer_retention_days',
        'lcmt_mailer_retention_action',
        'lcmt_mailer_stats_retention_days',
        'lcmt_mailer_attribution_without_consent',
        'lcmt_mailer_failures_dismissed_at',
        self::OPTION_DELETE_DATA,
    ];

    /**
     * Whether deleting the plugin also deletes this site's messages: its own
     * setting, or, on a network, the answer given in the network admin.
     */
    public static function shouldDeleteData(): bool
    {
        if (get_option(self::OPTION_DELETE_DATA, '0') === '1') {
            return true;
        }

        return is_multisite() && get_site_option(self::OPTION_DELETE_DATA, '0') === '1';
    }

    /**
     * @param mixed $value
     */
    public static function sanitize($value): string
    {
        return $value === '1' || $value === 1 || $value === true ? '1' : '0';
    }

    /**
     * Store the answer to the prompt: for this site, or for every site of the
     * network when it was given in the network admin.
     */
    public static function store(bool $delete, bool $network = false): void
    {
        $value = $delete ? '1' : '0';

        if ($network && is_multisite()) {
            update_site_option(self::OPTION_DELETE_DATA, $value);
            return;
        }

        update_option(self::OPTION_DELETE_DATA, $value, false);
    }

    /**
     * A valid nonce from a user who may delete plugins (and manage the
     * network's plugins when the answer is for the whole network).
     */
    public static function authorized(array $request): bool
    {
        if (!wp_verify_nonce((string) ($request['nonce'] ?? ''), self::AJAX_ACTION)) {
            return false;
        }

        if (!current_user_can('delete_plugins')) {
            return false;
        }

        return !self::isNetworkRequest($request) || current_user_can('manage_network_plugins');
    }

    public static function handleAjax(): void
    {
        $request = wp_unslash($_POST);

        if (!self::authorized($request)) {
            wp_send_json_error(null, 403);
        }

        self::store(($request['delete'] ?? '') === '1', self::isNetworkRequest($request));

        wp_send_json_success();
    }

    /**
     * The prompt on the Delete link of the plugins screen.
     */
    public static function enqueue(string $hook): void
    {
        if ($hook !== 'plugins.php' || !current_user_can('delete_plugins')) {
            return;
        }

        wp_enqueue_script(
            'lcmt-admin-uninstall',
            LCMT_MAILER_URL . 'assets/dist/admin-uninstall.js',
            [],
            lcmt_mailer_asset_version('assets/dist/admin-uninstall.js'),
            true
        );

        wp_localize_script('lcmt-admin-uninstall', 'lcmtMailerUninstall', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'action'  => self::AJAX_ACTION,
            'nonce'   => wp_create_nonce(self::AJAX_ACTION),
            'plugin'  => plugin_basename(LCMT_MAILER_PATH . 'lcmt-dev-mailer.php'),
            'network' => is_network_admin(),
            'confirm' => __("Also delete the received messages and their statistics?\n\nOK: delete them for good.\nCancel: keep them in the database (they will no longer be anonymized automatically).", 'lcmt-dev-mailer'),
        ]);
    }

    /**
     * What uninstall.php runs for one site: the purge is always unscheduled,
     * the table and options only go when the site asked for it.
     */
    public static function uninstallSite(): void
    {
        global $wpdb;

        wp_clear_scheduled_hook(self::CRON_HOOK);

        if (!self::shouldDeleteData()) {
            return;
        }

        $wpdb->query('DROP TABLE IF EXISTS ' . $wpdb->prefix . 'lcmt_mailer_submissions');

        foreach (self::DATA_OPTIONS as $option) {
            delete_option($option);
        }
    }

    private static function isNetworkRequest(array $request): bool
    {
        return is_multisite() && ($request['network'] ?? '') === '1';
    }
}
