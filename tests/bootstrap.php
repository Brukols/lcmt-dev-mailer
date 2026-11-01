<?php

/**
 * Loads the plugin classes that run without WordPress, with in-memory
 * stand-ins for the few WordPress functions they call.
 *
 * Anything that needs a real WordPress (is_email, sanitize_text_field, REST
 * routes, wp_mail) is left to manual testing on a site.
 */

define('ABSPATH', __DIR__ . '/');

$GLOBALS['lcmt_test_options']    = [];
$GLOBALS['lcmt_test_transients'] = [];
$GLOBALS['lcmt_test_post_meta']  = [];
$GLOBALS['lcmt_test_filters']    = [];

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

require_once dirname(__DIR__) . '/includes/post-type.php';
require_once dirname(__DIR__) . '/includes/field-parser.php';
require_once dirname(__DIR__) . '/includes/meta-fields.php';
require_once dirname(__DIR__) . '/includes/request-language.php';
require_once dirname(__DIR__) . '/includes/mail-language.php';
require_once dirname(__DIR__) . '/includes/confirmation-page.php';
require_once dirname(__DIR__) . '/includes/field-validator.php';
require_once dirname(__DIR__) . '/includes/submission-data.php';
require_once dirname(__DIR__) . '/includes/submission-context.php';
require_once dirname(__DIR__) . '/includes/channel-classifier.php';
require_once dirname(__DIR__) . '/includes/captcha-provider.php';
require_once dirname(__DIR__) . '/includes/altcha.php';
require_once dirname(__DIR__) . '/includes/captcha.php';
require_once dirname(__DIR__) . '/includes/submission-csv.php';
require_once dirname(__DIR__) . '/includes/retention.php';
require_once dirname(__DIR__) . '/includes/user-agent.php';
