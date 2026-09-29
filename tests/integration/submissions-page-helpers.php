<?php

use LcmtDevMailer\SubmissionsPage;

require_once ABSPATH . 'wp-admin/includes/admin.php';

/**
 * Helpers shared by the tests of the Received messages screen.
 */
function lcmt_sp_admin(): void
{
    $admins = get_users(['role' => 'administrator', 'number' => 1]);
    wp_set_current_user($admins[0]->ID);
}

function lcmt_sp_fields(array $pairs): string
{
    $snapshot = [];

    foreach ($pairs as $name => [$type, $value]) {
        $snapshot[] = ['name' => $name, 'type' => $type, 'value' => $value];
    }

    return wp_json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

/**
 * Run a callback with $_GET replaced, restoring it afterwards.
 */
function lcmt_sp_with_get(array $get, callable $callback)
{
    $saved = $_GET;
    $_GET  = $get;

    try {
        return $callback();
    } finally {
        $_GET = $saved;
    }
}

function lcmt_sp_render(array $get): string
{
    set_current_screen('mail_page_' . SubmissionsPage::PAGE_SLUG);

    // WP_List_Table builds its links from the request host, which wp-cli has not got.
    $_SERVER['HTTP_HOST'] ??= (string) wp_parse_url(home_url(), PHP_URL_HOST);

    return lcmt_sp_with_get($get, function () {
        ob_start();

        try {
            SubmissionsPage::render();
        } finally {
            $html = ob_get_clean();
        }

        return $html;
    });
}

/**
 * Render the whole screen of a page class (StatsPage, SubmissionSettings).
 */
function lcmt_sp_page(string $class, array $get = []): string
{
    $_SERVER['HTTP_HOST'] ??= (string) wp_parse_url(home_url(), PHP_URL_HOST);

    return lcmt_sp_with_get($get, function () use ($class) {
        ob_start();

        try {
            $class::render();
        } finally {
            $html = ob_get_clean();
        }

        return $html;
    });
}
