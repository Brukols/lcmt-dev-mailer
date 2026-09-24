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

require_once dirname(__DIR__) . '/includes/field-parser.php';
require_once dirname(__DIR__) . '/includes/field-validator.php';
require_once dirname(__DIR__) . '/includes/captcha-provider.php';
require_once dirname(__DIR__) . '/includes/altcha.php';
