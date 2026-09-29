<?php

/**
 * Removes the received messages and their settings when the plugin is deleted.
 *
 * Deactivating or updating the plugin keeps them; only "Delete" runs this.
 * On a multisite network, every site has its own table, options and cron
 * event, so each site is cleaned in turn.
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

/**
 * Drops the table, options and scheduled purge of the current site.
 */
function lcmt_mailer_uninstall_site(): void
{
    global $wpdb;

    $wpdb->query('DROP TABLE IF EXISTS ' . $wpdb->prefix . 'lcmt_mailer_submissions');

    foreach ([
        'lcmt_mailer_db_version',
        'lcmt_mailer_retention_days',
        'lcmt_mailer_retention_action',
        'lcmt_mailer_stats_retention_days',
        'lcmt_mailer_attribution_without_consent',
        'lcmt_mailer_failures_dismissed_at',
    ] as $option) {
        delete_option($option);
    }

    wp_clear_scheduled_hook('lcmt_mailer_purge_submissions');
}

if (is_multisite()) {
    foreach (get_sites(['fields' => 'ids', 'number' => 0]) as $siteId) {
        switch_to_blog((int) $siteId);
        lcmt_mailer_uninstall_site();
        restore_current_blog();
    }
} else {
    lcmt_mailer_uninstall_site();
}
