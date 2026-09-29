<?php

use LcmtDevMailer\Privacy;
use LcmtDevMailer\SubmissionRepository;
use LcmtDevMailer\SubmissionSettings;

require_once ABSPATH . 'wp-admin/includes/class-wp-privacy-policy-content.php';

function lcmt_pv_email(): string
{
    return 'it-' . uniqid() . '@example.invalid';
}

/**
 * Insert a row whose snapshot holds the email and a message text.
 */
function lcmt_pv_row(string $email, array $overrides = []): int
{
    return lcmt_it_insert($overrides + [
        'form_key'  => 'it-privacy',
        'created_at' => '2001-02-03 04:05:06',
        'form_seconds' => 42,
        'page_path' => '/contact/',
        'mail_error' => 'SMTP failed for ' . $email,
        'fields'    => wp_json_encode([
            ['name' => 'firstname', 'type' => 'text', 'value' => 'Jeanne'],
            ['name' => 'email', 'type' => 'email', 'value' => $email],
            ['name' => 'message', 'type' => 'textarea', 'value' => 'Hello'],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ]);
}

/**
 * Text registered with WordPress for the LCMT Mailer policy section, after
 * running addPolicyContent() as if inside admin_init.
 */
function lcmt_pv_policy_text(): string
{
    global $wp_current_filter;

    set_current_screen('dashboard');
    $wp_current_filter[] = 'admin_init';

    try {
        Privacy::addPolicyContent();
    } finally {
        array_pop($wp_current_filter);
    }

    $found = '';

    foreach (WP_Privacy_Policy_Content::get_suggested_policy_text() as $entry) {
        if ($entry['plugin_name'] === 'LCMT Mailer') {
            $found = $entry['policy_text'];
        }
    }

    return $found;
}

lcmt_it('privacy: registerExporter and registerEraser add the entry', function () {
    $exporters = Privacy::registerExporter([]);
    lcmt_assert_same([Privacy::class, 'export'], $exporters['lcmt-dev-mailer']['callback']);
    lcmt_assert_same('Received messages', $exporters['lcmt-dev-mailer']['exporter_friendly_name']);

    $erasers = Privacy::registerEraser([]);
    lcmt_assert_same([Privacy::class, 'erase'], $erasers['lcmt-dev-mailer']['callback']);
    lcmt_assert_same('Received messages', $erasers['lcmt-dev-mailer']['eraser_friendly_name']);
});

lcmt_it('privacy: the WordPress filters include the entries', function () {
    lcmt_assert_true(isset(apply_filters('wp_privacy_personal_data_exporters', [])['lcmt-dev-mailer']));
    lcmt_assert_true(isset(apply_filters('wp_privacy_personal_data_erasers', [])['lcmt-dev-mailer']));
});

lcmt_it('privacy: export returns exact matches only, case-insensitively', function () {
    $email = lcmt_pv_email();
    $exact = lcmt_pv_row($email);
    $padded = lcmt_pv_row(' ' . strtoupper($email) . ' ');
    lcmt_pv_row('x' . $email);
    lcmt_it_insert(['fields' => wp_json_encode([
        ['name' => 'message', 'type' => 'textarea', 'value' => 'write me at ' . $email],
    ])]);
    $anon = lcmt_pv_row($email);
    SubmissionRepository::anonymize([$anon]);

    $result = Privacy::export(strtolower($email));

    lcmt_assert_true($result['done']);
    lcmt_assert_count(2, $result['data']);
    lcmt_assert_same(['lcmt-submission-' . $exact, 'lcmt-submission-' . $padded], array_column($result['data'], 'item_id'));

    $item = $result['data'][0];
    lcmt_assert_same('lcmt-dev-mailer', $item['group_id']);
    lcmt_assert_same('Received messages', $item['group_label']);
    lcmt_assert_same(['Date', 'Form', 'Sent from', 'firstname', 'email', 'message'], array_column($item['data'], 'name'));
    lcmt_assert_same('it-privacy', $item['data'][1]['value']);
    lcmt_assert_same('/contact/', $item['data'][2]['value']);
    lcmt_assert_same($email, $item['data'][4]['value']);
});

lcmt_it('privacy: export with an empty email returns nothing', function () {
    lcmt_pv_row(lcmt_pv_email());
    lcmt_assert_same(['data' => [], 'done' => true], Privacy::export('  '));
});

lcmt_it('privacy: erase anonymizes exactly the matching rows', function () {
    global $wpdb;

    $email = lcmt_pv_email();
    $a = lcmt_pv_row($email);
    $b = lcmt_pv_row(strtoupper($email));
    $other = lcmt_pv_row(lcmt_pv_email());
    $substring = lcmt_pv_row('x' . $email);

    $result = Privacy::erase($email);

    lcmt_assert_true($result['items_removed']);
    lcmt_assert_same(false, $result['items_retained']);
    lcmt_assert_same([], $result['messages']);
    lcmt_assert_true($result['done']);

    foreach ([$a, $b] as $id) {
        $row = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . SubmissionRepository::table() . ' WHERE id = %d', $id), ARRAY_A);
        lcmt_assert_null($row['fields']);
        lcmt_assert_null($row['mail_error']);
        lcmt_assert_true($row['anonymized_at'] !== null, 'anonymized_at set');
        lcmt_assert_same('it-privacy', $row['form_key']);
        lcmt_assert_same('/contact/', $row['page_path']);
        lcmt_assert_same('00:00:00', substr($row['created_at'], 11), 'only the day is kept');
        lcmt_assert_null($row['form_seconds'], 'form_seconds dropped');
    }

    foreach ([$other, $substring] as $id) {
        $row = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . SubmissionRepository::table() . ' WHERE id = %d', $id), ARRAY_A);
        lcmt_assert_true($row['fields'] !== null, 'fields kept');
        lcmt_assert_null($row['anonymized_at']);
    }

    $second = Privacy::erase($email);
    lcmt_assert_same(false, $second['items_removed']);
    lcmt_assert_true($second['done']);
});

lcmt_it('privacy: erase with no match removes nothing', function () {
    lcmt_assert_same(false, Privacy::erase(lcmt_pv_email())['items_removed']);
    lcmt_assert_same(false, Privacy::erase('')['items_removed']);
});

lcmt_it('privacy: policy text follows the retention settings', function () {
    $days   = get_option(SubmissionSettings::OPTION_DAYS, false);
    $action = get_option(SubmissionSettings::OPTION_ACTION, false);

    try {
        update_option(SubmissionSettings::OPTION_DAYS, 200);
        update_option(SubmissionSettings::OPTION_ACTION, 'anonymize');
        $text = lcmt_pv_policy_text();
        lcmt_assert_true(str_contains($text, '200 days'), 'days: ' . $text);
        lcmt_assert_true(str_contains($text, 'anonymized'), 'anonymize sentence');

        update_option(SubmissionSettings::OPTION_DAYS, 201);
        update_option(SubmissionSettings::OPTION_ACTION, 'delete');
        $text = lcmt_pv_policy_text();
        lcmt_assert_true(str_contains($text, '201 days'), 'days: ' . $text);
        lcmt_assert_true(str_contains($text, 'they are then deleted'), 'delete sentence');
        lcmt_assert_true(!str_contains($text, 'anonymized'), 'no anonymize sentence');
    } finally {
        $days === false ? delete_option(SubmissionSettings::OPTION_DAYS) : update_option(SubmissionSettings::OPTION_DAYS, $days);
        $action === false ? delete_option(SubmissionSettings::OPTION_ACTION) : update_option(SubmissionSettings::OPTION_ACTION, $action);
    }
});

lcmt_it('privacy: [lcmt-retention-days] prints the retention period', function () {
    $days = get_option(SubmissionSettings::OPTION_DAYS, false);

    try {
        delete_option(SubmissionSettings::OPTION_DAYS);
        lcmt_assert_same('1095', do_shortcode('[lcmt-retention-days]'));
        update_option(SubmissionSettings::OPTION_DAYS, 90);
        lcmt_assert_same('90', do_shortcode('[lcmt-retention-days]'));
    } finally {
        $days === false ? delete_option(SubmissionSettings::OPTION_DAYS) : update_option(SubmissionSettings::OPTION_DAYS, $days);
    }
});
