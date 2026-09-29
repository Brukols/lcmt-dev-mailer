<?php

use LcmtDevMailer\MetaFields;
use LcmtDevMailer\SubmissionRecorder;
use LcmtDevMailer\SubmissionRepository;

function lcmt_it_fields(WP_Post $post): array
{
    return LcmtDevMailer\FieldParser::parse(
        MetaFields::get($post->ID, 'to'),
        MetaFields::get($post->ID, 'reply_to'),
        MetaFields::get($post->ID, 'subject'),
        MetaFields::get($post->ID, 'content')
    );
}

function lcmt_it_values(): array
{
    return ['firstname' => 'Élodie', 'email' => 'elodie@example.com', 'secret' => 'hunter2', 'message' => "Bonjour\nà tous"];
}

function lcmt_it_rows(string $key): array
{
    global $wpdb;

    return $wpdb->get_results($wpdb->prepare('SELECT * FROM ' . SubmissionRepository::table() . ' WHERE form_key = %s', $key), ARRAY_A);
}

function lcmt_it_context(): array
{
    return [
        'page'    => '/contact/',
        'device'  => 'mobile',
        'locale'  => 'fr-FR',
        'seconds' => 42,
        'landing' => ['path' => '/', 'utm_source' => 'Google', 'utm_medium' => 'cpc', 'click_id' => 'gclid'],
    ];
}

/**
 * POST a JSON body to the form route, with the spam protection off for the call.
 */
function lcmt_it_post(string $key, array $body): WP_REST_Response
{
    add_filter('lcmt_mailer_captcha_providers', '__return_empty_array');

    $request = new WP_REST_Request('POST', '/lcmt-mailer/v1/forms/' . $key);
    $request->set_header('Content-Type', 'application/json');
    $request->set_body(wp_json_encode($body));

    $response = rest_do_request($request);

    remove_filter('lcmt_mailer_captcha_providers', '__return_empty_array');

    return $response;
}

lcmt_it('storesSubmissions is true without meta, true for 1, false for 0', function () {
    lcmt_it_mail_ok();
    [$post] = lcmt_it_template();

    lcmt_assert_true(MetaFields::storesSubmissions($post->ID));

    update_post_meta($post->ID, '_lcmt_mail_store_submissions', '1');
    lcmt_assert_true(MetaFields::storesSubmissions($post->ID));

    update_post_meta($post->ID, '_lcmt_mail_store_submissions', '0');
    lcmt_assert_same(false, MetaFields::storesSubmissions($post->ID));

    remove_all_filters('pre_wp_mail');
});

lcmt_it('record returns 0 and stores nothing when the template opted out', function () {
    lcmt_it_mail_ok();
    [$post, $key] = lcmt_it_template(['_lcmt_mail_store_submissions' => '0']);

    $id = SubmissionRecorder::record($post, $key, lcmt_it_fields($post), lcmt_it_values(), lcmt_it_context());

    lcmt_assert_same(0, $id);
    lcmt_assert_count(0, lcmt_it_rows($key));
    remove_all_filters('pre_wp_mail');
});

lcmt_it('record stores the snapshot without passwords, the context and the channel', function () {
    lcmt_it_mail_ok();
    [$post, $key] = lcmt_it_template();

    $id  = SubmissionRecorder::record($post, $key, lcmt_it_fields($post), lcmt_it_values(), lcmt_it_context());
    $row = SubmissionRepository::find($id);

    lcmt_assert_true($id > 0);
    lcmt_assert_same($key, $row['form_key']);
    lcmt_assert_same($post->ID, (int) $row['mail_post_id']);
    lcmt_assert_same('new', $row['status']);
    lcmt_assert_same(0, (int) $row['mail_sent']);
    lcmt_assert_same(['firstname', 'email', 'message'], array_column($row['fields'], 'name'));
    lcmt_assert_same('Élodie', $row['fields'][0]['value']);
    lcmt_assert_same('/contact/', $row['page_path']);
    lcmt_assert_same('/', $row['landing_path']);
    lcmt_assert_same('google', $row['utm_source']);
    lcmt_assert_same('gclid', $row['click_id_type']);
    lcmt_assert_same('mobile', $row['device']);
    lcmt_assert_same('fr-FR', $row['locale']);
    lcmt_assert_same(42, (int) $row['form_seconds']);
    lcmt_assert_same('google_ads', $row['channel']);
    remove_all_filters('pre_wp_mail');
});

lcmt_it('the channel filter overrides the classifier', function () {
    lcmt_it_mail_ok();
    [$post, $key] = lcmt_it_template();

    $filter = static fn(string $channel, array $context) => 'referral';
    add_filter('lcmt_mailer_submission_channel', $filter, 10, 2);

    $id = SubmissionRecorder::record($post, $key, lcmt_it_fields($post), lcmt_it_values(), lcmt_it_context());

    remove_filter('lcmt_mailer_submission_channel', $filter, 10);

    lcmt_assert_same('referral', SubmissionRepository::find($id)['channel']);
    remove_all_filters('pre_wp_mail');
});

lcmt_it('send records success', function () {
    lcmt_it_mail_ok();
    [$post, $key] = lcmt_it_template();
    $id = SubmissionRecorder::record($post, $key, lcmt_it_fields($post), lcmt_it_values(), []);

    lcmt_assert_true(SubmissionRecorder::send($id, $key, ['[firstname*]' => 'Élodie']));

    $row = SubmissionRepository::find($id);
    lcmt_assert_same(1, (int) $row['mail_sent']);
    lcmt_assert_null($row['mail_error']);
    remove_all_filters('pre_wp_mail');
});

lcmt_it('send records the mail error on failure', function () {
    lcmt_it_mail_fail();
    [$post, $key] = lcmt_it_template();
    $id = SubmissionRecorder::record($post, $key, lcmt_it_fields($post), lcmt_it_values(), []);

    lcmt_assert_same(false, SubmissionRecorder::send($id, $key, ['[firstname*]' => 'Élodie']));

    $row = SubmissionRepository::find($id);
    lcmt_assert_same(0, (int) $row['mail_sent']);
    lcmt_assert_same('SMTP connect() failed.', $row['mail_error']);
    remove_all_filters('pre_wp_mail');
});

lcmt_it('send with id 0 sends and touches no row', function () {
    lcmt_it_mail_ok();
    [$post, $key] = lcmt_it_template();

    lcmt_assert_true(SubmissionRecorder::send(0, $key, ['[firstname*]' => 'Élodie']));
    lcmt_assert_count(0, lcmt_it_rows($key));
    remove_all_filters('pre_wp_mail');
});

lcmt_it('the REST route saves the submission and answers 200', function () {
    lcmt_it_mail_ok();
    [$post, $key] = lcmt_it_template();

    $response = lcmt_it_post($key, lcmt_it_values() + ['_context' => lcmt_it_context()]);

    lcmt_assert_same(200, $response->get_status());

    $rows = lcmt_it_rows($key);
    lcmt_assert_count(1, $rows);
    $row = SubmissionRepository::find((int) $rows[0]['id']);
    lcmt_assert_same(1, (int) $row['mail_sent']);
    lcmt_assert_same(['Élodie', 'elodie@example.com', "Bonjour\nà tous"], array_column($row['fields'], 'value'));
    lcmt_assert_same('/contact/', $row['page_path']);
    remove_all_filters('pre_wp_mail');
});

lcmt_it('a failing send answers 500 and keeps the message', function () {
    lcmt_it_mail_fail();
    [$post, $key] = lcmt_it_template();

    $response = lcmt_it_post($key, lcmt_it_values());

    lcmt_assert_same(500, $response->get_status());

    $rows = lcmt_it_rows($key);
    lcmt_assert_count(1, $rows);
    lcmt_assert_same(0, (int) $rows[0]['mail_sent']);
    lcmt_assert_same('SMTP connect() failed.', $rows[0]['mail_error']);
    remove_all_filters('pre_wp_mail');
});
