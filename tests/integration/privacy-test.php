<?php

use LcmtDevMailer\Attribution;
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

    // WordPress keeps every distinct text added during the request: start from an empty list so
    // the entry found is the one added below, not an older one with the same wording.
    $registry = new ReflectionProperty(WP_Privacy_Policy_Content::class, 'policy_content');
    $registry->setAccessible(true);
    $registry->setValue(null, []);

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
    lcmt_assert_same(['Date', 'Form', 'Sent from', 'Source', 'Time on the form', 'Email', 'Email error', 'firstname', 'email', 'message'], array_column($item['data'], 'name'));
    lcmt_assert_same('it-privacy', $item['data'][1]['value']);
    lcmt_assert_same('/contact/', $item['data'][2]['value']);
    lcmt_assert_same($email, $item['data'][8]['value']);
});

lcmt_it('privacy: export includes the context of the visit, only when known', function () {
    $email = lcmt_pv_email();
    $id    = lcmt_pv_row($email, [
        'mail_sent'     => 0,
        'landing_path'  => '/landing/',
        'channel'       => 'google_ads',
        'referrer_host' => 'www.google.com',
        'utm_source'    => 'google',
        'utm_medium'    => 'cpc',
        'utm_campaign'  => 'spring',
        'click_id_type' => 'gclid',
        'device'        => 'mobile',
        'locale'        => 'fr-FR',
        'form_seconds'  => 42,
    ]);

    $items = array_column(Privacy::export($email)['data'][0]['data'], 'value', 'name');

    lcmt_assert_same('/landing/', $items['Landing page'] ?? null, 'landing');
    lcmt_assert_same('Google Ads', $items['Source'] ?? null, 'source');
    lcmt_assert_same('www.google.com', $items['Referring site'] ?? null, 'referrer');
    lcmt_assert_same('google / cpc / spring', $items['Campaign'] ?? null, 'campaign');
    lcmt_assert_same('gclid', $items['Ad click'] ?? null, 'ad click');
    lcmt_assert_same('mobile', $items['Device'] ?? null, 'device');
    lcmt_assert_same('fr-FR', $items['Browser language'] ?? null, 'locale');
    lcmt_assert_same(human_time_diff(0, 42), $items['Time on the form'] ?? null, 'time on form');
    lcmt_assert_same('Not sent', $items['Email'] ?? null, 'email status');
    lcmt_assert_same('SMTP failed for ' . $email, $items['Email error'] ?? null, 'mail error');

    $sentEmail = lcmt_pv_email();
    lcmt_pv_row($sentEmail, ['mail_error' => null, 'form_seconds' => null]);
    $names = array_column(Privacy::export($sentEmail)['data'][0]['data'], 'name');

    lcmt_assert_same(['Date', 'Form', 'Sent from', 'Source', 'Email', 'firstname', 'email', 'message'], $names, 'empty values left out');
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

lcmt_it('privacy: policy text names the browser storage, the recorded context and the day kept after anonymization', function () {
    $action  = get_option(SubmissionSettings::OPTION_ACTION, false);
    $consent = get_option(Attribution::OPTION_WITHOUT_CONSENT, false);

    try {
        update_option(SubmissionSettings::OPTION_ACTION, 'anonymize');
        update_option(Attribution::OPTION_WITHOUT_CONSENT, '1');
        $text = lcmt_pv_policy_text();
        lcmt_assert_true(str_contains($text, 'kept in your browser until you close the tab'), 'browser storage: ' . $text);
        lcmt_assert_true(str_contains($text, 'type of device, the language of your browser and the time spent on the form'), 'context: ' . $text);
        lcmt_assert_true(str_contains($text, 'only the day'), 'day kept: ' . $text);

        update_option(Attribution::OPTION_WITHOUT_CONSENT, '0');
        $text = lcmt_pv_policy_text();
        lcmt_assert_same(false, str_contains($text, 'in your browser'), 'nothing stored in the browser: ' . $text);
        lcmt_assert_true(str_contains($text, 'time spent on the form'), 'context still recorded');
    } finally {
        $action === false ? delete_option(SubmissionSettings::OPTION_ACTION) : update_option(SubmissionSettings::OPTION_ACTION, $action);
        $consent === false ? delete_option(Attribution::OPTION_WITHOUT_CONSENT) : update_option(Attribution::OPTION_WITHOUT_CONSENT, $consent);
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

/**
 * Run $test with the retention options set, then put them back.
 */
function lcmt_pv_with_retention($days, string $action, callable $test): void
{
    $oldDays   = get_option(SubmissionSettings::OPTION_DAYS, false);
    $oldAction = get_option(SubmissionSettings::OPTION_ACTION, false);

    try {
        $days === null ? delete_option(SubmissionSettings::OPTION_DAYS) : update_option(SubmissionSettings::OPTION_DAYS, $days);
        update_option(SubmissionSettings::OPTION_ACTION, $action);
        $test();
    } finally {
        $oldDays === false ? delete_option(SubmissionSettings::OPTION_DAYS) : update_option(SubmissionSettings::OPTION_DAYS, $oldDays);
        $oldAction === false ? delete_option(SubmissionSettings::OPTION_ACTION) : update_option(SubmissionSettings::OPTION_ACTION, $oldAction);
    }
}

lcmt_it('privacy: [lcmt-retention-period] words the period', function () {
    $cases = [
        [null, '3 years'],
        [365, '1 year'],
        [730, '2 years'],
        [180, '6 months'],
        [30, '1 month'],
        [45, '45 days'],
        [1, '1 day'],
        [1460, '4 years'],
    ];

    foreach ($cases as [$days, $expected]) {
        lcmt_pv_with_retention($days, 'anonymize', function () use ($expected, $days) {
            lcmt_assert_same($expected, do_shortcode('[lcmt-retention-period]'), 'days ' . var_export($days, true));
        });
    }
});

lcmt_it('privacy: [lcmt-retention-action] follows the setting', function () {
    lcmt_pv_with_retention(null, 'anonymize', function () {
        lcmt_assert_same('anonymized', do_shortcode('[lcmt-retention-action]'));
    });
    lcmt_pv_with_retention(null, 'delete', function () {
        lcmt_assert_same('deleted', do_shortcode('[lcmt-retention-action]'));
    });
});

lcmt_it('privacy: the policy sentence uses the period in words', function () {
    lcmt_pv_with_retention(null, 'anonymize', function () {
        $text = do_shortcode('[lcmt-privacy-policy]');
        lcmt_assert_true(str_contains($text, 'saved on this site for 3 years so we can'), $text);
        lcmt_assert_true(str_contains($text, 'they are then anonymized: only the day'), $text);
        lcmt_assert_same(false, str_contains($text, '1095'), 'no days');
    });

    lcmt_pv_with_retention(180, 'delete', function () {
        $text = do_shortcode('[lcmt-privacy-policy]');
        lcmt_assert_true(str_contains($text, 'saved on this site for 6 months so we can'), $text);
        lcmt_assert_true(str_contains($text, 'they are then deleted'), $text);
        lcmt_assert_same(false, str_contains($text, 'anonymized'), 'no anonymize sentence');
    });

    lcmt_pv_with_retention(200, 'delete', function () {
        lcmt_assert_true(str_contains(do_shortcode('[lcmt-privacy-policy]'), 'for 200 days so we'), 'days when not whole months');
    });
});

lcmt_it('privacy: [lcmt-privacy-policy] and the policy guide print the same HTML', function () {
    foreach ([['anonymize', null], ['delete', 180]] as [$action, $days]) {
        lcmt_pv_with_retention($days, $action, function () {
            $shortcode = do_shortcode('[lcmt-privacy-policy]');
            lcmt_assert_same(Privacy::policyText(), $shortcode, 'shortcode is policyText()');
            lcmt_assert_same($shortcode, lcmt_pv_policy_text(), 'guide');
            lcmt_assert_true(str_starts_with($shortcode, '<p>'), 'paragraphs');
        });
    }
});

lcmt_it('privacy: the word shortcodes stay escaped', function () {
    $inject = static fn() => '<script>alert(1)</script>%s';
    add_filter('ngettext', $inject);
    add_filter('gettext', static fn($text) => str_contains($text, 'anonymized') || $text === 'deleted' ? '<b>x</b>' : $text);

    try {
        lcmt_pv_with_retention(null, 'anonymize', function () {
            $out = do_shortcode('[lcmt-retention-period]') . do_shortcode('[lcmt-retention-action]');
            lcmt_assert_same(false, str_contains($out, '<script>'), $out);
            lcmt_assert_same(false, str_contains($out, '<b>'), $out);
            lcmt_assert_true(str_contains($out, '&lt;script&gt;'), $out);
        });
    } finally {
        remove_all_filters('ngettext');
        remove_all_filters('gettext');
    }
});

function lcmt_pv_page(): string
{
    ob_start();
    SubmissionSettings::render();

    return (string) ob_get_clean();
}

lcmt_it('privacy: the Data retention page documents the shortcodes', function () {
    lcmt_pv_with_retention(180, 'delete', function () {
        $html = lcmt_pv_page();

        lcmt_assert_true(str_contains($html, '<h2>Privacy policy</h2>'), 'heading');
        lcmt_assert_true(str_contains($html, 'Recommended'), 'recommended');

        foreach (['lcmt-privacy-policy', 'lcmt-retention-period', 'lcmt-retention-action', 'lcmt-retention-days'] as $code) {
            lcmt_assert_true(str_contains($html, '<code>[' . $code . ']</code>'), $code . ' listed');
            lcmt_assert_true(str_contains($html, 'data-lcmt-copy="[' . $code . ']"'), $code . ' copy button');
        }

        lcmt_assert_true(str_contains($html, '<td class="lcmt-privacy-docs__live"><code>6 months</code>'), 'live period');
        lcmt_assert_true(str_contains($html, '<code>deleted</code>'), 'live action');
        lcmt_assert_true(str_contains($html, '<code>180</code>'), 'live days');
        lcmt_assert_true(str_contains($html, 'saved on this site for 6 months so we can'), 'live policy');
        lcmt_assert_true(str_contains($html, 'options-privacy.php?tab=policyguide'), 'guide link');
        lcmt_assert_true(str_contains($html, 'aria-live="polite"'), 'status region');
        lcmt_assert_true(str_contains($html, 'The messages sent through our forms are kept for [lcmt-retention-period], then [lcmt-retention-action].'), 'sample');
        lcmt_assert_true(str_contains($html, '<textarea readonly'), 'readonly sample');
        lcmt_assert_true(str_contains($html, 'data-lcmt-copy="The messages sent through our forms'), 'sample copy');
    });
});

lcmt_it('privacy: the page links the privacy policy page when one is set', function () {
    $without = lcmt_pv_page();
    lcmt_assert_true(str_contains($without, 'options-privacy.php"'), 'settings link without a page');

    $id = wp_insert_post(['post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'IT policy']);
    $old = get_option('wp_page_for_privacy_policy', false);
    update_option('wp_page_for_privacy_policy', $id);

    try {
        lcmt_assert_true(str_contains(lcmt_pv_page(), esc_url((string) get_privacy_policy_url())), 'policy page link');
    } finally {
        $old === false ? delete_option('wp_page_for_privacy_policy') : update_option('wp_page_for_privacy_policy', $old);
    }
});

lcmt_it('privacy: the page enqueues its copy script only on the Data retention screen', function () {
    wp_dequeue_script('lcmt-admin-privacy-docs');
    SubmissionSettings::enqueue('edit.php');
    lcmt_assert_same(false, wp_script_is('lcmt-admin-privacy-docs', 'enqueued'));

    SubmissionSettings::enqueue('mail_page_' . SubmissionSettings::PAGE_SLUG);
    lcmt_assert_true(wp_script_is('lcmt-admin-privacy-docs', 'enqueued'));
    wp_dequeue_script('lcmt-admin-privacy-docs');
});
