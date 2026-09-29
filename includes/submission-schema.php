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
    public const VERSION = '3';
    public const OPTION_VERSION = 'lcmt_mailer_db_version';

    /**
     * Every column of the current schema, to tell an upgrade that worked from one that did not.
     */
    public const COLUMNS = [
        'id', 'form_key', 'mail_post_id', 'created_at', 'status', 'mail_sent', 'mail_error', 'fields',
        'page_path', 'page_id', 'landing_path', 'referrer_host', 'channel', 'utm_source', 'utm_medium',
        'utm_campaign', 'click_id_type', 'device', 'locale', 'form_seconds', 'user_agent', 'browser', 'os',
        'anonymized_at', 'trashed_at',
    ];

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
            user_agent varchar(500) NULL,
            browser varchar(30) NOT NULL DEFAULT '',
            os varchar(30) NOT NULL DEFAULT '',
            anonymized_at datetime NULL,
            trashed_at datetime NULL,
            PRIMARY KEY  (id),
            KEY created_at (created_at),
            KEY form_key (form_key),
            KEY status (status),
            KEY anonymized_at (anonymized_at),
            KEY trashed_at (trashed_at)
        ) {$charset};");

        // dbDelta reports no error: when the table is still missing (no
        // CREATE privilege, full disk) or an ALTER failed, leave the version
        // unset so the next load tries again instead of saving into a table
        // that is not there or lacks a column (every insert would fail).
        $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table))) === $table;

        if ($exists && self::hasColumns(self::COLUMNS)) {
            update_option(self::OPTION_VERSION, self::VERSION);
        }
    }

    /**
     * Whether the submissions table has all these columns.
     *
     * @param list<string> $columns
     */
    public static function hasColumns(array $columns): bool
    {
        global $wpdb;

        $existing = $wpdb->get_col('SHOW COLUMNS FROM ' . SubmissionRepository::table());

        return !array_diff($columns, $existing ?: []);
    }
}
