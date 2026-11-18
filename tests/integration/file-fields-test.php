<?php

use LcmtDevMailer\FileField;
use LcmtDevMailer\Mailer;
use LcmtDevMailer\MetaFields;

/**
 * File fields inside WordPress: the limits, the hint and the form attributes
 * of a template with [name file] and [name files] fields, the "Maximum number
 * of files" setting and the placeholder of a [name files] field in the mail.
 * The uploads themselves are checked over HTTP (file-upload-test.sh).
 */

function lcmt_it_file_template(string $content, array $meta = []): array
{
    return lcmt_it_template($meta + [
        '_lcmt_mail_content'      => $content,
        '_lcmt_mail_file_formats' => 'jpg,png,pdf',
        '_lcmt_mail_file_max_size' => '5',
    ]);
}

lcmt_it('a template with a single file field keeps its form attributes and hint', function () {
    [$post, $key] = lcmt_it_file_template('[firstname*] [brief* file]', ['_lcmt_mail_file_max_files' => '4']);

    $attributes = FileField::formAttributesFor($post, $key);

    lcmt_assert_true(str_contains($attributes, 'data-lcmt-file-hint="Formats: JPG, PNG, PDF — 5 MB maximum"'), $attributes);
    lcmt_assert_true(!str_contains($attributes, 'data-lcmt-file-max-files'), 'no number of files: ' . $attributes);
    lcmt_assert_same('Formats: JPG, PNG, PDF — 5 MB maximum', lcmt_mailer_file_hint($key));
});

lcmt_it('a template with a files field puts the number of files on the form and in the hint', function () {
    [$post, $key] = lcmt_it_file_template('[firstname*] [attachment* files]', ['_lcmt_mail_file_max_files' => '2']);

    $attributes = FileField::formAttributesFor($post, $key);
    $hint       = 'Formats: JPG, PNG, PDF — 5 MB maximum per file, 2 files maximum';

    lcmt_assert_true(str_contains($attributes, ' enctype="multipart/form-data"'), $attributes);
    lcmt_assert_true(str_contains($attributes, 'data-lcmt-file-max-files="2"'), $attributes);
    lcmt_assert_true(str_contains($attributes, 'data-lcmt-file-hint="' . $hint . '"'), $attributes);
    lcmt_assert_same($hint, lcmt_mailer_file_hint($key));
    lcmt_assert_same(2, lcmt_mailer_file_max_files($key));
    lcmt_assert_same('.jpg,.jpeg,.png,.pdf', lcmt_mailer_file_accept($key));
    lcmt_assert_same(5 * FileField::MB, lcmt_mailer_file_max_size($key));
});

lcmt_it('the number of files defaults to 3, and is 0 for an unknown form', function () {
    [, $key] = lcmt_it_file_template('[attachment files]');

    lcmt_assert_same(3, lcmt_mailer_file_max_files($key));
    lcmt_assert_same(0, lcmt_mailer_file_max_files('it-no-such-form'));
    lcmt_assert_same('', lcmt_mailer_file_hint('it-no-such-form'));
});

lcmt_it('the lcmt_mailer_file_limits filter sees and can change the number of files', function () {
    [$post, $key] = lcmt_it_file_template('[attachment files]', ['_lcmt_mail_file_max_files' => '2']);

    $seen   = null;
    $filter = static function ($limits) use (&$seen) {
        $seen = $limits;
        $limits['max_files'] = 4;
        return $limits;
    };

    add_filter('lcmt_mailer_file_limits', $filter);

    try {
        $limits = FileField::limitsFor($post, $key);
    } finally {
        remove_filter('lcmt_mailer_file_limits', $filter);
    }

    lcmt_assert_same(2, $seen['max_files'] ?? null, 'payload');
    lcmt_assert_same(4, $limits['max_files']);
});

lcmt_it('a filter written before max_files existed keeps the stored number', function () {
    [$post, $key] = lcmt_it_file_template('[attachment files]', ['_lcmt_mail_file_max_files' => '2']);

    $filter = static fn() => ['formats' => ['pdf'], 'max_size' => FileField::MB];

    add_filter('lcmt_mailer_file_limits', $filter);

    try {
        $limits = FileField::limitsFor($post, $key);
    } finally {
        remove_filter('lcmt_mailer_file_limits', $filter);
    }

    lcmt_assert_same(['formats' => ['pdf'], 'max_size' => FileField::MB, 'max_files' => 2], $limits);
});

lcmt_it('the File uploads row saves the maximum number of files, within bounds', function () {
    [$post] = lcmt_it_file_template('[attachment files]');

    $admins = get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ID']);
    wp_set_current_user((int) ($admins[0] ?? 0));

    $saved = [];

    foreach (['5' => '5', '999' => (string) min(FileField::MAX_FILES_LIMIT, FileField::serverMaxFiles() ?: FileField::MAX_FILES_LIMIT), 'abc' => '3', '0' => '1'] as $posted => $expected) {
        $_POST = [
            'lcmt_mail_fields_nonce'    => wp_create_nonce('lcmt_mail_fields'),
            'lcmt_mail_file_settings'   => '1',
            'lcmt_mail_file_formats'    => ['pdf'],
            'lcmt_mail_file_max_size'   => '5',
            'lcmt_mail_file_max_files'  => $posted,
        ];

        MetaFields::save($post->ID);
        $saved[$posted] = [$expected, get_post_meta($post->ID, MetaFields::FIELD_FILE_MAX_FILES, true)];
    }

    $_POST = [];
    wp_set_current_user(0);

    foreach ($saved as $posted => [$expected, $actual]) {
        lcmt_assert_same($expected, $actual, 'posted ' . $posted);
    }
});

lcmt_it('the File uploads row shows the maximum number of files', function () {
    [$post] = lcmt_it_file_template('[attachment files]', ['_lcmt_mail_file_max_files' => '4']);

    ob_start();
    MetaFields::render($post);
    $html = (string) ob_get_clean();

    lcmt_assert_true(str_contains($html, 'Maximum number of files'), 'label');
    lcmt_assert_true((bool) preg_match('/name="lcmt_mail_file_max_files" value="4" min="1"/', $html), 'input');
    lcmt_assert_true(str_contains($html, '<code>files</code>'), 'placeholder help');
});

lcmt_it('the mail shows the file names of a files field', function () {
    [$post] = lcmt_it_file_template('Files: [attachment* files] / Brief: [brief file]');

    $form = Mailer::buildForm($post, ['[attachment]' => 'plan.pdf, logo.png', '[brief]' => 'brief.pdf']);

    lcmt_assert_true(str_contains((string) $form['content'], 'Files: plan.pdf, logo.png / Brief: brief.pdf'), (string) $form['content']);
});
