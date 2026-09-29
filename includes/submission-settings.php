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

    public static function url(array $args = []): string
    {
        return add_query_arg(
            array_merge(['post_type' => PostType::SLUG, 'page' => self::PAGE_SLUG], $args),
            admin_url('edit.php')
        );
    }

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

        wp_safe_redirect(self::url([
            'anonymized' => $done['anonymized'],
            'deleted'    => $done['deleted'],
        ]));
        exit;
    }

    /**
     * The copy buttons of the Privacy policy section, on this screen only.
     */
    public static function enqueue(string $hook): void
    {
        if ($hook !== PostType::SLUG . '_page_' . self::PAGE_SLUG) {
            return;
        }

        wp_enqueue_script(
            'lcmt-admin-privacy-docs',
            LCMT_MAILER_URL . 'assets/dist/admin-privacy-docs.js',
            [],
            lcmt_mailer_asset_version('assets/dist/admin-privacy-docs.js'),
            true
        );
    }

    /**
     * The Data retention screen: heading, settings form and "Purge now".
     */
    public static function render(): void
    {
        $days      = self::retentionDays();
        $action    = self::retentionAction();
        $statsDays = self::statsRetentionDays();
        ?>
        <div class="wrap">
        <h1 class="wp-heading-inline"><?php esc_html_e('Data retention', 'lcmt-dev-mailer'); ?></h1>
        <hr class="wp-header-end">
        <div class="lcmt-retention">
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
                <?php
                // What settings_fields() prints, but with a referer that is this page: options.php
                // sends the user back to it after saving.
                ?>
                <input type="hidden" name="option_page" value="<?= esc_attr(self::GROUP) ?>" />
                <input type="hidden" name="action" value="update" />
                <?php wp_nonce_field(self::GROUP . '-options', '_wpnonce', false); ?>
                <input type="hidden" name="_wp_http_referer" value="<?= esc_attr(self::url()) ?>" />

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

            <hr>

            <?php self::renderPrivacyDocs(); ?>
        </div>
        </div>
        <?php
    }

    /**
     * "Privacy policy": the shortcodes that print the plugin's part of the
     * privacy policy, with what each prints now and a Copy button. Placed
     * after "Purge now" since it is reference material, not a setting.
     */
    private static function renderPrivacyDocs(): void
    {
        $pageUrl  = get_privacy_policy_url();
        $pageLink = $pageUrl !== ''
            ? '<a href="' . esc_url($pageUrl) . '">' . esc_html__('privacy policy page', 'lcmt-dev-mailer') . '</a>'
            : '<a href="' . esc_url(admin_url('options-privacy.php')) . '">' . esc_html__('privacy policy page', 'lcmt-dev-mailer') . '</a>';
        $guide    = '<a href="' . esc_url(admin_url('options-privacy.php?tab=policyguide')) . '">' . esc_html__('privacy policy guide', 'lcmt-dev-mailer') . '</a>';
        $rows     = [
            'lcmt-privacy-policy'   => true,
            'lcmt-retention-period' => false,
            'lcmt-retention-action' => false,
            'lcmt-retention-days'   => false,
        ];
        $sample   = __('The messages sent through our forms are kept for [lcmt-retention-period], then [lcmt-retention-action].', 'lcmt-dev-mailer');
        ?>
        <div class="lcmt-privacy-docs">
            <h2><?php esc_html_e('Privacy policy', 'lcmt-dev-mailer'); ?></h2>
            <p>
                <?php
                printf(
                    /* translators: 1: link to the site's privacy policy page, 2: link to the WordPress privacy policy guide */
                    wp_kses_post(__('These shortcodes follow the settings above. Paste them in your %1$s to state how this plugin handles personal data. The same text is in the WordPress %2$s.', 'lcmt-dev-mailer')),
                    $pageLink, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built above from escaped parts.
                    $guide // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built above from escaped parts.
                );
                ?>
            </p>

            <table class="widefat striped lcmt-privacy-docs__table">
                <thead>
                    <tr>
                        <th scope="col"><?php esc_html_e('Shortcode', 'lcmt-dev-mailer'); ?></th>
                        <th scope="col"><?php esc_html_e('Displays', 'lcmt-dev-mailer'); ?></th>
                        <th scope="col"><?php esc_html_e('Copy', 'lcmt-dev-mailer'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $name => $recommended): ?>
                        <?php $code = '[' . $name . ']'; ?>
                        <tr>
                            <td>
                                <code><?= esc_html($code) ?></code>
                                <?php if ($recommended): ?>
                                    <br><strong><?php esc_html_e('Recommended', 'lcmt-dev-mailer'); ?></strong>
                                <?php endif; ?>
                            </td>
                            <?php if ($recommended): ?>
                                <td class="lcmt-privacy-docs__live"><div class="lcmt-privacy-docs__policy"><?= Privacy::policyText() // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- already run through wp_kses_post(). ?></div></td>
                            <?php else: ?>
                                <td class="lcmt-privacy-docs__live"><code><?= do_shortcode($code) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the shortcode escapes its output. ?></code></td>
                            <?php endif; ?>
                            <td>
                                <button type="button" class="button" data-lcmt-copy="<?= esc_attr($code) ?>"
                                        data-copied="<?= esc_attr__('Copied', 'lcmt-dev-mailer') ?>"><?php esc_html_e('Copy', 'lcmt-dev-mailer'); ?></button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <h3><?php esc_html_e('Write your own text', 'lcmt-dev-mailer'); ?></h3>
            <p><?php esc_html_e('To word it yourself, combine the shortcodes in your own sentences. For example:', 'lcmt-dev-mailer'); ?></p>
            <p>
                <label for="lcmt-privacy-sample" class="screen-reader-text"><?php esc_html_e('Sample paragraph', 'lcmt-dev-mailer'); ?></label>
                <textarea readonly id="lcmt-privacy-sample" class="large-text" rows="2"><?= esc_textarea($sample) ?></textarea>
            </p>
            <p>
                <button type="button" class="button" data-lcmt-copy="<?= esc_attr($sample) ?>"
                        data-copied="<?= esc_attr__('Copied', 'lcmt-dev-mailer') ?>"><?php esc_html_e('Copy', 'lcmt-dev-mailer'); ?></button>
            </p>

            <p class="screen-reader-text" role="status" aria-live="polite" data-lcmt-copy-status></p>
        </div>
        <style>
            .lcmt-privacy-docs__table { max-width: 900px; }
            .lcmt-privacy-docs__table td { vertical-align: top; }
            .lcmt-privacy-docs__policy { max-height: 16em; overflow: auto; padding: 0 12px; border: 1px solid #c3c4c7; background: #fff; }
        </style>
        <?php
    }
}
