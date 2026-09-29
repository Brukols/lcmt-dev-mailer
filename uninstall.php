<?php

/**
 * Removes the received messages and their settings when the plugin is deleted.
 *
 * Deactivating or updating the plugin keeps them; only "Delete" runs this.
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

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
