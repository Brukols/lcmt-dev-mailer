<?php

/**
 * Helpers shared by several integration test files. harness.php loads this
 * file, so no test depends on which other test file was loaded first.
 */

/**
 * Filters that keep any email from leaving the site, whatever a test does.
 */
function lcmt_it_mail_ok(): void
{
    remove_all_filters('pre_wp_mail');
    add_filter('pre_wp_mail', '__return_true');
}

function lcmt_it_mail_fail(): void
{
    remove_all_filters('pre_wp_mail');
    add_filter('pre_wp_mail', static function () {
        do_action('wp_mail_failed', new WP_Error('wp_mail_failed', 'SMTP connect() failed.'));
        return false;
    });
}

/**
 * A mail template created inside the test transaction.
 *
 * @return array{0: WP_Post, 1: string} The post and its key.
 */
function lcmt_it_template(array $meta = []): array
{
    $key    = 'it-rec-' . uniqid();
    $postId = wp_insert_post([
        'post_type'   => 'mail',
        'post_status' => 'publish',
        'post_title'  => 'IT template',
    ]);

    foreach ($meta + [
        '_lcmt_mail_key'     => $key,
        '_lcmt_mail_to'      => 'it@example.invalid',
        '_lcmt_mail_subject' => 'Hello [firstname*]',
        '_lcmt_mail_content' => '[firstname*] [email* email] [secret password] [message textarea]',
    ] as $name => $value) {
        update_post_meta($postId, $name, $value);
    }

    return [get_post($postId), get_post_meta($postId, '_lcmt_mail_key', true)];
}

function lcmt_it_key(): string
{
    return 'it-' . uniqid();
}

function lcmt_it_ids(array $rows): array
{
    return array_map(static fn(array $row) => (int) $row['id'], $rows);
}
