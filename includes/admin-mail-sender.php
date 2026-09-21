<?php

namespace LcmtDevMailer;

if (!defined('ABSPATH')) {
    exit;
}

class AdminMailSender
{
    public static function addMetaBox(): void
    {
        add_meta_box(
            'lcmt_mail_sender_metabox',
            __('Actions & Usage', 'lcmt-dev-mailer'),
            [self::class, 'renderMetaBox'],
            PostType::SLUG,
            'side',
            'high'
        );
    }

    public static function renderMetaBox(\WP_Post $post): void
    {
        wp_nonce_field('lcmt_mail_sender', 'lcmt_mail_sender_nonce');

        $key     = MetaFields::get($post->ID, 'key');
        $content = MetaFields::get($post->ID, 'content');
        $to      = MetaFields::get($post->ID, 'to');
        $subject = MetaFields::get($post->ID, 'subject');

        $currentLang = '';
        if (function_exists('pll_get_post_language')) {
            $currentLang = pll_get_post_language($post->ID);
        }

        echo '<div id="lcmt-mail-sender-container">';

        if ($key) {
            $fields   = FieldParser::parse($to, $subject, $content);
            $formFile = get_stylesheet_directory() . '/forms/' . $key . '.php';
            $formExists = file_exists($formFile);

            // ── Key & language ──
            echo '<p><strong>Key:</strong> <code>' . esc_html($key) . '</code></p>';
            if ($currentLang) {
                echo '<p><strong>' . esc_html__('Language:', 'lcmt-dev-mailer') . '</strong> ' . esc_html($currentLang) . '</p>';
            }

            // ── Shortcode / PHP / REST ──
            echo '<hr>';
            echo '<p><strong>' . esc_html__('Shortcode:', 'lcmt-dev-mailer') . '</strong></p>';
            echo '<input type="text" readonly value="' . esc_attr('[lcmt-form key="' . $key . '"]') . '" class="large-text code" onclick="this.select()" />';

            echo '<p style="margin-top: 8px;"><strong>PHP:</strong></p>';
            echo '<input type="text" readonly value="' . esc_attr('<?php echo \\LcmtDevMailer\\FormRenderer::render(\'' . $key . '\'); ?>') . '" class="large-text code" onclick="this.select()" />';

            echo '<p style="margin-top: 8px;"><strong>REST:</strong></p>';
            echo '<input type="text" readonly value="POST /wp-json/lcmt-mailer/v1/forms/' . esc_attr($key) . '" class="large-text code" onclick="this.select()" />';

            // ── Field reference table ──
            if (!empty($fields)) {
                echo '<hr>';
                echo '<p><strong>' . esc_html__('Form fields:', 'lcmt-dev-mailer') . '</strong></p>';
                echo '<table class="widefat fixed striped" style="font-size: 12px;">';
                echo '<thead><tr>';
                echo '<th>' . esc_html__('name=""', 'lcmt-dev-mailer') . '</th>';
                echo '<th style="width: 70px;">' . esc_html__('Type', 'lcmt-dev-mailer') . '</th>';
                echo '<th style="width: 30px; text-align: center;">*</th>';
                echo '</tr></thead><tbody>';

                foreach ($fields as $field) {
                    echo '<tr>';
                    echo '<td><code>' . esc_html($field['name']) . '</code></td>';
                    echo '<td><code>' . esc_html($field['type']) . '</code></td>';
                    echo '<td style="text-align: center;">' . ($field['required'] ? '<span style="color: red;">*</span>' : '') . '</td>';
                    echo '</tr>';
                }

                echo '</tbody></table>';
                echo '<p class="description" style="margin-top: 4px;">' . esc_html__('Use these as name="" attributes on your form inputs.', 'lcmt-dev-mailer') . '</p>';
            }

            // ── Form file status + generate button ──
            echo '<hr>';
            echo '<p><strong>' . esc_html__('Form file:', 'lcmt-dev-mailer') . '</strong></p>';
            echo '<code>forms/' . esc_html($key) . '.php</code> ';

            if ($formExists) {
                echo '<span style="color: green;">&#10003;</span>';
            } else {
                echo '<span style="color: red;">&#10007; ' . esc_html__('missing', 'lcmt-dev-mailer') . '</span>';

                if (!empty($fields)) {
                    echo '<div style="margin-top: 8px;">';
                    echo '<button type="button" id="lcmt-generate-template" class="button button-secondary" style="width: 100%;">';
                    echo esc_html__('Generate form template', 'lcmt-dev-mailer');
                    echo '</button>';
                    echo '<div id="lcmt-generate-status" style="margin-top: 6px;"></div>';
                    echo '</div>';
                }
            }

        } else {
            echo '<p><em>' . esc_html__('Save the post with a key to see usage info.', 'lcmt-dev-mailer') . '</em></p>';
        }

        // ── Test email ──
        echo '<hr>';
        echo '<p><label for="lcmt_test_email_recipient"><strong>' . esc_html__('Test email:', 'lcmt-dev-mailer') . '</strong></label></p>';
        echo '<input type="email" id="lcmt_test_email_recipient" name="lcmt_test_email_recipient" style="width: 100%; margin-bottom: 10px;" placeholder="test@example.com" />';

        // Auto-generate one input per parsed field
        if (!empty($fields)) {
            echo '<p style="margin-top: 6px;"><strong>' . esc_html__('Test values:', 'lcmt-dev-mailer') . '</strong></p>';
            foreach ($fields as $field) {
                $placeholder = 'test_' . $field['name'];
                if ($field['name'] === 'email') {
                    $placeholder = 'test@example.com';
                }
                echo '<label style="display: block; font-size: 12px; margin-top: 6px; color: #555;">' . esc_html($field['name']);
                if ($field['required']) {
                    echo ' <span style="color: red;">*</span>';
                }
                echo '</label>';
                echo '<input type="text" class="lcmt-test-field" data-field-name="' . esc_attr($field['name']) . '" style="width: 100%; margin-bottom: 2px;" placeholder="' . esc_attr($placeholder) . '" value="' . esc_attr($placeholder) . '" />';
            }
        }
        echo '<hr>';

        echo '<button type="button" id="lcmt-send-test-email" class="button button-primary" style="width: 100%; margin-bottom: 10px;">' . esc_html__('Send Test Email', 'lcmt-dev-mailer') . '</button>';

        echo '<div id="lcmt-mail-sender-status" style="margin-top: 10px;"></div>';
        echo '</div>';

        self::enqueueScripts($post->ID, $currentLang);
    }

    private static function enqueueScripts(int $postId, string $currentLang): void
    {
        $handle = 'lcmt-admin-test-mail';

        wp_enqueue_script(
            $handle,
            LCMT_MAILER_URL . 'assets/dist/admin-test-mail.js',
            [],
            lcmt_mailer_asset_version('assets/dist/admin-test-mail.js'),
            true
        );

        wp_enqueue_script(
            'lcmt-admin-generate-template',
            LCMT_MAILER_URL . 'assets/dist/admin-generate-template.js',
            [],
            lcmt_mailer_asset_version('assets/dist/admin-generate-template.js'),
            true
        );

        $localized = [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('lcmt_mail_sender'),
            'postId'  => $postId,
            'lang'    => $currentLang,
            'i18n'    => [
                'enterEmail'       => __('Please enter a recipient email.', 'lcmt-dev-mailer'),
                'invalidEmail'     => __('Please enter a valid email.', 'lcmt-dev-mailer'),
                'sending'          => __('Sending...', 'lcmt-dev-mailer'),
                'sendTestEmail'    => __('Send Test Email', 'lcmt-dev-mailer'),
                'connectionError'  => __('Connection error', 'lcmt-dev-mailer'),
                'generating'       => __('Generating...', 'lcmt-dev-mailer'),
                'generateTemplate' => __('Generate form template', 'lcmt-dev-mailer'),
                'confirmGenerate'  => __('This will create the form template file. Continue?', 'lcmt-dev-mailer'),
            ],
        ];

        wp_localize_script($handle, 'lcmtMailerAdmin', $localized);
    }

    // ── AJAX: Send test email ──

    public static function handleSendTestMail(): void
    {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'lcmt_mail_sender')) {
            wp_send_json_error(__('Security check failed', 'lcmt-dev-mailer'));
        }

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(__('Insufficient permissions', 'lcmt-dev-mailer'));
        }

        $postId    = intval($_POST['post_id'] ?? 0);
        $recipient = sanitize_email($_POST['recipient'] ?? '');
        $lang      = sanitize_text_field($_POST['lang'] ?? '');

        if (!$recipient) {
            wp_send_json_error(__('Email recipient is required', 'lcmt-dev-mailer'));
        }

        $placeholders = [];
        $rawPlaceholders = stripslashes($_POST['placeholders'] ?? '');
        if (!empty($rawPlaceholders)) {
            $placeholders = json_decode($rawPlaceholders, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                wp_send_json_error(__('Invalid JSON in placeholders', 'lcmt-dev-mailer'));
            }
        }

        $post = get_post($postId);
        if (!$post || $post->post_type !== PostType::SLUG) {
            wp_send_json_error(__('Invalid mail post', 'lcmt-dev-mailer'));
        }

        $key = MetaFields::get($postId, 'key');

        if (!$key) {
            wp_send_json_error(__('No key found for this mail post', 'lcmt-dev-mailer'));
        }

        $content = MetaFields::get($postId, 'content');
        $to      = MetaFields::get($postId, 'to');
        $subject = MetaFields::get($postId, 'subject');
        $fields  = FieldParser::parse($to, $subject, $content);

        $defaultPlaceholders = [];
        foreach ($fields as $field) {
            $defaultPlaceholders[$field['name']] = 'test_' . $field['name'];
        }
        $defaultPlaceholders['email'] = $recipient;

        $finalPlaceholders = array_merge($defaultPlaceholders, $placeholders);

        try {
            $form = Mailer::buildForm($post, $finalPlaceholders, $lang ?: null);

            if (!$form) {
                wp_send_json_error(__('Could not build email for key: ', 'lcmt-dev-mailer') . $key);
            }

            $form['to'] = $recipient;

            $result = Mailer::sendWithBaseTemplate($form);

            if ($result) {
                wp_send_json_success(__('Email sent successfully to ', 'lcmt-dev-mailer') . $recipient);
            } else {
                wp_send_json_error(__('Failed to send email', 'lcmt-dev-mailer'));
            }
        } catch (\Exception $e) {
            wp_send_json_error(__('Error: ', 'lcmt-dev-mailer') . $e->getMessage());
        }
    }

    // ── AJAX: Generate form template ──

    public static function handleGenerateFormTemplate(): void
    {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'lcmt_mail_sender')) {
            wp_send_json_error(__('Security check failed', 'lcmt-dev-mailer'));
        }

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(__('Insufficient permissions', 'lcmt-dev-mailer'));
        }

        $postId = intval($_POST['post_id'] ?? 0);
        $post   = get_post($postId);

        if (!$post || $post->post_type !== PostType::SLUG) {
            wp_send_json_error(__('Invalid mail post', 'lcmt-dev-mailer'));
        }

        $key = MetaFields::get($postId, 'key');

        if (!$key) {
            wp_send_json_error(__('No key found for this mail post', 'lcmt-dev-mailer'));
        }

        $formsDir = get_stylesheet_directory() . '/forms';
        $filePath = $formsDir . '/' . $key . '.php';

        if (file_exists($filePath)) {
            wp_send_json_error(__('File already exists: forms/', 'lcmt-dev-mailer') . $key . '.php');
        }

        // Parse fields from all mail fields
        $content = MetaFields::get($postId, 'content');
        $to      = MetaFields::get($postId, 'to');
        $subject = MetaFields::get($postId, 'subject');
        $fields  = FieldParser::parse($to, $subject, $content);

        if (empty($fields)) {
            wp_send_json_error(__('No fields found in the mail content. Add placeholders like [firstname*] first.', 'lcmt-dev-mailer'));
        }

        // Create forms directory if it doesn't exist
        if (!is_dir($formsDir)) {
            wp_mkdir_p($formsDir);
        }

        // Generate the template
        $template = self::buildFormTemplate($key, $fields);

        $written = file_put_contents($filePath, $template);

        if ($written === false) {
            wp_send_json_error(__('Could not write file. Check directory permissions.', 'lcmt-dev-mailer'));
        }

        wp_send_json_success(__('Template created: forms/', 'lcmt-dev-mailer') . $key . '.php');
    }

    /**
     * Build the PHP form template content from parsed fields.
     */
    private static function buildFormTemplate(string $key, array $fields): string
    {
        $lines = [];
        $lines[] = '<?php';
        $lines[] = '/**';
        $lines[] = ' * Form template: ' . $key;
        $lines[] = ' *';
        $lines[] = ' * Auto-generated by LCMT Dev Mailer.';
        $lines[] = ' * Edit this file to customize the form markup.';
        $lines[] = ' *';
        $lines[] = ' * The plugin wraps this with a <form> tag and handles submission automatically.';
        $lines[] = ' * Use [data-lcmt-status] on an element to show status messages.';
        $lines[] = ' */';
        $lines[] = '?>';
        $lines[] = '';
        $lines[] = '<div class="lcmt-form-fields">';

        foreach ($fields as $field) {
            $name     = $field['name'];
            $required = $field['required'];
            $type     = $field['type'];
            $label    = ucfirst(str_replace('_', ' ', $name));
            $reqAttr  = $required ? ' required' : '';
            $reqMark  = $required ? ' <span class="required">*</span>' : '';

            $lines[] = '';
            $lines[] = '    <div class="lcmt-form-field">';
            $lines[] = '        <label for="' . $name . '">' . $label . $reqMark . '</label>';

            if ($type === 'textarea') {
                $lines[] = '        <textarea id="' . $name . '" name="' . $name . '" rows="5"' . $reqAttr . '></textarea>';
            } else {
                $lines[] = '        <input type="' . $type . '" id="' . $name . '" name="' . $name . '"' . $reqAttr . ' />';
            }

            $lines[] = '    </div>';
        }

        $lines[] = '';
        $lines[] = '    <div class="lcmt-form-field">';
        $lines[] = '        <button type="submit">' . __('Submit', 'lcmt-dev-mailer') . '</button>';
        $lines[] = '    </div>';
        $lines[] = '';
        $lines[] = '    <div data-lcmt-status></div>';
        $lines[] = '</div>';
        $lines[] = '';

        return implode("\n", $lines);
    }

    // ── Columns ──

    public static function addColumns(array $columns): array
    {
        $columns['lcmt_key']    = __('Key', 'lcmt-dev-mailer');
        $columns['lcmt_form']   = __('Form file', 'lcmt-dev-mailer');
        $columns['test_email']  = __('Actions', 'lcmt-dev-mailer');
        return $columns;
    }

    public static function populateColumns(string $column, int $postId): void
    {
        switch ($column) {
            case 'lcmt_key':
                $key = MetaFields::get($postId, 'key');
                echo $key ? '<code>' . esc_html($key) . '</code>' : '—';
                break;
            case 'lcmt_form':
                $key = MetaFields::get($postId, 'key');
                if ($key) {
                    $exists = file_exists(get_stylesheet_directory() . '/forms/' . $key . '.php');
                    echo '<code>forms/' . esc_html($key) . '.php</code> ';
                    echo $exists ? '<span style="color: green;">&#10003;</span>' : '<span style="color: red;">&#10007;</span>';
                } else {
                    echo '—';
                }
                break;
            case 'test_email':
                echo '<a href="' . esc_url(get_edit_post_link($postId)) . '" class="button button-small">' . esc_html__('Manage', 'lcmt-dev-mailer') . '</a>';
                break;
        }
    }

    public static function adminNotice(): void
    {
        global $post_type;

        if ($post_type !== PostType::SLUG) {
            return;
        }

        echo '<div class="notice notice-info is-dismissible">';
        echo '<p><strong>' . esc_html__('Tip:', 'lcmt-dev-mailer') . '</strong> ' . esc_html__('You can send test emails directly from the edit page using the "Actions & Usage" panel on the right.', 'lcmt-dev-mailer') . '</p>';
        echo '</div>';
    }
}
