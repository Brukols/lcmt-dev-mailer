<?php

namespace LcmtDevMailer;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Creates and upgrades the submissions table.
 *
 * Sites update through the GitHub releases, which never run the activation
 * hook, so the stored schema version is checked on every load instead.
 */
class SubmissionSchema
{
    public const VERSION = '1';
    public const OPTION_VERSION = 'lcmt_mailer_db_version';

    public static function maybeUpgrade(): void
    {
        if (get_option(self::OPTION_VERSION) === self::VERSION) {
            return;
        }

        self::install();
    }

    public static function install(): void
    {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $table   = SubmissionRepository::table();
        $charset = $wpdb->get_charset_collate();

        // dbDelta needs two spaces after PRIMARY KEY and one field per line.
        dbDelta("CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            form_key varchar(100) NOT NULL DEFAULT '',
            mail_post_id bigint(20) unsigned NOT NULL DEFAULT 0,
            created_at datetime NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'new',
            mail_sent tinyint(1) NOT NULL DEFAULT 0,
            mail_error text NULL,
            fields longtext NULL,
            page_path varchar(255) NOT NULL DEFAULT '',
            page_id bigint(20) unsigned NOT NULL DEFAULT 0,
            landing_path varchar(255) NOT NULL DEFAULT '',
            referrer_host varchar(191) NOT NULL DEFAULT '',
            channel varchar(30) NOT NULL DEFAULT '',
            utm_source varchar(100) NOT NULL DEFAULT '',
            utm_medium varchar(100) NOT NULL DEFAULT '',
            utm_campaign varchar(150) NOT NULL DEFAULT '',
            click_id_type varchar(20) NOT NULL DEFAULT '',
            device varchar(10) NOT NULL DEFAULT '',
            locale varchar(10) NOT NULL DEFAULT '',
            form_seconds int(10) unsigned NULL,
            anonymized_at datetime NULL,
            PRIMARY KEY  (id),
            KEY created_at (created_at),
            KEY form_key (form_key),
            KEY status (status),
            KEY anonymized_at (anonymized_at)
        ) {$charset};");

        // dbDelta reports no error: when the table is still missing (no
        // CREATE privilege, full disk), leave the version unset so the next
        // load tries again instead of saving into a table that is not there.
        $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table))) === $table;

        if ($exists) {
            update_option(self::OPTION_VERSION, self::VERSION);
        }
    }
}
