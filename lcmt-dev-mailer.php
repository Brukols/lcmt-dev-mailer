<?php

/**
 * Plugin Name: LCMT Mailer
 * Plugin URI: https://github.com/Brukols/lcmt-dev-mailer
 * Description: Developer-oriented mail engine. Create mail templates in WP admin, auto-generates REST endpoints, form rendering, validation and TypeScript types.
 * Version: 2.1.0
 * Author: Amaury Lecomte
 * Author URI:
 * Text Domain: lcmt-dev-mailer
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 8.1
 */

if (!defined('ABSPATH')) {
    exit;
}

define('LCMT_MAILER_PATH', plugin_dir_path(__FILE__));
define('LCMT_MAILER_URL', plugin_dir_url(__FILE__));

/**
 * Get a content hash for cache-busting an asset file.
 */
function lcmt_mailer_asset_version(string $file): string
{
    $path = LCMT_MAILER_PATH . $file;
    return file_exists($path) ? substr(md5_file($path), 0, 8) : '0';
}

require_once LCMT_MAILER_PATH . 'includes/post-type.php';
require_once LCMT_MAILER_PATH . 'includes/meta-fields.php';
require_once LCMT_MAILER_PATH . 'includes/field-parser.php';
require_once LCMT_MAILER_PATH . 'includes/field-validator.php';
require_once LCMT_MAILER_PATH . 'includes/template-loader.php';
require_once LCMT_MAILER_PATH . 'includes/mailer.php';
require_once LCMT_MAILER_PATH . 'includes/form-renderer.php';
require_once LCMT_MAILER_PATH . 'includes/form-endpoint.php';
require_once LCMT_MAILER_PATH . 'includes/admin-mail-sender.php';
require_once LCMT_MAILER_PATH . 'includes/settings.php';
require_once LCMT_MAILER_PATH . 'includes/captcha-provider.php';
require_once LCMT_MAILER_PATH . 'includes/captcha.php';
require_once LCMT_MAILER_PATH . 'includes/altcha.php';
require_once LCMT_MAILER_PATH . 'includes/captcha-settings.php';
require_once LCMT_MAILER_PATH . 'includes/updater.php';

// ── Updates from the GitHub releases ──
LcmtDevMailer\Updater::register(__FILE__);

// ── Translations ──
add_action('init', function () {
    load_plugin_textdomain('lcmt-dev-mailer', false, basename(LCMT_MAILER_PATH) . '/languages');
});

// ── Post type & fields ──
add_action('init', ['LcmtDevMailer\\PostType', 'register']);
add_action('add_meta_boxes', ['LcmtDevMailer\\MetaFields', 'addMetaBox']);
add_action('save_post', ['LcmtDevMailer\\MetaFields', 'save']);

// ── Admin UI ──
add_action('admin_menu', ['LcmtDevMailer\\Settings', 'addSubmenu']);
add_action('admin_init', ['LcmtDevMailer\\Settings', 'registerSettings']);
add_action('admin_menu', ['LcmtDevMailer\\CaptchaSettings', 'addSubmenu']);
add_action('admin_init', ['LcmtDevMailer\\CaptchaSettings', 'registerSettings']);
add_action('admin_enqueue_scripts', ['LcmtDevMailer\\Settings', 'enqueueAdminAssets']);
add_action('add_meta_boxes', ['LcmtDevMailer\\AdminMailSender', 'addMetaBox']);
add_action('wp_ajax_lcmt_send_test_mail', ['LcmtDevMailer\\AdminMailSender', 'handleSendTestMail']);
add_action('wp_ajax_lcmt_generate_form_template', ['LcmtDevMailer\\AdminMailSender', 'handleGenerateFormTemplate']);
add_filter('manage_mail_posts_columns', ['LcmtDevMailer\\AdminMailSender', 'addColumns']);
add_action('manage_mail_posts_custom_column', ['LcmtDevMailer\\AdminMailSender', 'populateColumns'], 10, 2);
add_action('admin_notices', ['LcmtDevMailer\\AdminMailSender', 'adminNotice']);
add_action('admin_post_' . LcmtDevMailer\Altcha::REGENERATE_ACTION, ['LcmtDevMailer\\Altcha', 'handleRegenerateKey']);

// ── Frontend: register assets early, enqueue on shortcode render ──
add_action('wp_enqueue_scripts', ['LcmtDevMailer\\FormRenderer', 'registerAssets']);
add_shortcode('lcmt-form', ['LcmtDevMailer\\FormRenderer', 'shortcode']);
add_action('rest_api_init', ['LcmtDevMailer\\FormEndpoint', 'register']);
add_action('rest_api_init', ['LcmtDevMailer\\Captcha', 'registerRoutes']);
