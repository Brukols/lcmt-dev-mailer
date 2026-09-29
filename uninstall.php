<?php

/**
 * Runs when the plugin is deleted (not on deactivation or update).
 *
 * The daily purge is always unscheduled. The received messages, their table
 * and their settings are only removed when the site asked for it: the prompt
 * on the Delete link of the Plugins screen, or the "Delete received messages
 * when the plugin is deleted" setting (see Uninstaller). Otherwise they stay
 * in the database. On a multisite network each site is handled in turn,
 * since each has its own table, options and cron event.
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

require_once __DIR__ . '/includes/uninstaller.php';

if (is_multisite()) {
    foreach (get_sites(['fields' => 'ids', 'number' => 0]) as $siteId) {
        switch_to_blog((int) $siteId);
        LcmtDevMailer\Uninstaller::uninstallSite();
        restore_current_blog();
    }

    // The answer given in the network admin only served this deletion.
    delete_site_option(LcmtDevMailer\Uninstaller::OPTION_DELETE_DATA);
} else {
    LcmtDevMailer\Uninstaller::uninstallSite();
}
