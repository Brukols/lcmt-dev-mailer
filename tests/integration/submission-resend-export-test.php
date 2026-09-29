<?php

use LcmtDevMailer\SubmissionRepository;
use LcmtDevMailer\SubmissionsPage;

require_once __DIR__ . '/submissions-page-helpers.php';

/**
 * A failed row with fields, tied to a temporary mail template so resend has a real key.
 *
 * @return array{0: int, 1: string} Row id and template key.
 */
function lcmt_re_failed_row(array $overrides = []): array
{
    [, $key] = lcmt_it_template();

    $id = lcmt_it_insert($overrides + [
        'form_key'   => $key,
        'mail_sent'  => 0,
        'mail_error' => 'Old error.',
        'fields'     => wp_json_encode([
            ['name' => 'firstname', 'type' => 'text', 'value' => 'Élodie'],
            ['name' => 'email', 'type' => 'email', 'value' => 'elodie@example.com'],
            ['name' => 'message', 'type' => 'textarea', 'value' => 'Bonjour'],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ]);

    return [$id, $key];
}

/**
 * Every mail attempt goes through this filter: it counts them and sends nothing.
 */
function lcmt_re_count_mail(): ArrayObject
{
    $counter = new ArrayObject(['n' => 0]);

    remove_all_filters('pre_wp_mail');
    add_filter('pre_wp_mail', static function () use ($counter) {
        $counter['n']++;
        return true;
    });

    return $counter;
}

lcmt_it('resend on a failed row sends the email and records the success', function () {
    lcmt_it_mail_ok();
    [$id] = lcmt_re_failed_row();

    lcmt_assert_same('resent', SubmissionsPage::apply('resend', [$id]));

    $row = SubmissionRepository::find($id);
    lcmt_assert_same(1, (int) $row['mail_sent']);
    lcmt_assert_null($row['mail_error']);
    remove_all_filters('pre_wp_mail');
});

lcmt_it('resend that fails again stores the new error', function () {
    lcmt_it_mail_fail();
    [$id] = lcmt_re_failed_row();

    lcmt_assert_same('resend_failed', SubmissionsPage::apply('resend', [$id]));

    $row = SubmissionRepository::find($id);
    lcmt_assert_same(0, (int) $row['mail_sent']);
    lcmt_assert_same('SMTP connect() failed.', $row['mail_error']);
    remove_all_filters('pre_wp_mail');
});

lcmt_it('resend on an anonymized row fails without sending', function () {
    $counter = lcmt_re_count_mail();
    [$id]    = lcmt_re_failed_row(['fields' => null]);

    lcmt_assert_same('resend_failed', SubmissionsPage::apply('resend', [$id]));
    lcmt_assert_same(0, $counter['n'], 'mail attempts');
    remove_all_filters('pre_wp_mail');
});

lcmt_it('resend on a missing id fails without sending', function () {
    $counter = lcmt_re_count_mail();

    lcmt_assert_same('resend_failed', SubmissionsPage::apply('resend', [999999999]));
    lcmt_assert_same(0, $counter['n'], 'mail attempts');
    remove_all_filters('pre_wp_mail');
});

lcmt_it('the detail shows the resend button only for a failed, non-anonymized row', function () {
    lcmt_sp_admin();
    $label = 'Send the email again';

    [$failed]     = lcmt_re_failed_row();
    [$anonymized] = lcmt_re_failed_row(['fields' => null, 'anonymized_at' => current_time('mysql', true)]);
    $sent         = lcmt_it_insert(['mail_sent' => 1, 'fields' => lcmt_sp_fields(['email' => ['email', 'a@example.com']])]);

    lcmt_assert_true(str_contains(lcmt_sp_render(['submission' => $failed]), $label), 'failed');
    lcmt_assert_same(false, str_contains(lcmt_sp_render(['submission' => $anonymized]), $label), 'anonymized');
    lcmt_assert_same(false, str_contains(lcmt_sp_render(['submission' => $sent]), $label), 'sent');
});

lcmt_it('exportUrl carries the list filters, not paged nor search, with a valid nonce', function () {
    lcmt_sp_admin();

    $url = lcmt_sp_with_get(
        ['s' => 'élodie', 'form_key' => 'contact', 'status' => 'new', 'failed' => '1', 'paged' => '3'],
        static fn() => SubmissionsPage::exportUrl()
    );

    $query = [];
    parse_str((string) wp_parse_url(html_entity_decode($url), PHP_URL_QUERY), $query);

    lcmt_assert_same('élodie', $query['s'] ?? null);
    lcmt_assert_same('contact', $query['form_key'] ?? null);
    lcmt_assert_same('new', $query['status'] ?? null);
    lcmt_assert_same('1', $query['failed'] ?? null);
    lcmt_assert_same(SubmissionsPage::EXPORT_ACTION, $query['action'] ?? null);
    lcmt_assert_same(false, array_key_exists('paged', $query), 'paged');
    lcmt_assert_same(false, array_key_exists('search', $query), 'search');
    lcmt_assert_true(str_contains($url, 'admin-post.php'));
    lcmt_assert_true((bool) wp_verify_nonce($query['_wpnonce'] ?? '', SubmissionsPage::EXPORT_ACTION));
});

lcmt_it('writeCsv writes a BOM, semicolons, local dates and neutralised formulas', function () {
    $utc = '2026-01-15 10:30:00';
    $id  = lcmt_it_insert([
        'created_at' => $utc,
        'fields'     => wp_json_encode([['name' => 'message', 'type' => 'textarea', 'value' => '=1+1']]),
    ]);

    $stream = fopen('php://memory', 'w+');
    SubmissionsPage::writeCsv($stream, [SubmissionRepository::find($id)]);
    rewind($stream);
    $csv = stream_get_contents($stream);
    fclose($stream);

    lcmt_assert_same("\xEF\xBB\xBF", substr($csv, 0, 3), 'BOM');

    $lines = explode("\n", trim(substr($csv, 3)));
    lcmt_assert_same('created_at;form_key;', substr($lines[0], 0, 20), 'header');
    lcmt_assert_true(str_ends_with($lines[0], ';message'), 'message column');

    $cells = str_getcsv($lines[1], ';');
    lcmt_assert_same(get_date_from_gmt($utc, 'Y-m-d H:i:s'), $cells[0], 'local time');
    lcmt_assert_same('it-form', $cells[1]);
    lcmt_assert_same("'=1+1", end($cells), 'formula');
});
