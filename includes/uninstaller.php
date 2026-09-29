<?php

namespace LcmtDevMailer;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Decides what deleting the plugin removes. uninstall.php cannot ask, and
 * WordPress only shows the Delete link once the plugin is inactive, when none
 * of its code runs. So the answer is stored beforehand: by a prompt on the
 * Deactivate link of the Plugins screen, or by a checkbox on the Data
 * retention page. Without an answer, the received messages are kept.
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
     * The dialog on the Deactivate link of the Plugins screen (single site
     * and network admin), while the plugin is still loaded.
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
        ]);
    }

    /**
     * The dialog the deactivation opens instead of the browser's confirm().
     * Printed in the footer of the Plugins screen, and only where the script
     * that opens it was enqueued.
     */
    public static function printDialog(): void
    {
        if (!wp_script_is('lcmt-admin-uninstall', 'enqueued')) {
            return;
        }

        $stored  = is_network_admin() ? get_site_option(self::OPTION_DELETE_DATA, '0') : get_option(self::OPTION_DELETE_DATA, '0');
        $checked = $stored === '1';
        $helpOff = __('They stay in the database, but are no longer anonymized automatically once the plugin is inactive.', 'lcmt-dev-mailer');
        $helpOn  = __('They will be erased for good when you delete the plugin.', 'lcmt-dev-mailer');
        ?>
        <style>
            #lcmt-deactivate-dialog { box-sizing: border-box; width: calc(100% - 32px); max-width: 480px; padding: 0; border: 0; border-radius: 4px; color: #1d2327; box-shadow: 0 3px 30px rgba(0, 0, 0, .3); }
            #lcmt-deactivate-dialog::backdrop { background: rgba(0, 0, 0, .7); }
            #lcmt-deactivate-dialog .lcmt-deactivate__body { padding: 20px 24px; }
            #lcmt-deactivate-dialog h2 { margin: 0 0 12px; padding: 0; font-size: 1.3em; line-height: 1.4; }
            #lcmt-deactivate-dialog p { margin: 0 0 12px; }
            #lcmt-deactivate-dialog label { display: flex; gap: 8px; align-items: flex-start; font-weight: 600; }
            #lcmt-deactivate-dialog label input { margin-top: 2px; }
            #lcmt-deactivate-dialog .lcmt-deactivate__help { margin: 4px 0 0 26px; color: #50575e; }
            #lcmt-deactivate-dialog .lcmt-deactivate__actions { display: flex; justify-content: flex-end; gap: 8px; margin-top: 20px; }
        </style>
        <dialog id="lcmt-deactivate-dialog" aria-labelledby="lcmt-deactivate-title">
            <div class="lcmt-deactivate__body">
                <h2 id="lcmt-deactivate-title"><?php esc_html_e('Deactivate LCMT Mailer', 'lcmt-dev-mailer'); ?></h2>
                <p><?php esc_html_e('Deactivating keeps everything: your settings, your templates and the received messages.', 'lcmt-dev-mailer'); ?></p>
                <label>
                    <input type="checkbox" data-lcmt-delete <?php checked($checked); ?> />
                    <span><?php esc_html_e('Also delete the received messages and statistics when the plugin is deleted', 'lcmt-dev-mailer'); ?></span>
                </label>
                <p class="lcmt-deactivate__help" data-help-off="<?= esc_attr($helpOff) ?>" data-help-on="<?= esc_attr($helpOn) ?>" data-lcmt-help aria-live="polite"><?= esc_html($checked ? $helpOn : $helpOff) ?></p>
                <div class="lcmt-deactivate__actions">
                    <button type="button" class="button" data-lcmt-cancel><?php esc_html_e('Cancel', 'lcmt-dev-mailer'); ?></button>
                    <button type="button" class="button button-primary" data-lcmt-confirm data-busy-label="<?= esc_attr__('Deactivating…', 'lcmt-dev-mailer') ?>"><?php esc_html_e('Deactivate', 'lcmt-dev-mailer'); ?></button>
                </div>
            </div>
        </dialog>
        <?php
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
