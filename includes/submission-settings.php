<?php

namespace LcmtDevMailer;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Email templates → Data retention: how long received messages are kept.
 */
class SubmissionSettings
{
    public const PAGE_SLUG = 'lcmt-mailer-retention';
    public const GROUP = 'lcmt_mailer_retention';
    public const OPTION_DAYS = 'lcmt_mailer_retention_days';
    public const OPTION_ACTION = 'lcmt_mailer_retention_action';
    public const OPTION_STATS_DAYS = 'lcmt_mailer_stats_retention_days';
    public const PURGE_ACTION = 'lcmt_mailer_purge_now';

    public static function retentionDays(): int
    {
        return Retention::clampDays(get_option(self::OPTION_DAYS, Retention::DEFAULT_DAYS), 1, Retention::DEFAULT_DAYS);
    }

    public static function retentionAction(): string
    {
        return get_option(self::OPTION_ACTION, 'anonymize') === 'delete' ? 'delete' : 'anonymize';
    }

    public static function statsRetentionDays(): int
    {
        return Retention::clampDays(get_option(self::OPTION_STATS_DAYS, 0), 0, 0);
    }

    public static function addSubmenu(): void
    {
        add_submenu_page(
            'edit.php?post_type=' . PostType::SLUG,
            __('Data retention', 'lcmt-dev-mailer'),
            __('Data retention', 'lcmt-dev-mailer'),
            'manage_options',
            self::PAGE_SLUG,
            [self::class, 'renderPage']
        );
    }

    public static function registerSettings(): void
    {
        register_setting(self::GROUP, self::OPTION_DAYS, [
            'type'              => 'integer',
            'sanitize_callback' => static fn($value) => Retention::clampDays($value, 1, Retention::DEFAULT_DAYS),
            'default'           => Retention::DEFAULT_DAYS,
        ]);

        register_setting(self::GROUP, self::OPTION_ACTION, [
            'type'              => 'string',
            'sanitize_callback' => static fn($value) => $value === 'delete' ? 'delete' : 'anonymize',
            'default'           => 'anonymize',
        ]);

        register_setting(self::GROUP, self::OPTION_STATS_DAYS, [
            'type'              => 'integer',
            'sanitize_callback' => static fn($value) => Retention::clampDays($value, 0, 0),
            'default'           => 0,
        ]);

        register_setting(self::GROUP, Uninstaller::OPTION_DELETE_DATA, [
            'type'              => 'string',
            'sanitize_callback' => [Uninstaller::class, 'sanitize'],
            'default'           => '0',
        ]);

        // options.php sends null for an unchecked box.
        register_setting(self::GROUP, Attribution::OPTION_WITHOUT_CONSENT, [
            'type'              => 'string',
            'sanitize_callback' => static fn($value) => $value ? '1' : '0',
            'default'           => '1',
        ]);
    }

    public static function handlePurgeNow(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You are not allowed to do this.', 'lcmt-dev-mailer'), 403);
        }

        check_admin_referer(self::PURGE_ACTION);

        $done = Retention::run();

        wp_safe_redirect(add_query_arg([
            'post_type'  => PostType::SLUG,
            'page'       => self::PAGE_SLUG,
            'anonymized' => $done['anonymized'],
            'deleted'    => $done['deleted'],
        ], admin_url('edit.php')));
        exit;
    }

    public static function renderPage(): void
    {
        $days      = self::retentionDays();
        $action    = self::retentionAction();
        $statsDays = self::statsRetentionDays();
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Data retention', 'lcmt-dev-mailer'); ?></h1>

            <?php if (isset($_GET['anonymized'])): ?>
                <div class="notice notice-success is-dismissible"><p>
                    <?php
                    printf(
                        /* translators: 1: number of anonymized messages, 2: number of deleted messages */
                        esc_html__('Purge done: %1$d messages anonymized, %2$d deleted.', 'lcmt-dev-mailer'),
                        absint($_GET['anonymized']),
                        absint($_GET['deleted'] ?? 0)
                    );
                    ?>
                </p></div>
            <?php endif; ?>

            <form method="post" action="options.php">
                <?php settings_fields(self::GROUP); ?>

                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="lcmt-retention-days"><?php esc_html_e('Keep personal data for', 'lcmt-dev-mailer'); ?></label></th>
                        <td>
                            <input type="number" min="1" max="<?= (int) Retention::MAX_DAYS ?>" id="lcmt-retention-days"
                                   name="<?= esc_attr(self::OPTION_DAYS) ?>" value="<?= esc_attr((string) $days) ?>" class="small-text" />
                            <?php esc_html_e('days after the message was sent', 'lcmt-dev-mailer'); ?>
                            <p class="description">
                                <?php esc_html_e('The GDPR sets no fixed period: data may be kept as long as its purpose needs it. For prospects, the CNIL accepts at most 3 years (1095 days) after the last contact. State this period in your privacy policy.', 'lcmt-dev-mailer'); ?>
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><?php esc_html_e('When the period ends', 'lcmt-dev-mailer'); ?></th>
                        <td>
                            <fieldset>
                                <label><input type="radio" name="<?= esc_attr(self::OPTION_ACTION) ?>" value="anonymize" <?php checked($action, 'anonymize'); ?> />
                                    <?php esc_html_e('Anonymize: erase what the visitor typed, keep the day, form, page and source for statistics', 'lcmt-dev-mailer'); ?></label><br>
                                <label><input type="radio" name="<?= esc_attr(self::OPTION_ACTION) ?>" value="delete" <?php checked($action, 'delete'); ?> />
                                    <?php esc_html_e('Delete the whole message', 'lcmt-dev-mailer'); ?></label>
                            </fieldset>
                            <p class="description"><?php esc_html_e('When deleting, anonymized statistics older than this period are deleted too, whatever the setting below.', 'lcmt-dev-mailer'); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><?php esc_html_e('When the plugin is deleted', 'lcmt-dev-mailer'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="<?= esc_attr(Uninstaller::OPTION_DELETE_DATA) ?>" value="1" <?php checked(get_option(Uninstaller::OPTION_DELETE_DATA, '0'), '1'); ?> />
                                <?php esc_html_e('Delete received messages when the plugin is deleted', 'lcmt-dev-mailer'); ?>
                            </label>
                            <p class="description"><?php esc_html_e('Unticked, deleting the plugin keeps the messages and their statistics in the database. Deactivating the plugin from the Plugins screen asks the question and stores the answer here; deactivating it with WP-CLI or a bulk action does not.', 'lcmt-dev-mailer'); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><label for="lcmt-stats-days"><?php esc_html_e('Keep anonymized statistics for', 'lcmt-dev-mailer'); ?></label></th>
                        <td>
                            <input type="number" min="0" max="<?= (int) Retention::MAX_DAYS ?>" id="lcmt-stats-days"
                                   name="<?= esc_attr(self::OPTION_STATS_DAYS) ?>" value="<?= esc_attr((string) $statsDays) ?>" class="small-text" />
                            <?php esc_html_e('days', 'lcmt-dev-mailer'); ?>
                            <p class="description"><?php esc_html_e('0 keeps them forever: once anonymized, they are no longer personal data.', 'lcmt-dev-mailer'); ?></p>
                        </td>
                    </tr>
                </table>

                <details style="margin: 1em 0;">
                    <summary style="cursor: pointer; font-weight: 600;"><?php esc_html_e('Advanced settings', 'lcmt-dev-mailer'); ?></summary>

                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php esc_html_e('Visit origin without a consent tool', 'lcmt-dev-mailer'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="<?= esc_attr(Attribution::OPTION_WITHOUT_CONSENT) ?>" value="1" <?php checked(Attribution::storesWithoutConsent()); ?> />
                                    <?php esc_html_e('Remember the landing page and campaign in the visitor\'s browser when no consent tool is installed', 'lcmt-dev-mailer'); ?>
                                </label>
                                <p class="description">
                                    <?php esc_html_e('With a consent tool compatible with WP Consent API, this is only done after the visitor accepts statistics, whatever this setting. Without one, storing it needs consent under the ePrivacy rules: untick this to only record the page the form was sent from.', 'lcmt-dev-mailer'); ?>
                                </p>
                            </td>
                        </tr>
                    </table>
                </details>

                <?php submit_button(); ?>
            </form>

            <hr>

            <h2><?php esc_html_e('Purge now', 'lcmt-dev-mailer'); ?></h2>
            <p><?php esc_html_e('The purge runs once a day, when the site gets visits. Run it now to apply new settings at once.', 'lcmt-dev-mailer'); ?></p>
            <form method="post" action="<?= esc_url(admin_url('admin-post.php')) ?>"
                  onsubmit="return confirm(<?= esc_attr(wp_json_encode(__('Apply the retention settings now? This cannot be undone.', 'lcmt-dev-mailer'))) ?>);">
                <input type="hidden" name="action" value="<?= esc_attr(self::PURGE_ACTION) ?>" />
                <?php wp_nonce_field(self::PURGE_ACTION); ?>
                <?php submit_button(__('Purge now', 'lcmt-dev-mailer'), 'secondary', 'submit', false); ?>
            </form>
        </div>
        <?php
    }
}
