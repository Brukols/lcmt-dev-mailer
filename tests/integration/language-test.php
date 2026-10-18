<?php

use LcmtDevMailer\Mailer;
use LcmtDevMailer\RequestLanguage;
use LcmtDevMailer\SubmissionRecorder;
use LcmtDevMailer\SubmissionRepository;
use LcmtDevMailer\SubmissionSchema;
use LcmtDevMailer\SubmissionsPage;

require_once __DIR__ . '/submissions-page-helpers.php';

/**
 * Whether the site runs Polylang with at least two languages, the default
 * one and another; the multilingual tests do nothing otherwise.
 *
 * @return array{0: string, 1: string}|null The default slug and another slug.
 */
function lcmt_lang_pair(): ?array
{
    if (!function_exists('pll_languages_list') || !function_exists('pll_set_post_language')) {
        return null;
    }

    $default = (string) pll_default_language();
    $others  = array_values(array_diff((array) pll_languages_list(['fields' => 'slug']), [$default]));

    return $default !== '' && $others ? [$default, $others[0]] : null;
}

/**
 * Keep every email on the site and remember the last one.
 */
function lcmt_lang_capture(): void
{
    $GLOBALS['lcmt_lang_mail'] = null;
    remove_all_filters('pre_wp_mail');
    add_filter('pre_wp_mail', static function ($return, array $atts) {
        $GLOBALS['lcmt_lang_mail'] = $atts;
        return true;
    }, 10, 2);
}

/**
 * A source mail in the default language and its translation in another one.
 *
 * @return array{0: WP_Post, 1: string, 2: int} Source post, key, translation id.
 */
function lcmt_lang_mail_with_translation(string $default, string $other, string $status, string $content): array
{
    [$source, $key] = lcmt_it_template([
        '_lcmt_mail_subject' => 'Demande de [firstname*]',
        '_lcmt_mail_content' => 'Prénom : [firstname*] [email* email] langue [submissionLanguage]',
    ]);
    pll_set_post_language($source->ID, $default);

    $translationId = wp_insert_post(['post_type' => 'mail', 'post_status' => $status, 'post_title' => 'IT translation']);
    update_post_meta($translationId, '_lcmt_mail_key', $key);
    update_post_meta($translationId, '_lcmt_mail_to', 'it@example.invalid');
    update_post_meta($translationId, '_lcmt_mail_subject', 'Request from [firstname*]');
    update_post_meta($translationId, '_lcmt_mail_content', $content);
    pll_set_post_language($translationId, $other);
    pll_save_post_translations([$default => $source->ID, $other => $translationId]);

    return [$source, $key, $translationId];
}

lcmt_it('The schema is version 4 and knows the language column', function () {
    lcmt_assert_same('4', SubmissionSchema::VERSION);
    lcmt_assert_true(in_array('language', SubmissionSchema::COLUMNS, true), 'listed');
    lcmt_assert_true(SubmissionSchema::hasColumns(['language']), 'exists in the table');
});

lcmt_it('record stores the submission language', function () {
    lcmt_it_mail_ok();
    [$post, $key] = lcmt_it_template();

    $id = RequestLanguage::using('en', static fn() => SubmissionRecorder::record($post, $key, [], [], null));

    lcmt_assert_same('en', SubmissionRepository::find($id)['language']);
});

lcmt_it('Without a language, nothing is recorded and no locale is switched', function () {
    lcmt_it_mail_ok();
    [$post, $key] = lcmt_it_template();

    $id = RequestLanguage::using(null, static fn() => SubmissionRecorder::record($post, $key, [], [], null));

    lcmt_assert_same('', SubmissionRepository::find($id)['language']);

    $request = new WP_REST_Request('POST', '/lcmt-mailer/v1/forms/' . $key);
    lcmt_assert_same(false, RequestLanguage::switchFor($request), 'no lang');

    $request->set_param('lang', 'xx');
    lcmt_assert_same(false, RequestLanguage::switchFor($request), 'unknown lang');
});

lcmt_it('The Language column and detail item appear on a multilingual site only (Polylang)', function () {
    lcmt_sp_admin();

    $key = lcmt_it_key();
    $id  = lcmt_it_insert(['form_key' => $key, 'language' => 'en']);
    $row = SubmissionRepository::find($id);

    $html  = lcmt_sp_render(['form_key' => $key]);
    $items = SubmissionsPage::contextItems($row);

    if (!RequestLanguage::isMultilingual()) {
        lcmt_assert_same(false, str_contains($html, 'column-language'), 'no column');
        lcmt_assert_same(false, isset($items['Language']), 'no detail item');
        return;
    }

    lcmt_assert_same(1, preg_match('#column-page_path.*column-language.*column-channel#s', $html), 'column after Sent from');
    lcmt_assert_true(str_contains($html, '>' . RequestLanguage::name('en') . '<'), 'language name in the list');
    lcmt_assert_same(RequestLanguage::name('en'), $items['Language'] ?? null, 'detail item');
});

lcmt_it('A published translation sends the mail in the submission language (Polylang)', function () {
    if (!($pair = lcmt_lang_pair())) {
        return;
    }

    [$default, $other] = $pair;
    lcmt_lang_capture();
    [, $key] = lcmt_lang_mail_with_translation($default, $other, 'publish', 'First name: [firstname*] [email* email] language [submissionLanguage]');

    $sent = RequestLanguage::using($other, static fn() => Mailer::sendByKey($key, ['firstname' => 'Ann', 'email' => 'a@example.com']));

    lcmt_assert_true($sent, 'sent');
    lcmt_assert_same('Request from Ann', $GLOBALS['lcmt_lang_mail']['subject'] ?? null, 'translated subject');
    lcmt_assert_true(str_contains($GLOBALS['lcmt_lang_mail']['message'], 'language ' . RequestLanguage::name($other)), '[submissionLanguage] filled');
});

lcmt_it('A draft translation leaves the mail in the default language (Polylang)', function () {
    if (!($pair = lcmt_lang_pair())) {
        return;
    }

    [$default, $other] = $pair;
    lcmt_lang_capture();
    [, $key] = lcmt_lang_mail_with_translation($default, $other, 'draft', 'First name: [firstname*] [email* email] language [submissionLanguage]');

    RequestLanguage::using($other, static fn() => Mailer::sendByKey($key, ['firstname' => 'Ann', 'email' => 'a@example.com']));

    lcmt_assert_same('Demande de Ann', $GLOBALS['lcmt_lang_mail']['subject'] ?? null);
});

lcmt_it('A translation with other placeholders leaves the mail in the default language (Polylang)', function () {
    if (!($pair = lcmt_lang_pair())) {
        return;
    }

    [$default, $other] = $pair;
    lcmt_lang_capture();
    [, $key] = lcmt_lang_mail_with_translation($default, $other, 'publish', 'Name: [name*] [email* email]');

    RequestLanguage::using($other, static fn() => Mailer::sendByKey($key, ['firstname' => 'Ann', 'email' => 'a@example.com']));

    lcmt_assert_same('Demande de Ann', $GLOBALS['lcmt_lang_mail']['subject'] ?? null);
});

lcmt_it('The REST route never takes [submissionLanguage] from the visitor', function () {
    lcmt_lang_capture();
    [$post, $key] = lcmt_it_template([
        '_lcmt_mail_content' => '[firstname*] [email* email] lang=[submissionLanguage]',
    ]);

    if (function_exists('pll_set_post_language') && ($pair = lcmt_lang_pair())) {
        pll_set_post_language($post->ID, $pair[0]);
    }

    add_filter('lcmt_mailer_captcha_providers', '__return_empty_array');

    $request = new WP_REST_Request('POST', '/lcmt-mailer/v1/forms/' . $key);
    $request->set_header('Content-Type', 'application/json');
    $request->set_body(wp_json_encode(['firstname' => 'Ann', 'email' => 'a@example.com', 'submissionLanguage' => 'Forged']));

    $response = RequestLanguage::using(null, static fn() => rest_do_request($request));

    remove_filter('lcmt_mailer_captcha_providers', '__return_empty_array');

    lcmt_assert_same(200, $response->get_status(), 'status');
    lcmt_assert_true(str_contains($GLOBALS['lcmt_lang_mail']['message'], 'lang='), 'placeholder replaced');
    lcmt_assert_same(false, str_contains($GLOBALS['lcmt_lang_mail']['message'], 'Forged'), 'visitor value ignored');
});
