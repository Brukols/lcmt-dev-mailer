<?php

namespace LcmtDevMailer;

if (!defined('ABSPATH')) {
    exit;
}

class FormRenderer
{
    /**
     * Where a form file wants the spam protection widget, printed by
     * lcmt_mailer_captcha().
     */
    public const CAPTCHA_SLOT = '<!--lcmt-captcha-->';

    /**
     * Opening tag of a submit control: <button> without a type or with
     * type="submit", or <input type="submit">, any quotes, any case.
     *
     * The scan skips (SKIP/FAIL) comments, script/style/template/textarea
     * elements and every other tag, and reads attributes as quoted runs, so
     * a "<button>" or "type=submit" inside a comment, raw text or an
     * attribute value is never taken for a control.
     */
    private const ATTRS = '(?:[^>"\']|"[^"]*"|\'[^\']*\')*';

    private const SUBMIT_PATTERN = '/<!--[\s\S]*?-->(*SKIP)(*F)'
        . '|<(script|style|template|textarea)\b[\s\S]*?<\/\1\s*>(*SKIP)(*F)'
        . '|<button(?=[\s>\/])(?!' . self::ATTRS . '(?<![\w-])type\s*=)' . self::ATTRS . '>'
        . '|<(?:button|input)(?=[\s\/])' . self::ATTRS . '(?<![\w-])type\s*=\s*(?<q>["\']?)submit\k<q>(?=[\s\/>])' . self::ATTRS . '>'
        . '|<[a-z][^\s\/>]*' . self::ATTRS . '>(*SKIP)(*F)/i';

    /**
     * Render a form by its mail key.
     *
     * Looks for the form file at: {theme}/forms/{key}.php
     * Wraps the developer's markup with a <form> tag pointing to the REST endpoint.
     *
     * @param string $key   The mail key (e.g. 'contact').
     * @param array  $atts  Optional shortcode attributes.
     * @return string        HTML output.
     */
    public static function render(string $key, array $atts = []): string
    {
        $mailPost = Mailer::getPostByKey($key);

        if (!$mailPost) {
            return '<!-- lcmt-mailer: no mail post found for key "' . esc_html($key) . '" -->';
        }

        $formFile = self::locateFormFile($key);

        if (!$formFile) {
            return '<!-- lcmt-mailer: form file not found at forms/' . esc_html($key) . '.php -->';
        }

        // Enqueue the form handler script
        self::enqueueScript();

        $formId   = 'lcmt-form-' . $key;
        $endpoint = '/wp-json/lcmt-mailer/v1/forms/' . $key;
        $nonce    = wp_create_nonce('wp_rest');

        // Capture the developer's form inner HTML
        ob_start();
        include $formFile;
        $innerHtml = ob_get_clean();

        // Auto-include the widget of the spam protection this template uses
        $innerHtml = self::placeWidget($innerHtml, Captcha::widget($mailPost->ID));

        // Auto-include the plugin's loader overlay
        $innerHtml .= '<div class="lcmt-loader" aria-hidden="true"><div class="lcmt-loader__spinner"></div></div>';

        // Check if developer already included a <form> tag
        if (preg_match('/<form[\s>]/i', $innerHtml)) {
            // Inject our attributes into the existing form tag
            $innerHtml = preg_replace(
                '/<form([\s>])/i',
                '<form id="' . esc_attr($formId) . '" data-lcmt-endpoint="' . esc_attr($endpoint) . '" data-lcmt-nonce="' . esc_attr($nonce) . '"$1',
                $innerHtml,
                1
            );
            return self::collapseWhitespace($innerHtml);
        }

        // Wrap with our form tag
        $html  = '<form id="' . esc_attr($formId) . '" data-lcmt-endpoint="' . esc_attr($endpoint) . '" data-lcmt-nonce="' . esc_attr($nonce) . '">';
        $html .= $innerHtml;
        $html .= '</form>';

        return self::collapseWhitespace($html);
    }

    /**
     * Put the spam protection widget in the form's markup: in place of the
     * first CAPTCHA_SLOT (the others are removed), else right before the
     * first submit control, else at the end.
     *
     * @param string $widget Empty when no protection is active.
     */
    public static function placeWidget(string $html, string $widget): string
    {
        $slot = strpos($html, self::CAPTCHA_SLOT);

        if ($slot !== false) {
            $html = substr_replace($html, $widget, $slot, strlen(self::CAPTCHA_SLOT));

            return str_replace(self::CAPTCHA_SLOT, '', $html);
        }

        if ($widget === '') {
            return $html;
        }

        if (preg_match(self::SUBMIT_PATTERN, $html, $match, PREG_OFFSET_CAPTURE)) {
            return substr_replace($html, $widget, $match[0][1], 0);
        }

        return $html . $widget;
    }

    /**
     * Register assets early (called on wp_enqueue_scripts).
     * Assets are only enqueued when a form is actually rendered.
     */
    public static function registerAssets(): void
    {
        wp_register_style(
            'lcmt-form-handler',
            LCMT_MAILER_URL . 'assets/dist/form-handler.css',
            [],
            lcmt_mailer_asset_version('assets/dist/form-handler.css')
        );

        wp_register_script(
            'lcmt-form-handler',
            LCMT_MAILER_URL . 'assets/dist/form-handler.js',
            [],
            lcmt_mailer_asset_version('assets/dist/form-handler.js'),
            true
        );
    }

    /**
     * Enqueue the frontend form handler assets (once).
     */
    private static function enqueueScript(): void
    {
        if (wp_script_is('lcmt-form-handler', 'enqueued')) {
            return;
        }

        wp_enqueue_style('lcmt-form-handler');
        wp_enqueue_script('lcmt-form-handler');

        wp_localize_script('lcmt-form-handler', 'lcmtMailerFront', [
            'colors' => Settings::getAlertColors(),
            'textColors' => Settings::getAlertTextColors(),
            // The page language, sent back as `lang` so the answer and the mail use it.
            'lang' => function_exists('pll_current_language') ? (string) pll_current_language() : '',
            'i18n' => [
                'requiredFields' => __('Please fill in all required fields.', 'lcmt-dev-mailer'),
                'genericError'   => __('An error occurred.', 'lcmt-dev-mailer'),
                'captchaFailed'  => __('Security verification failed. Please try again.', 'lcmt-dev-mailer'),
            ],
        ]);
    }

    /**
     * Shortcode handler: [lcmt-form key="contact"]
     */
    public static function shortcode(array $atts): string
    {
        $atts = shortcode_atts(['key' => ''], $atts, 'lcmt-form');

        if (empty($atts['key'])) {
            return '<!-- lcmt-mailer: missing key attribute -->';
        }

        return self::render($atts['key'], $atts);
    }

    /**
     * Collapse newlines in rendered HTML so wpautop cannot inject <br> tags.
     */
    private static function collapseWhitespace(string $html): string
    {
        return preg_replace('/\s*\n\s*/', '', $html);
    }

    /**
     * Locate the form PHP file in the theme.
     */
    private static function locateFormFile(string $key): ?string
    {
        $file = get_stylesheet_directory() . '/forms/' . $key . '.php';

        if (file_exists($file)) {
            return $file;
        }

        // Fallback to parent theme
        $parentFile = get_template_directory() . '/forms/' . $key . '.php';

        if ($parentFile !== $file && file_exists($parentFile)) {
            return $parentFile;
        }

        return null;
    }
}
