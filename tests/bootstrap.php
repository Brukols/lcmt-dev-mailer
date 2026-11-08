<?php

/**
 * Loads the plugin classes that run without WordPress, with in-memory
 * stand-ins for the few WordPress functions they call.
 *
 * Anything that needs a real WordPress (is_email, sanitize_text_field, REST
 * routes, wp_mail) is left to manual testing on a site.
 */

define('ABSPATH', __DIR__ . '/');
define('AUTH_KEY', 'test-auth-key');
define('SECURE_AUTH_SALT', 'test-secure-auth-salt');

$GLOBALS['lcmt_test_options']    = [];
$GLOBALS['lcmt_test_transients'] = [];
$GLOBALS['lcmt_test_post_meta']  = [];
$GLOBALS['lcmt_test_filters']    = [];
$GLOBALS['lcmt_test_mails']      = [];

function __($text, $domain = 'default')
{
    return $text;
}

function get_option($name, $default = false)
{
    return $GLOBALS['lcmt_test_options'][$name] ?? $default;
}

function add_option($name, $value = '', $deprecated = '', $autoload = null)
{
    if (array_key_exists($name, $GLOBALS['lcmt_test_options'])) {
        return false;
    }

    $GLOBALS['lcmt_test_options'][$name] = $value;

    return true;
}

function update_option($name, $value, $autoload = null)
{
    $GLOBALS['lcmt_test_options'][$name] = $value;

    return true;
}

function delete_option($name)
{
    unset($GLOBALS['lcmt_test_options'][$name]);

    return true;
}

function get_bloginfo($show = '')
{
    return 'Test &amp; site';
}

function wp_specialchars_decode($text, $quoteStyle = ENT_NOQUOTES)
{
    return htmlspecialchars_decode((string) $text, $quoteStyle);
}

function wp_mail($to, $subject, $message)
{
    $GLOBALS['lcmt_test_mails'][] = compact('to', 'subject', 'message');

    return true;
}

function admin_url($path = '')
{
    return 'https://example.test/wp-admin/' . $path;
}

function add_query_arg($args, $url)
{
    return $args === [] ? $url : $url . '&' . http_build_query($args);
}

function get_transient($name)
{
    return $GLOBALS['lcmt_test_transients'][$name] ?? false;
}

function set_transient($name, $value, $expiration = 0)
{
    $GLOBALS['lcmt_test_transients'][$name] = $value;

    return true;
}

/**
 * Returns the value set in $GLOBALS['lcmt_test_filters'][$hook], if any,
 * else the value passed in.
 */
function apply_filters($hook, $value, ...$args)
{
    return $GLOBALS['lcmt_test_filters'][$hook] ?? $value;
}

function sanitize_key($key)
{
    return preg_replace('/[^a-z0-9_\-]/', '', strtolower((string) $key));
}

function get_post_meta($postId, $key = '', $single = false)
{
    return $GLOBALS['lcmt_test_post_meta'][$postId][$key] ?? '';
}

function delete_post_meta($postId, $key)
{
    unset($GLOBALS['lcmt_test_post_meta'][$postId][$key]);

    return true;
}

define('MINUTE_IN_SECONDS', 60);
define('DAY_IN_SECONDS', 86400);

class WP_Error
{
    public function __construct(public $code = '', public $message = '') {}
}

function esc_attr($text)
{
    return htmlspecialchars((string) $text, ENT_QUOTES);
}

function esc_html($text)
{
    return htmlspecialchars((string) $text, ENT_QUOTES);
}

function esc_html__($text, $domain = 'default')
{
    return esc_html($text);
}

function esc_url($url)
{
    return (string) $url;
}

function wp_kses($text, $allowed)
{
    return (string) $text;
}

function is_wp_error($thing)
{
    return $thing instanceof WP_Error;
}

function home_url($path = '')
{
    return 'https://example.test' . $path;
}

function wp_json_encode($data)
{
    return json_encode($data);
}

function wp_parse_url($url, $component = -1)
{
    return parse_url($url, $component);
}

function wp_remote_retrieve_response_code($response)
{
    return $response['response']['code'] ?? '';
}

function wp_remote_retrieve_body($response)
{
    return $response['body'] ?? '';
}

function delete_transient($name)
{
    unset($GLOBALS['lcmt_test_transients'][$name]);

    return true;
}

require_once dirname(__DIR__) . '/includes/post-type.php';
require_once dirname(__DIR__) . '/includes/field-parser.php';
require_once dirname(__DIR__) . '/includes/meta-fields.php';
require_once dirname(__DIR__) . '/includes/request-language.php';
require_once dirname(__DIR__) . '/includes/mail-language.php';
require_once dirname(__DIR__) . '/includes/confirmation-page.php';
require_once dirname(__DIR__) . '/includes/settings.php';
require_once dirname(__DIR__) . '/includes/success-message.php';
require_once dirname(__DIR__) . '/includes/date-value.php';
require_once dirname(__DIR__) . '/includes/field-validator.php';
require_once dirname(__DIR__) . '/includes/submission-data.php';
require_once dirname(__DIR__) . '/includes/submission-context.php';
require_once dirname(__DIR__) . '/includes/channel-classifier.php';
require_once dirname(__DIR__) . '/includes/secret-box.php';
require_once dirname(__DIR__) . '/includes/unreadable-secrets.php';
require_once dirname(__DIR__) . '/includes/captcha-settings.php';
require_once dirname(__DIR__) . '/includes/captcha-provider.php';
require_once dirname(__DIR__) . '/includes/altcha.php';
require_once dirname(__DIR__) . '/includes/turnstile.php';
require_once dirname(__DIR__) . '/includes/captcha.php';
require_once dirname(__DIR__) . '/includes/admin-mail-sender.php';
require_once dirname(__DIR__) . '/includes/form-renderer.php';
require_once dirname(__DIR__) . '/includes/privacy.php';
require_once dirname(__DIR__) . '/includes/submission-csv.php';
require_once dirname(__DIR__) . '/includes/retention.php';
require_once dirname(__DIR__) . '/includes/user-agent.php';
