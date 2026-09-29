<?php

use LcmtDevMailer\Privacy;
use LcmtDevMailer\SubmissionRecorder;
use LcmtDevMailer\SubmissionRepository;
use LcmtDevMailer\SubmissionSettings;
use LcmtDevMailer\SubmissionsPage;

require_once __DIR__ . '/submissions-page-helpers.php';

const LCMT_IT_UA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36 Edg/126.0.0.0';

lcmt_it('the submissions table has the user agent, browser and os columns', function () {
    global $wpdb;

    $columns = $wpdb->get_results('SHOW COLUMNS FROM ' . SubmissionRepository::table(), OBJECT_K);

    foreach (['user_agent', 'browser', 'os'] as $name) {
        lcmt_assert_true(isset($columns[$name]), $name);
    }

    lcmt_assert_same('varchar(500)', $columns['user_agent']->Type);
    lcmt_assert_same('YES', $columns['user_agent']->Null);
    lcmt_assert_same('varchar(30)', $columns['browser']->Type);
    lcmt_assert_same('varchar(30)', $columns['os']->Type);
    lcmt_assert_same(LcmtDevMailer\SubmissionSchema::VERSION, get_option(LcmtDevMailer\SubmissionSchema::OPTION_VERSION), 'upgraded');
});

lcmt_it('record stores the raw user agent and its families', function () {
    lcmt_it_mail_ok();
    [$post, $key] = lcmt_it_template();

    $id  = SubmissionRecorder::record($post, $key, lcmt_it_fields($post), lcmt_it_values(), [], LCMT_IT_UA);
    $row = SubmissionRepository::find($id);

    lcmt_assert_same(LCMT_IT_UA, $row['user_agent']);
    lcmt_assert_same('Edge', $row['browser']);
    lcmt_assert_same('Windows', $row['os']);
    remove_all_filters('pre_wp_mail');
});

lcmt_it('record cuts the user agent at 500 characters and strips control characters', function () {
    lcmt_it_mail_ok();
    [$post, $key] = lcmt_it_template();

    $id  = SubmissionRecorder::record($post, $key, lcmt_it_fields($post), lcmt_it_values(), [], "Mozilla/5.0\x00 " . str_repeat('x', 800));
    $row = SubmissionRepository::find($id);

    lcmt_assert_same(500, mb_strlen($row['user_agent'], 'UTF-8'));
    lcmt_assert_true(!str_contains($row['user_agent'], "\x00"), 'control character');
    remove_all_filters('pre_wp_mail');
});

lcmt_it('record without a user agent stores NULL and empty families', function () {
    lcmt_it_mail_ok();
    [$post, $key] = lcmt_it_template();

    $row = SubmissionRepository::find(SubmissionRecorder::record($post, $key, lcmt_it_fields($post), lcmt_it_values(), []));

    lcmt_assert_null($row['user_agent']);
    lcmt_assert_same('', $row['browser']);
    lcmt_assert_same('', $row['os']);
    remove_all_filters('pre_wp_mail');
});

lcmt_it('the REST route records the User-Agent header of the request', function () {
    lcmt_it_mail_ok();
    [, $key] = lcmt_it_template();

    add_filter('lcmt_mailer_captcha_providers', '__return_empty_array');
    $request = new WP_REST_Request('POST', '/lcmt-mailer/v1/forms/' . $key);
    $request->set_header('Content-Type', 'application/json');
    $request->set_header('User-Agent', LCMT_IT_UA);
    $request->set_body(wp_json_encode(lcmt_it_values()));
    $status = rest_do_request($request)->get_status();
    remove_filter('lcmt_mailer_captcha_providers', '__return_empty_array');

    lcmt_assert_same(200, $status);
    $rows = lcmt_it_rows($key);
    lcmt_assert_count(1, $rows);
    lcmt_assert_same(LCMT_IT_UA, $rows[0]['user_agent']);
    lcmt_assert_same('Edge', $rows[0]['browser']);
    remove_all_filters('pre_wp_mail');
});

lcmt_it('anonymization erases the raw user agent and keeps the families', function () {
    $id = lcmt_it_insert(['user_agent' => LCMT_IT_UA, 'browser' => 'Edge', 'os' => 'Windows']);

    SubmissionRepository::anonymize([$id]);
    $row = SubmissionRepository::find($id);

    lcmt_assert_null($row['user_agent']);
    lcmt_assert_same('Edge', $row['browser']);
    lcmt_assert_same('Windows', $row['os']);

    $old = lcmt_it_insert(['user_agent' => LCMT_IT_UA, 'browser' => 'Edge', 'os' => 'Windows', 'created_at' => '2001-01-01 10:00:00']);
    SubmissionRepository::anonymizeBefore('2002-01-01 00:00:00', 100);
    lcmt_assert_null(SubmissionRepository::find($old)['user_agent'], 'retention run');
});

lcmt_it('countBy groups by browser and os', function () {
    $when = '2090-01-05 00:00:00';
    lcmt_it_insert(['browser' => 'Chrome', 'os' => 'Windows', 'created_at' => $when]);
    lcmt_it_insert(['browser' => 'Chrome', 'os' => 'macOS', 'created_at' => $when]);
    lcmt_it_insert(['browser' => 'Safari', 'os' => 'macOS', 'created_at' => $when]);

    lcmt_assert_same([['label' => 'Chrome', 'total' => 2], ['label' => 'Safari', 'total' => 1]], SubmissionRepository::countBy('browser', LCMT_IT_FUTURE));
    lcmt_assert_same([['label' => 'macOS', 'total' => 2], ['label' => 'Windows', 'total' => 1]], SubmissionRepository::countBy('os', LCMT_IT_FUTURE));
});

lcmt_it('the detail shows the browser, the system and the raw user agent, escaped', function () {
    lcmt_sp_admin();
    $id   = lcmt_it_insert(['user_agent' => 'Mozilla/5.0 <b>x</b>', 'browser' => 'Edge', 'os' => 'Windows']);
    $html = lcmt_sp_render(['submission' => (string) $id]);

    lcmt_assert_true(str_contains($html, '<th style="width: 200px;">Browser</th><td>Edge</td>'), 'browser');
    lcmt_assert_true(str_contains($html, '<th style="width: 200px;">Operating system</th><td>Windows</td>'), 'os');
    lcmt_assert_true(str_contains($html, '<th style="width: 200px;">User agent</th><td>Mozilla/5.0 &lt;b&gt;x&lt;/b&gt;</td>'), 'user agent');
});

lcmt_it('an anonymized detail keeps the families and hides the user agent', function () {
    lcmt_sp_admin();
    $id = lcmt_it_insert(['user_agent' => LCMT_IT_UA, 'browser' => 'Edge', 'os' => 'Windows']);
    SubmissionRepository::anonymize([$id]);

    $html = lcmt_sp_render(['submission' => (string) $id]);

    lcmt_assert_true(str_contains($html, '>Edge</td>'), 'browser kept');
    lcmt_assert_same(false, str_contains($html, 'User agent'), 'no user agent row');
    lcmt_assert_same(false, str_contains($html, 'Mozilla/5.0'), 'no raw string');
});

lcmt_it('the privacy export lists the browser, the system and the user agent', function () {
    $email = 'it-' . uniqid() . '@example.invalid';
    lcmt_it_insert([
        'user_agent' => LCMT_IT_UA,
        'browser'    => 'Edge',
        'os'         => 'Windows',
        'fields'     => wp_json_encode([['name' => 'email', 'type' => 'email', 'value' => $email]]),
    ]);

    $items = array_column(Privacy::export($email)['data'][0]['data'], 'value', 'name');

    lcmt_assert_same('Edge', $items['Browser'] ?? null);
    lcmt_assert_same('Windows', $items['Operating system'] ?? null);
    lcmt_assert_same(LCMT_IT_UA, $items['User agent'] ?? null);
});

lcmt_it('the privacy eraser clears the user agent', function () {
    $email = 'it-' . uniqid() . '@example.invalid';
    $id    = lcmt_it_insert([
        'user_agent' => LCMT_IT_UA,
        'browser'    => 'Edge',
        'fields'     => wp_json_encode([['name' => 'email', 'type' => 'email', 'value' => $email]]),
    ]);

    Privacy::erase($email);
    $row = SubmissionRepository::find($id);

    lcmt_assert_null($row['user_agent']);
    lcmt_assert_same('Edge', $row['browser']);
});

lcmt_it('the CSV export has the browser, os and user agent columns', function () {
    $id = lcmt_it_insert(['user_agent' => LCMT_IT_UA, 'browser' => 'Edge', 'os' => 'Windows']);

    $stream = fopen('php://memory', 'w+');
    SubmissionsPage::writeCsv($stream, [SubmissionRepository::find($id)]);
    rewind($stream);
    $lines = explode("\n", trim(substr(stream_get_contents($stream), 3)));
    fclose($stream);

    $header = str_getcsv($lines[0], ';');
    $cells  = array_combine($header, str_getcsv($lines[1], ';'));

    lcmt_assert_same('Edge', $cells['browser']);
    lcmt_assert_same('Windows', $cells['os']);
    lcmt_assert_same(LCMT_IT_UA, $cells['user_agent']);
});

lcmt_it('the policy text mentions the user agent and the kept families', function () {
    $action = get_option(SubmissionSettings::OPTION_ACTION, false);

    try {
        update_option(SubmissionSettings::OPTION_ACTION, 'anonymize');
        $text = lcmt_pv_policy_text();
        lcmt_assert_true(str_contains($text, 'user agent'), 'user agent: ' . $text);
        lcmt_assert_true(str_contains($text, 'erased when the message is anonymized'), 'erased: ' . $text);
        lcmt_assert_true(str_contains($text, 'browser and operating system families'), 'families kept: ' . $text);

        update_option(SubmissionSettings::OPTION_ACTION, 'delete');
        $text = lcmt_pv_policy_text();
        lcmt_assert_true(str_contains($text, 'It is deleted with the message'), 'delete mode: ' . $text);
        lcmt_assert_same(false, str_contains($text, 'families'), 'nothing kept in delete mode');
    } finally {
        $action === false ? delete_option(SubmissionSettings::OPTION_ACTION) : update_option(SubmissionSettings::OPTION_ACTION, $action);
    }
});
