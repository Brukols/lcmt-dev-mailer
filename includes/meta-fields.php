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
                    <?php esc_html_e('Built-in placeholders (always available): [currentUserLink], [currentUserEmail]', 'lcmt-dev-mailer'); ?>
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
