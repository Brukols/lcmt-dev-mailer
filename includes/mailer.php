<?php

namespace LcmtDevMailer;

if (!defined('ABSPATH')) {
    exit;
}

class Mailer
{
    /**
     * Send an email by mail key.
     *
     * @param string      $key             The mail key (e.g. 'contact').
     * @param array       $placeholders    Key/value pairs — keys can be bare ('firstname') or bracketed ('[firstname]').
     * @param string|null $forcedLanguage  Force a specific language slug (Polylang).
     * @return bool
     */
    public static function sendByKey(string $key, array $placeholders, ?string $forcedLanguage = null): bool
    {
        $post = self::getPostByKey($key, $forcedLanguage);

        if (!$post) {
            return false;
        }

        $form = self::buildForm($post, $placeholders, $forcedLanguage);

        if (!$form) {
            return false;
        }

        return self::sendWithBaseTemplate($form);
    }

    /**
     * Send an email using a pre-built form array (to, subject, content).
     */
    public static function sendWithBaseTemplate(array $form): bool
    {
        $html = TemplateLoader::render($form['subject'], $form['content']);

        $to = self::normalizeRecipients($form['to']);

        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . self::getFromHeader(),
        ];

        if (!empty($form['reply_to'])) {
            $headers[] = 'Reply-To: ' . $form['reply_to'];
        }

        $headers = apply_filters('lcmt_mailer_headers', $headers, $form);

        return wp_mail($to, $form['subject'], $html, $headers);
    }

    /**
     * Find the mail post by its key.
     */
    public static function getPostByKey(string $key, ?string $forcedLanguage = null): ?\WP_Post
    {
        $defaultLanguage = apply_filters('lcmt_mailer_default_language', 'fr');

        $args = [
            'post_type'      => PostType::SLUG,
            'posts_per_page' => 1,
            'post_status'    => 'publish',
            'meta_key'       => MetaFields::FIELD_KEY,
            'meta_value'     => $key,
            'lang'           => $forcedLanguage ?? $defaultLanguage,
        ];

        $posts = get_posts($args);

        return $posts[0] ?? null;
    }

    /**
     * Build the form array (to, subject, content) with placeholders replaced.
     */
    public static function buildForm(\WP_Post $post, array $placeholders, ?string $forcedLanguage = null): ?array
    {
        $from = apply_filters('lcmt_mailer_from_email', '');

        $to      = MetaFields::get($post->ID, 'to');
        $replyTo = MetaFields::get($post->ID, 'reply_to');
        $subject = MetaFields::get($post->ID, 'subject');
        $content = MetaFields::get($post->ID, 'content');

        // Built-in placeholders
        $user = wp_get_current_user();
        $placeholders['[currentUserLink]']  = admin_url('user-edit.php?user_id=' . $user->ID);
        $placeholders['[currentUserEmail]'] = $user->user_email;

        // Normalize placeholders: ensure all placeholder variants are replaced
        // A field "phone" with type "tel" can appear as: [phone], [phone*], [phone tel], [phone* tel]
        $normalized = [];
        $parsedFields = FieldParser::parse($to, $replyTo, $subject, $content);

        foreach ($placeholders as $k => $v) {
            $bare = trim($k, '[]* ');
            $normalized['[' . $bare . ']']  = $v;
            $normalized['[' . $bare . '*]'] = $v;

            // Also add variants with the type suffix if this field has an explicit type
            if (isset($parsedFields[$bare]) && $parsedFields[$bare]['type'] !== 'text') {
                $type = $parsedFields[$bare]['type'];
                $normalized['[' . $bare . ' ' . $type . ']']  = $v;
                $normalized['[' . $bare . '* ' . $type . ']'] = $v;
            }
        }

        // Replace placeholders in "to" and "reply-to" (may contain [email*] etc.)
        foreach ($normalized as $k => $v) {
            $to = str_replace($k, $v, $to);
            $replyTo = str_replace($k, $v, $replyTo);
        }

        // Detect recipient language and load translation if available
        $translated = self::maybeTranslateForRecipient($to, $post->ID, $forcedLanguage, $subject, $content);
        $to      = $translated['to'];
        $subject = $translated['subject'];
        $content = $translated['content'];

        // Replace placeholders in subject and content
        foreach ($normalized as $k => $v) {
            $subject = str_replace($k, $v, $subject);
            $content = str_replace($k, $v, $content);
        }

        // Convert newlines to HTML paragraphs for email rendering
        $content = wpautop($content);

        return [
            'to'       => $to,
            'reply_to' => $replyTo,
            'from'     => $from,
            'subject'  => $subject,
            'content'  => $content,
        ];
    }

    /**
     * Send an error notification email to the admin.
     */
    public static function sendErrorEmail(string $subject, array $args = []): bool
    {
        $errorEmail = get_option('error_admin_email');

        if (empty($errorEmail)) {
            $errorEmail = get_option('admin_email');
        }

        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . self::getFromHeader(),
        ];

        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
        $traceDetails = '';

        foreach ($backtrace as $trace) {
            $function = $trace['function'] ?? '';
            $class    = $trace['class'] ?? '';
            $type     = $trace['type'] ?? '';
            $file     = $trace['file'] ?? __FILE__;
            $line     = $trace['line'] ?? __LINE__;

            $traceDetails .= "Function: $class$type$function, File: $file, Line: $line<br>";
        }

        $content = '';
        foreach ($args as $key => $value) {
            $content .= esc_html($key) . ': ' . esc_html($value) . '<br>';
        }

        $content .= '<br><br>' . $traceDetails;

        return wp_mail($errorEmail, $subject, $content, $headers);
    }

    // ─── Private helpers ────────────────────────────────────────

    private static function normalizeRecipients(string|array $to): string|array
    {
        if (is_array($to)) {
            return $to;
        }

        if (strpos($to, ',') !== false) {
            return array_map('trim', explode(',', $to));
        }

        return $to;
    }

    private static function getFromHeader(): string
    {
        $name = get_bloginfo('name');

        return $name . ' <' . Settings::getFromEmail() . '>';
    }

    private static function maybeTranslateForRecipient(string $to, int $postId, ?string $forcedLanguage, string $subject, string $content): array
    {
        $result = ['to' => $to, 'subject' => $subject, 'content' => $content];

        if ($forcedLanguage) {
            return $result;
        }

        if (!function_exists('pll_get_post')) {
            return $result;
        }

        $userLanguage = apply_filters('lcmt_mailer_user_language', null, $to);

        if (empty($userLanguage)) {
            return $result;
        }

        $translatedPostId = pll_get_post($postId, $userLanguage);

        if ($translatedPostId && $translatedPostId !== $postId) {
            $translatedSubject = MetaFields::get($translatedPostId, 'subject');
            $translatedContent = MetaFields::get($translatedPostId, 'content');

            if ($translatedSubject) {
                $result['subject'] = $translatedSubject;
            }
            if ($translatedContent) {
                $result['content'] = $translatedContent;
            }
        }

        return $result;
    }
}
