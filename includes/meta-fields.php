<?php

namespace LcmtDevMailer;

if (!defined('ABSPATH')) {
    exit;
}

class MetaFields
{
    public const FIELD_KEY = '_lcmt_mail_key';
    public const FIELD_TO = '_lcmt_mail_to';
    public const FIELD_REPLY_TO = '_lcmt_mail_reply_to';
    public const FIELD_SUBJECT = '_lcmt_mail_subject';
    public const FIELD_CONTENT = '_lcmt_mail_content';
    public const FIELD_STORE_SUBMISSIONS = '_lcmt_mail_store_submissions';
    public const FIELD_CONFIRMATION_PAGE = '_lcmt_mail_confirmation_page';
    public const FIELD_CAPTCHA = '_lcmt_mail_captcha';
    public const FIELD_SUCCESS_MESSAGE = '_lcmt_mail_success_message';
    public const FIELD_FILE_FORMATS = '_lcmt_mail_file_formats';
    public const FIELD_FILE_MAX_SIZE = '_lcmt_mail_file_max_size';

    public static function addMetaBox(): void
    {
        add_meta_box(
            'lcmt_mail_fields',
            __('Mail Fields', 'lcmt-dev-mailer'),
            [self::class, 'render'],
            PostType::SLUG,
            'normal',
            'high'
        );
    }

    public static function render(\WP_Post $post): void
    {
        wp_nonce_field('lcmt_mail_fields', 'lcmt_mail_fields_nonce');

        $key      = get_post_meta($post->ID, self::FIELD_KEY, true);
        $to       = get_post_meta($post->ID, self::FIELD_TO, true);
        $replyTo  = get_post_meta($post->ID, self::FIELD_REPLY_TO, true);
        $subject  = get_post_meta($post->ID, self::FIELD_SUBJECT, true);
        $content  = get_post_meta($post->ID, self::FIELD_CONTENT, true);

        echo '<table class="form-table">';

        echo '<tr>';
        echo '<th><label for="lcmt_mail_key">' . esc_html__('Key', 'lcmt-dev-mailer') . '</label></th>';
        echo '<td>';
        echo '<input type="text" id="lcmt_mail_key" name="lcmt_mail_key" value="' . esc_attr($key) . '" class="regular-text" pattern="[a-z0-9\-]+" placeholder="contact" />';
        echo '<p class="description">' . esc_html__('Unique identifier in kebab-case (e.g. contact, reset-password). Used for the shortcode, REST endpoint and form file.', 'lcmt-dev-mailer') . '</p>';
        echo '</td>';
        echo '</tr>';

        echo '<tr>';
        echo '<th><label for="lcmt_mail_to">' . esc_html__('To', 'lcmt-dev-mailer') . '</label></th>';
        echo '<td><input type="text" id="lcmt_mail_to" name="lcmt_mail_to" value="' . esc_attr($to) . '" class="large-text" /></td>';
        echo '</tr>';

        echo '<tr>';
        echo '<th><label for="lcmt_mail_reply_to">' . esc_html__('Reply-To', 'lcmt-dev-mailer') . '</label></th>';
        echo '<td>';
        echo '<input type="text" id="lcmt_mail_reply_to" name="lcmt_mail_reply_to" value="' . esc_attr($replyTo) . '" class="large-text" placeholder="[email* email]" />';
        echo '<p class="description">' . esc_html__('Optional. Supports placeholders (e.g. [email* email] to reply to the form sender).', 'lcmt-dev-mailer') . '</p>';
        echo '</td>';
        echo '</tr>';

        echo '<tr>';
        echo '<th><label for="lcmt_mail_subject">' . esc_html__('Subject', 'lcmt-dev-mailer') . '</label></th>';
        echo '<td><input type="text" id="lcmt_mail_subject" name="lcmt_mail_subject" value="' . esc_attr($subject) . '" class="large-text" /></td>';
        echo '</tr>';

        echo '<tr>';
        echo '<th><label for="lcmt_mail_content">' . esc_html__('Content', 'lcmt-dev-mailer') . '</label></th>';
        echo '<td>';
        wp_editor($content, 'lcmt_mail_content', [
            'textarea_name' => 'lcmt_mail_content',
            'media_buttons' => true,
            'textarea_rows' => 15,
            'teeny'         => false,
        ]);
        echo '</td>';
        echo '</tr>';

        echo '<tr>';
        echo '<th>' . esc_html__('Received messages', 'lcmt-dev-mailer') . '</th>';
        echo '<td>';
        echo '<label><input type="checkbox" name="lcmt_mail_store_submissions" value="1" ' . checked(self::storesSubmissions($post->ID), true, false) . ' /> ';
        echo esc_html__('Save the messages sent through this form', 'lcmt-dev-mailer') . '</label>';
        echo '<p class="description">' . esc_html__('They appear under Email templates → Received messages, and are anonymized or deleted after the retention period set there.', 'lcmt-dev-mailer') . '</p>';
        echo '</td>';
        echo '</tr>';

        self::renderSuccessMessage($post);
        self::renderConfirmationPage($post);
        self::renderCaptcha($post);
        self::renderFileUploads($post, $to, $replyTo, $subject, $content);

        echo '</table>';

        // ── Placeholder syntax reference ──
        self::renderSyntaxReference();
    }

    public static function save(int $postId): void
    {
        if (!isset($_POST['lcmt_mail_fields_nonce'])) {
            return;
        }

        if (!wp_verify_nonce($_POST['lcmt_mail_fields_nonce'], 'lcmt_mail_fields')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!current_user_can('edit_post', $postId)) {
            return;
        }

        if (get_post_type($postId) !== PostType::SLUG) {
            return;
        }

        if (isset($_POST['lcmt_mail_key'])) {
            update_post_meta($postId, self::FIELD_KEY, sanitize_title($_POST['lcmt_mail_key']));
        }

        if (isset($_POST['lcmt_mail_to'])) {
            update_post_meta($postId, self::FIELD_TO, sanitize_text_field($_POST['lcmt_mail_to']));
        }

        if (isset($_POST['lcmt_mail_reply_to'])) {
            update_post_meta($postId, self::FIELD_REPLY_TO, sanitize_text_field($_POST['lcmt_mail_reply_to']));
        }

        if (isset($_POST['lcmt_mail_subject'])) {
            update_post_meta($postId, self::FIELD_SUBJECT, sanitize_text_field($_POST['lcmt_mail_subject']));
        }

        if (isset($_POST['lcmt_mail_content'])) {
            update_post_meta($postId, self::FIELD_CONTENT, wp_kses_post($_POST['lcmt_mail_content']));
        }

        update_post_meta($postId, self::FIELD_STORE_SUBMISSIONS, isset($_POST['lcmt_mail_store_submissions']) ? '1' : '0');

        if (isset($_POST['lcmt_mail_success_message'])) {
            $message = trim(sanitize_textarea_field(wp_unslash($_POST['lcmt_mail_success_message'])));

            if ($message !== '') {
                update_post_meta($postId, self::FIELD_SUCCESS_MESSAGE, $message);
            } else {
                delete_post_meta($postId, self::FIELD_SUCCESS_MESSAGE);
            }
        }

        if (isset($_POST['lcmt_mail_confirmation_page'])) {
            $pageId = ConfirmationPage::normalizeId(wp_unslash($_POST['lcmt_mail_confirmation_page']));

            if ($pageId && get_post_type($pageId) === 'page') {
                update_post_meta($postId, self::FIELD_CONFIRMATION_PAGE, (string) $pageId);
            } else {
                delete_post_meta($postId, self::FIELD_CONFIRMATION_PAGE);
            }
        }

        $captcha = Captcha::sanitizeForMail(wp_unslash($_POST['lcmt_mail_captcha'] ?? ''));

        if ($captcha === Captcha::INHERIT) {
            delete_post_meta($postId, self::FIELD_CAPTCHA);
        } else {
            update_post_meta($postId, self::FIELD_CAPTCHA, $captcha);
        }

        // Only when the "File uploads" row was in the form.
        if (isset($_POST['lcmt_mail_file_settings'])) {
            $formats = FileField::sanitizeFormats(wp_unslash($_POST['lcmt_mail_file_formats'] ?? []), FileField::formats());

            if ($formats) {
                update_post_meta($postId, self::FIELD_FILE_FORMATS, implode(',', $formats));
            } else {
                // Nothing ticked: the default formats.
                delete_post_meta($postId, self::FIELD_FILE_FORMATS);
            }

            $maxMb = min(
                FileField::sanitizeMaxMb(wp_unslash($_POST['lcmt_mail_file_max_size'] ?? '')),
                FileField::serverMaxMb(FileField::serverLimit())
            );

            update_post_meta($postId, self::FIELD_FILE_MAX_SIZE, (string) $maxMb);
        }
    }

    /**
     * The "File uploads" row: the formats and the size the file fields
     * ([name file]) of this template accept. Shown on every template, with a
     * note when it has no file field yet.
     */
    private static function renderFileUploads(\WP_Post $post, string $to, string $replyTo, string $subject, string $content): void
    {
        $map      = FileField::formats();
        $selected = FileField::storedFormats($post->ID, $map);
        $server   = FileField::serverLimit();
        $serverMb = FileField::serverMaxMb($server);
        $maxMb    = min(FileField::storedMaxMb($post->ID), $serverMb);
        $hasFile  = FileField::hasFileField(FieldParser::parse($to, $replyTo, $subject, $content));

        echo '<tr>';
        echo '<th>' . esc_html__('File uploads', 'lcmt-dev-mailer') . '</th>';
        echo '<td>';
        echo '<input type="hidden" name="lcmt_mail_file_settings" value="1" />';

        echo '<p class="description" style="margin-bottom: 8px;">';
        echo esc_html__('For the file fields of this template, written [name file] or [name* file]. The file is attached to the email and never kept on the site: received messages only keep its name.', 'lcmt-dev-mailer');

        if (!$hasFile) {
            echo ' <em>' . esc_html__('This template has no file field yet.', 'lcmt-dev-mailer') . '</em>';
        }

        echo '</p>';

        echo '<fieldset>';
        echo '<legend style="font-weight: 600; margin-bottom: 4px;">' . esc_html__('Allowed formats', 'lcmt-dev-mailer') . '</legend>';

        foreach ($map as $id => $format) {
            echo '<label style="display: inline-block; min-width: 11em; margin: 0 12px 4px 0;"><input type="checkbox" name="lcmt_mail_file_formats[]" value="' . esc_attr($id) . '" ' . checked(in_array($id, $selected, true), true, false) . ' /> ';
            echo esc_html($format['label']) . ' <code style="font-size: 11px;">' . esc_html('.' . implode(' .', $format['extensions'])) . '</code></label>';
        }

        echo '</fieldset>';
        echo '<p class="description">' . esc_html__('Nothing ticked: JPG, PNG and PDF. The content of each file is checked, not only its extension.', 'lcmt-dev-mailer') . '</p>';

        echo '<p style="margin-top: 12px;"><label for="lcmt_mail_file_max_size" style="font-weight: 600;">' . esc_html__('Maximum size', 'lcmt-dev-mailer') . '</label> ';
        echo '<input type="number" id="lcmt_mail_file_max_size" name="lcmt_mail_file_max_size" value="' . esc_attr((string) $maxMb) . '" min="1" max="' . esc_attr((string) $serverMb) . '" step="1" class="small-text" /> ';
        echo esc_html__('MB', 'lcmt-dev-mailer') . '</p>';

        echo '<p class="description">' . esc_html(sprintf(
            /* translators: %s: a size such as "64 MB" */
            __('Per file. This server accepts up to %s per upload (PHP upload_max_filesize and post_max_size): a larger value is lowered to it.', 'lcmt-dev-mailer'),
            FileField::sizeText($server)
        )) . '</p>';
        echo '</td>';
        echo '</tr>';
    }

    /**
     * The "Spam protection" row: the site default, none, or one provider.
     * A provider that is not set up is flagged: a form using it stays
     * unprotected until its keys are entered.
     */
    private static function renderCaptcha(\WP_Post $post): void
    {
        $stored    = (string) get_post_meta($post->ID, self::FIELD_CAPTCHA, true);
        $providers = Captcha::providers();
        $defaultId = Captcha::selectedId();
        $default   = isset($providers[$defaultId]) ? $providers[$defaultId]::label() : __('None', 'lcmt-dev-mailer');

        echo '<tr>';
        echo '<th><label for="lcmt_mail_captcha">' . esc_html__('Spam protection', 'lcmt-dev-mailer') . '</label></th>';
        echo '<td>';
        echo '<select id="lcmt_mail_captcha" name="lcmt_mail_captcha">';
        echo '<option value="' . esc_attr(Captcha::INHERIT) . '" ' . selected($stored, Captcha::INHERIT, false) . '>'
            . esc_html(sprintf(
                /* translators: %s: name of the site-wide spam protection */
                __('Site default (%s)', 'lcmt-dev-mailer'),
                $default
            )) . '</option>';
        echo '<option value="' . esc_attr(Captcha::NONE) . '" ' . selected($stored, Captcha::NONE, false) . '>' . esc_html__('None', 'lcmt-dev-mailer') . '</option>';

        foreach ($providers as $id => $provider) {
            $label = $provider::label();

            if (!Captcha::isReady($provider)) {
                $label .= ' — ' . __('not set up', 'lcmt-dev-mailer');
            }

            echo '<option value="' . esc_attr($id) . '" ' . selected($stored, $id, false) . '>' . esc_html($label) . '</option>';
        }

        echo '</select>';
        echo '<p class="description">' . esc_html__('Blocks automated submissions of this form.', 'lcmt-dev-mailer');

        $selectedProvider = $providers[Captcha::selectedIdFor($post->ID)] ?? null;
        $defaultProvider  = $providers[$defaultId] ?? null;

        $unready = $selectedProvider && !Captcha::isReady($selectedProvider) ? $selectedProvider : ($defaultProvider && !Captcha::isReady($defaultProvider) ? $defaultProvider : null);

        if ($unready) {
            // Straight to the tab of the provider to set up
            echo ' <a href="' . esc_url(CaptchaSettings::tabUrl((string) array_search($unready, $providers, true))) . '">' . esc_html__('Set up a protection', 'lcmt-dev-mailer') . '</a>';
        }

        echo '</p>';
        echo '</td>';
        echo '</tr>';
    }

    /**
     * The "Success message" row: optional, the site's message shows through
     * as the placeholder of the empty field.
     */
    private static function renderSuccessMessage(\WP_Post $post): void
    {
        $message = (string) get_post_meta($post->ID, self::FIELD_SUCCESS_MESSAGE, true);

        echo '<tr>';
        echo '<th><label for="lcmt_mail_success_message">' . esc_html__('Success message', 'lcmt-dev-mailer') . '</label></th>';
        echo '<td>';
        echo '<textarea id="lcmt_mail_success_message" name="lcmt_mail_success_message" rows="2" class="large-text" placeholder="' . esc_attr(Settings::siteSuccessMessage()) . '">' . esc_textarea($message) . '</textarea>';
        echo '<p class="description">' . esc_html__('Optional. Shown after a successful submission of this form. Empty: the site\'s message, set in Email templates → Settings.', 'lcmt-dev-mailer') . '</p>';
        echo '</td>';
        echo '</tr>';
    }

    /**
     * The "Confirmation page" row: a select of the published pages, "None"
     * by default. A stored page that is no longer published is not in the
     * list, so it is flagged: saving the form clears it.
     */
    private static function renderConfirmationPage(\WP_Post $post): void
    {
        $selected = ConfirmationPage::pageId($post->ID);

        $select = wp_dropdown_pages([
            'name'              => 'lcmt_mail_confirmation_page',
            'id'                => 'lcmt_mail_confirmation_page',
            'selected'          => $selected,
            'show_option_none'  => __('None', 'lcmt-dev-mailer'),
            'option_none_value' => '0',
            'post_status'       => 'publish',
            'echo'              => 0,
        ]);

        echo '<tr>';
        echo '<th><label for="lcmt_mail_confirmation_page">' . esc_html__('Confirmation page', 'lcmt-dev-mailer') . '</label></th>';
        echo '<td>';

        if ($select) {
            // Built and escaped by wp_dropdown_pages().
            echo $select; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        } else {
            echo '<input type="hidden" name="lcmt_mail_confirmation_page" value="0" />';
            echo '<em>' . esc_html__('No published page yet.', 'lcmt-dev-mailer') . '</em>';
        }

        if ($selected && get_post_status($selected) !== 'publish') {
            echo '<p class="description" style="color: #b32d2e;">' . esc_html(sprintf(
                /* translators: %d: page ID */
                __('The page selected before (#%d) is no longer published: visitors see the success message, and saving this form clears it.', 'lcmt-dev-mailer'),
                $selected
            )) . '</p>';
        }

        echo '<p class="description">' . esc_html__('Optional. After a successful submission, visitors are taken to this page instead of seeing the success message. On a multilingual site, its translation in the visitor\'s language is used when there is one.', 'lcmt-dev-mailer') . '</p>';
        echo '</td>';
        echo '</tr>';
    }

    /**
     * Whether submissions of this form are saved. On unless turned off, so
     * templates created before the option existed save theirs too.
     */
    public static function storesSubmissions(int $postId): bool
    {
        return get_post_meta($postId, self::FIELD_STORE_SUBMISSIONS, true) !== '0';
    }

    private static function renderSyntaxReference(): void
    {
        ?>
        <div class="lcmt-syntax-ref" style="margin-top: 20px; background: #f9f9f9; border: 1px solid #ddd; border-radius: 4px; padding: 16px;">
            <h3 style="margin: 0 0 12px; font-size: 14px; cursor: pointer;" onclick="this.parentElement.classList.toggle('lcmt-syntax-ref--open')">
                <?php esc_html_e('Placeholder syntax reference', 'lcmt-dev-mailer'); ?> <span style="font-weight: normal; color: #888;">&#9660;</span>
            </h3>
            <div class="lcmt-syntax-ref__body" style="display: none;">

                <table class="widefat fixed striped" style="font-size: 12px; margin-bottom: 12px;">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Syntax', 'lcmt-dev-mailer'); ?></th>
                            <th><?php esc_html_e('Result', 'lcmt-dev-mailer'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr><td><code>[name]</code></td><td><?php esc_html_e('Optional field, type text', 'lcmt-dev-mailer'); ?></td></tr>
                        <tr><td><code>[name*]</code></td><td><?php esc_html_e('Required field, type text', 'lcmt-dev-mailer'); ?></td></tr>
                        <tr><td><code>[name type]</code></td><td><?php esc_html_e('Optional field with explicit type', 'lcmt-dev-mailer'); ?></td></tr>
                        <tr><td><code>[name* type]</code></td><td><?php esc_html_e('Required field with explicit type', 'lcmt-dev-mailer'); ?></td></tr>
                    </tbody>
                </table>

                <p style="margin: 0 0 8px; font-weight: 600; font-size: 12px;"><?php esc_html_e('Available types:', 'lcmt-dev-mailer'); ?></p>
                <table class="widefat fixed striped" style="font-size: 12px; margin-bottom: 12px;">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Type', 'lcmt-dev-mailer'); ?></th>
                            <th><?php esc_html_e('HTML output', 'lcmt-dev-mailer'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr><td><code>text</code></td><td><code>&lt;input type="text"&gt;</code></td></tr>
                        <tr><td><code>email</code></td><td><code>&lt;input type="email"&gt;</code></td></tr>
                        <tr><td><code>tel</code> / <code>phone</code></td><td><code>&lt;input type="tel"&gt;</code></td></tr>
                        <tr><td><code>url</code></td><td><code>&lt;input type="url"&gt;</code></td></tr>
                        <tr><td><code>number</code></td><td><code>&lt;input type="number"&gt;</code></td></tr>
                        <tr><td><code>password</code></td><td><code>&lt;input type="password"&gt;</code></td></tr>
                        <tr><td><code>date</code></td><td><code>&lt;input type="date"&gt;</code></td></tr>
                        <tr><td><code>textarea</code></td><td><code>&lt;textarea&gt;</code></td></tr>
                        <tr><td><code>hidden</code></td><td><code>&lt;input type="hidden"&gt;</code></td></tr>
                        <tr><td><code>file</code></td><td><code>&lt;input type="file"&gt;</code> — <?php esc_html_e('attached to the email; the mail shows the file name. Formats and size: "File uploads" above.', 'lcmt-dev-mailer'); ?></td></tr>
                    </tbody>
                </table>

                <p style="margin: 0 0 8px; font-weight: 600; font-size: 12px;"><?php esc_html_e('Example content:', 'lcmt-dev-mailer'); ?></p>
                <pre style="background: #fff; border: 1px solid #ddd; padding: 10px; font-size: 12px; margin: 0; white-space: pre-wrap;">Hello [firstname*],

Thank you for your message.

Email: [email* email]
Phone: [phone tel]
Company: [company]

Message:
[message* textarea]</pre>

                <p style="margin: 8px 0 0; font-size: 11px; color: #666;">
                    <?php esc_html_e('Built-in placeholders (always available): [currentUserLink], [currentUserEmail], [submissionLanguage]', 'lcmt-dev-mailer'); ?>
                </p>
                <p style="margin: 4px 0 0; font-size: 11px; color: #666;">
                    <code>[submissionLanguage]</code> <?php esc_html_e('the language of the page the form was sent from, e.g. English; empty without Polylang.', 'lcmt-dev-mailer'); ?>
                </p>
            </div>
        </div>
        <style>
            .lcmt-syntax-ref--open .lcmt-syntax-ref__body { display: block !important; }
            .lcmt-syntax-ref--open h3 span { transform: rotate(180deg); display: inline-block; }
        </style>
        <?php
    }

    /**
     * Get a mail field value by post ID and field name.
     * Falls back to old ACF meta keys for backwards compatibility.
     */
    public static function get(int $postId, string $field): string
    {
        $metaKey = match ($field) {
            'key'      => self::FIELD_KEY,
            'to'       => self::FIELD_TO,
            'reply_to' => self::FIELD_REPLY_TO,
            'subject'  => self::FIELD_SUBJECT,
            'content'  => self::FIELD_CONTENT,
            default    => '',
        };

        if (!$metaKey) {
            return '';
        }

        $value = get_post_meta($postId, $metaKey, true);

        // Fallback: read from ACF meta keys for existing posts
        if (empty($value) && $field !== 'key') {
            $value = get_post_meta($postId, $field, true);
        }

        return $value ?: '';
    }
}
