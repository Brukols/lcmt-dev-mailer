<?php

namespace LcmtDevMailer;

if (!defined('ABSPATH')) {
    exit;
}

class TemplateLoader
{
    /**
     * Render the base email HTML template.
     *
     * Looks for a theme override first at:
     *   {theme}/lcmt-dev-mailer/mail-base.php
     *
     * Falls back to the plugin default at:
     *   lcmt-dev-mailer/templates/mail-base.php
     *
     * @param string $subject
     * @param string $content  Already-replaced HTML body.
     * @return string  Full HTML email string.
     */
    public static function render(string $subject, string $content): string
    {
        $template = self::locate('mail-base.php');

        ob_start();

        $args = [
            'subject' => $subject,
            'content' => $content,
        ];

        include $template;

        return ob_get_clean();
    }

    /**
     * Locate a template file.
     * Theme override directory: {theme}/lcmt-dev-mailer/
     */
    public static function locate(string $fileName): string
    {
        $themeFile = get_stylesheet_directory() . '/lcmt-dev-mailer/' . $fileName;

        if (file_exists($themeFile)) {
            return $themeFile;
        }

        return LCMT_MAILER_PATH . 'templates/' . $fileName;
    }
}
