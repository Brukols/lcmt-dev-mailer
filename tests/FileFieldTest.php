<?php

use LcmtDevMailer\FileField;
use LcmtDevMailer\MetaFields;
use PHPUnit\Framework\TestCase;

/**
 * The pure logic of file fields: formats, sizes, names, upload errors. The
 * upload itself (is_uploaded_file, content check, mail attachment) is checked
 * on a site with wp-cli and curl.
 */
class FileFieldTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['lcmt_test_post_meta'] = [];
        $GLOBALS['lcmt_test_filters']   = [];
    }

    private function map(): array
    {
        return FileField::sanitizeFormatMap(FileField::FORMATS);
    }

    // ── Format map ──

    public function test_the_built_in_formats_are_all_kept(): void
    {
        $this->assertSame(['jpg', 'png', 'webp', 'gif', 'tiff', 'pdf', 'ai', 'eps', 'svg'], array_keys($this->map()));
    }

    public function test_each_format_maps_to_its_extensions_and_mime_types(): void
    {
        $map = $this->map();

        $this->assertSame(['jpg', 'jpeg'], $map['jpg']['extensions']);
        $this->assertSame(['image/jpeg'], $map['jpg']['mimes']);
        $this->assertSame(['tif', 'tiff'], $map['tiff']['extensions']);
        $this->assertSame(['image/tiff'], $map['tiff']['mimes']);
        $this->assertSame(['image/svg+xml'], $map['svg']['mimes']);
        $this->assertSame(['application/pdf'], $map['pdf']['mimes']);
    }

    public function test_an_illustrator_file_may_hold_a_pdf_or_postscript(): void
    {
        $map = $this->map();

        $this->assertTrue(FileField::mimeMatches('ai', 'application/pdf', $map));
        $this->assertTrue(FileField::mimeMatches('ai', 'application/postscript', $map));
        $this->assertTrue(FileField::mimeMatches('eps', 'application/postscript', $map));
        $this->assertFalse(FileField::mimeMatches('eps', 'application/pdf', $map));
    }

    public function test_a_content_of_another_format_does_not_match(): void
    {
        $map = $this->map();

        $this->assertFalse(FileField::mimeMatches('jpg', 'image/png', $map));
        $this->assertFalse(FileField::mimeMatches('pdf', 'text/plain', $map));
        $this->assertFalse(FileField::mimeMatches('svg', 'text/plain', $map));
        $this->assertFalse(FileField::mimeMatches('png', false, $map));
        $this->assertFalse(FileField::mimeMatches('png', '', $map));
        $this->assertFalse(FileField::mimeMatches('unknown', 'image/png', $map));
    }

    public function test_a_filtered_map_keeps_only_well_formed_entries(): void
    {
        $map = FileField::sanitizeFormatMap([
            'zip'        => ['label' => 'ZIP', 'extensions' => ['.ZIP'], 'mimes' => ['Application/Zip']],
            'bad id!'    => ['extensions' => ['x'], 'mimes' => ['a/b']],
            'noext'      => ['extensions' => [], 'mimes' => ['a/b']],
            'nomime'     => ['extensions' => ['abc'], 'mimes' => ['not a mime']],
            'notarray'   => 'png',
            7            => ['extensions' => ['x'], 'mimes' => ['a/b']],
        ]);

        $this->assertSame(['zip'], array_keys($map));
        $this->assertSame(['zip'], $map['zip']['extensions']);
        $this->assertSame(['application/zip'], $map['zip']['mimes']);
        $this->assertSame('ZIP', $map['zip']['short']);
    }

    public function test_a_filter_cannot_allow_scripts_or_pages(): void
    {
        $map = FileField::sanitizeFormatMap([
            'web' => ['extensions' => ['php', 'phtml', 'html', 'js', 'txt'], 'mimes' => ['text/plain']],
            'php' => ['extensions' => ['php'], 'mimes' => ['text/x-php']],
        ]);

        $this->assertSame(['txt'], $map['web']['extensions']);
        $this->assertArrayNotHasKey('php', $map);
        $this->assertNull(FileField::formatFor('php', ['php'], ['php' => ['extensions' => ['php'], 'mimes' => ['a/b']]]));
    }

    public function test_formats_go_through_the_filter(): void
    {
        $GLOBALS['lcmt_test_filters']['lcmt_mailer_file_formats'] = ['doc' => ['extensions' => ['docx'], 'mimes' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document']]];

        $this->assertSame(['doc'], array_keys(FileField::formats()));
    }

    // ── Chosen formats and size ──

    public function test_known_formats_are_kept_in_map_order(): void
    {
        $this->assertSame(['jpg', 'pdf', 'svg'], FileField::sanitizeFormats(['svg', 'pdf', 'exe', 'jpg', 'pdf'], $this->map()));
        $this->assertSame(['png', 'pdf'], FileField::sanitizeFormats('pdf, png', $this->map()));
        $this->assertSame([], FileField::sanitizeFormats(null, $this->map()));
        $this->assertSame([], FileField::sanitizeFormats([['jpg']], $this->map()));
    }

    public function test_a_template_without_formats_of_its_own_takes_jpg_png_and_pdf(): void
    {
        $this->assertSame(['jpg', 'png', 'pdf'], FileField::storedFormats(1, $this->map()));

        $GLOBALS['lcmt_test_post_meta'][1][MetaFields::FIELD_FILE_FORMATS] = 'gif,svg';
        $this->assertSame(['gif', 'svg'], FileField::storedFormats(1, $this->map()));

        $GLOBALS['lcmt_test_post_meta'][1][MetaFields::FIELD_FILE_FORMATS] = 'exe';
        $this->assertSame(['jpg', 'png', 'pdf'], FileField::storedFormats(1, $this->map()));
    }

    public function test_the_maximum_size_is_whole_megabytes_at_least_one(): void
    {
        $this->assertSame(10, FileField::sanitizeMaxMb(''));
        $this->assertSame(10, FileField::sanitizeMaxMb('abc'));
        $this->assertSame(10, FileField::sanitizeMaxMb('-5'));
        $this->assertSame(10, FileField::sanitizeMaxMb(null));
        $this->assertSame(1, FileField::sanitizeMaxMb('0'));
        $this->assertSame(25, FileField::sanitizeMaxMb('25'));
        $this->assertSame(2, FileField::sanitizeMaxMb('2.9'));
        $this->assertSame(8, FileField::sanitizeMaxMb(8));
    }

    public function test_a_template_without_a_size_of_its_own_takes_ten_megabytes(): void
    {
        $this->assertSame(10, FileField::storedMaxMb(2));

        $GLOBALS['lcmt_test_post_meta'][2][MetaFields::FIELD_FILE_MAX_SIZE] = '3';
        $this->assertSame(3, FileField::storedMaxMb(2));
    }

    public function test_the_size_never_goes_over_the_server_limit(): void
    {
        $this->assertSame(2 * FileField::MB, FileField::capBytes(10 * FileField::MB, 2 * FileField::MB));
        $this->assertSame(10 * FileField::MB, FileField::capBytes(10 * FileField::MB, 64 * FileField::MB));
        $this->assertSame(10 * FileField::MB, FileField::capBytes(10 * FileField::MB, 0));
        $this->assertSame(0, FileField::capBytes(-1, 0));
    }

    public function test_the_admin_input_stops_at_the_server_limit_in_whole_megabytes(): void
    {
        $this->assertSame(64, FileField::serverMaxMb(64 * FileField::MB));
        $this->assertSame(2, FileField::serverMaxMb((int) (2.5 * FileField::MB)));
        $this->assertSame(1, FileField::serverMaxMb(512 * 1024));
        $this->assertSame(1, FileField::serverMaxMb(0));
    }

    // ── Extensions and accept ──

    public function test_reads_the_last_extension_in_lower_case(): void
    {
        $this->assertSame('pdf', FileField::extension('Devis.Final.PDF'));
        $this->assertSame('', FileField::extension('README'));
        $this->assertSame('', FileField::extension('name.'));
        $this->assertSame('php', FileField::extension('photo.jpg.php'));
    }

    public function test_finds_the_allowed_format_of_an_extension(): void
    {
        $map = $this->map();

        $this->assertSame('jpg', FileField::formatFor('jpeg', ['png', 'jpg'], $map));
        $this->assertSame('tiff', FileField::formatFor('tif', ['tiff'], $map));
        $this->assertNull(FileField::formatFor('png', ['jpg', 'pdf'], $map));
        $this->assertNull(FileField::formatFor('', ['jpg'], $map));
    }

    public function test_builds_the_accept_attribute_from_the_extensions(): void
    {
        $this->assertSame('.jpg,.jpeg,.png,.pdf', FileField::accept(['jpg', 'png', 'pdf'], $this->map()));
        $this->assertSame(['tif', 'tiff', 'ai'], FileField::extensions(['tiff', 'ai'], $this->map()));
        $this->assertSame('', FileField::accept([], $this->map()));

        // Two formats sharing an extension list it once.
        $shared = ['a' => ['extensions' => ['ai', 'pdf']], 'b' => ['extensions' => ['pdf']]];
        $this->assertSame(['ai', 'pdf'], FileField::extensions(['a', 'b'], $shared));
    }

    // ── Upload errors ──

    public function test_sorts_out_what_is_wrong_with_an_upload(): void
    {
        $ok = ['name' => 'a.pdf', 'tmp_name' => '/tmp/php1', 'error' => UPLOAD_ERR_OK, 'size' => 10];

        $this->assertSame('', FileField::uploadProblem($ok));
        $this->assertSame('empty', FileField::uploadProblem(null));
        $this->assertSame('empty', FileField::uploadProblem(['tmp_name' => '', 'error' => UPLOAD_ERR_NO_FILE]));
        $this->assertSame('too_big', FileField::uploadProblem(['tmp_name' => '', 'error' => UPLOAD_ERR_INI_SIZE]));
        $this->assertSame('too_big', FileField::uploadProblem(['tmp_name' => '', 'error' => UPLOAD_ERR_FORM_SIZE]));
        $this->assertSame('failed', FileField::uploadProblem(['tmp_name' => '', 'error' => UPLOAD_ERR_PARTIAL]));
        $this->assertSame('failed', FileField::uploadProblem(['tmp_name' => '', 'error' => UPLOAD_ERR_NO_TMP_DIR]));
        $this->assertSame('failed', FileField::uploadProblem(['tmp_name' => '', 'error' => 'x']));
        $this->assertSame('failed', FileField::uploadProblem(['error' => UPLOAD_ERR_OK, 'tmp_name' => '']));
        $this->assertSame('failed', FileField::uploadProblem('a.pdf'));
        $this->assertSame('failed', FileField::uploadProblem(['name' => 'a.pdf']));
    }

    public function test_several_files_under_one_name_are_refused(): void
    {
        $this->assertSame('multiple', FileField::uploadProblem([
            'name'     => ['a.pdf', 'b.pdf'],
            'tmp_name' => ['/tmp/1', '/tmp/2'],
            'error'    => [0, 0],
        ]));
    }

    // ── Names and texts ──

    public function test_a_long_name_is_cut_keeping_its_extension(): void
    {
        $name = str_repeat('é', 200) . '.pdf';
        $cut  = FileField::shortenName($name);

        $this->assertSame(FileField::MAX_NAME_LENGTH, mb_strlen($cut, 'UTF-8'));
        $this->assertStringEndsWith('.pdf', $cut);
        $this->assertSame('brief.pdf', FileField::shortenName('brief.pdf'));
    }

    public function test_sizes_show_decimals_only_when_needed(): void
    {
        $this->assertSame(0, FileField::sizeDecimals(10 * FileField::MB));
        $this->assertSame(1, FileField::sizeDecimals((int) (1.5 * FileField::MB)));
        $this->assertSame(0, FileField::sizeDecimals(128 * FileField::MB + 5));
    }

    public function test_the_hint_lists_the_short_labels_and_the_size(): void
    {
        $this->assertSame('Formats: JPG, PNG, AI — 10 MB maximum', FileField::hintText(['jpg', 'png', 'ai'], $this->map(), '10 MB'));
    }

    public function test_the_form_attributes_are_escaped(): void
    {
        $this->assertSame(
            ' enctype="multipart/form-data" data-lcmt-file-extensions="jpg,pdf" data-lcmt-file-max-size="1024" data-lcmt-file-hint="A &quot;b&quot; &lt;c&gt;"',
            FileField::formAttributes(['jpg', 'pdf'], 1024, 'A "b" <c>')
        );
    }

    public function test_spots_a_file_field_among_parsed_fields(): void
    {
        $this->assertTrue(FileField::hasFileField(['a' => ['name' => 'a', 'required' => false, 'type' => 'file']]));
        $this->assertFalse(FileField::hasFileField(['a' => ['name' => 'a', 'required' => false, 'type' => 'text']]));
        $this->assertFalse(FileField::hasFileField([]));
    }

    // ── Several files ([name files]) ──

    public function test_a_files_field_counts_as_a_file_field(): void
    {
        $files = ['a' => ['name' => 'a', 'required' => false, 'type' => 'files']];
        $file  = ['a' => ['name' => 'a', 'required' => false, 'type' => 'file']];

        $this->assertSame('files', FileField::MULTIPLE_TYPE);
        $this->assertTrue(FileField::hasFileField($files));
        $this->assertTrue(FileField::hasMultipleFileField($files));
        $this->assertFalse(FileField::hasMultipleFileField($file));
        $this->assertFalse(FileField::hasMultipleFileField([]));
    }

    public function test_the_maximum_number_of_files_is_a_whole_number_of_at_least_one(): void
    {
        $this->assertSame(3, FileField::DEFAULT_MAX_FILES);
        $this->assertSame(3, FileField::sanitizeMaxFiles(''));
        $this->assertSame(3, FileField::sanitizeMaxFiles('abc'));
        $this->assertSame(3, FileField::sanitizeMaxFiles('-5'));
        $this->assertSame(3, FileField::sanitizeMaxFiles(null));
        $this->assertSame(1, FileField::sanitizeMaxFiles('0'));
        $this->assertSame(5, FileField::sanitizeMaxFiles('5'));
        $this->assertSame(2, FileField::sanitizeMaxFiles('2.9'));
        $this->assertSame(8, FileField::sanitizeMaxFiles(8));
        $this->assertSame(FileField::MAX_FILES_LIMIT, FileField::sanitizeMaxFiles('999'));
    }

    public function test_the_maximum_number_of_files_never_exceeds_what_php_takes(): void
    {
        $this->assertSame(5, FileField::capFiles(5, 20));
        $this->assertSame(4, FileField::capFiles(10, 4));
        $this->assertSame(10, FileField::capFiles(10, 0));
        $this->assertSame(1, FileField::capFiles(0, 0));
    }

    public function test_reads_the_stored_maximum_number_of_files(): void
    {
        $this->assertSame('_lcmt_mail_file_max_files', MetaFields::FIELD_FILE_MAX_FILES);
        $this->assertSame(3, FileField::storedMaxFiles(3));

        $GLOBALS['lcmt_test_post_meta'][3][MetaFields::FIELD_FILE_MAX_FILES] = '5';
        $this->assertSame(5, FileField::storedMaxFiles(3));
    }

    public function test_the_limits_hold_the_maximum_number_of_files(): void
    {
        $GLOBALS['lcmt_test_post_meta'][101][MetaFields::FIELD_FILE_MAX_FILES] = '4';

        $limits = FileField::limitsFor(new WP_Post(101), 'devis');

        $this->assertSame(['jpg', 'png', 'pdf'], $limits['formats']);
        $this->assertSame(10 * FileField::MB, $limits['max_size']);
        $this->assertSame(min(4, FileField::serverMaxFiles() ?: 4), $limits['max_files']);
    }

    public function test_a_filter_without_max_files_keeps_the_stored_one(): void
    {
        $GLOBALS['lcmt_test_post_meta'][102][MetaFields::FIELD_FILE_MAX_FILES] = '2';
        $GLOBALS['lcmt_test_filters']['lcmt_mailer_file_limits'] = ['formats' => ['pdf'], 'max_size' => FileField::MB];

        $limits = FileField::limitsFor(new WP_Post(102), 'devis');

        $this->assertSame(['formats' => ['pdf'], 'max_size' => FileField::MB, 'max_files' => 2], $limits);
    }

    public function test_a_filter_can_change_the_maximum_number_of_files(): void
    {
        $GLOBALS['lcmt_test_filters']['lcmt_mailer_file_limits'] = ['formats' => ['pdf'], 'max_size' => FileField::MB, 'max_files' => '2'];

        $this->assertSame(2, FileField::limitsFor(new WP_Post(103), 'devis')['max_files']);
    }

    public function test_the_hint_of_a_form_with_several_files_says_per_file_and_how_many(): void
    {
        $this->assertSame(
            'Formats: JPG, PNG, PDF — 5 MB maximum per file, 3 files maximum',
            FileField::hintText(['jpg', 'png', 'pdf'], $this->map(), '5 MB', 3)
        );
        $this->assertSame(
            'Formats: PDF — 5 MB maximum per file, 1 file maximum',
            FileField::hintText(['pdf'], $this->map(), '5 MB', 1)
        );
        $this->assertSame('Formats: PDF — 5 MB maximum', FileField::hintText(['pdf'], $this->map(), '5 MB', 0));
    }

    public function test_the_form_attributes_carry_the_maximum_number_of_files_when_given(): void
    {
        $this->assertSame(
            ' enctype="multipart/form-data" data-lcmt-file-extensions="pdf" data-lcmt-file-max-size="1024" data-lcmt-file-hint="h" data-lcmt-file-max-files="3"',
            FileField::formAttributes(['pdf'], 1024, 'h', 3)
        );
    }

    public function test_splits_the_array_shape_of_php_into_one_entry_per_file(): void
    {
        $entry = [
            'name'     => ['a.pdf', 'b.png'],
            'type'     => ['application/pdf', 'image/png'],
            'tmp_name' => ['/tmp/phpA', '/tmp/phpB'],
            'error'    => [UPLOAD_ERR_OK, UPLOAD_ERR_OK],
            'size'     => [10, 20],
        ];

        $this->assertSame([
            ['name' => 'a.pdf', 'type' => 'application/pdf', 'tmp_name' => '/tmp/phpA', 'error' => UPLOAD_ERR_OK, 'size' => 10],
            ['name' => 'b.png', 'type' => 'image/png', 'tmp_name' => '/tmp/phpB', 'error' => UPLOAD_ERR_OK, 'size' => 20],
        ], FileField::splitUploads($entry));
    }

    public function test_an_empty_selection_splits_into_no_file(): void
    {
        $this->assertSame([], FileField::splitUploads(null));
        $this->assertSame([], FileField::splitUploads(['name' => [''], 'tmp_name' => [''], 'error' => [UPLOAD_ERR_NO_FILE], 'size' => [0]]));
        $this->assertSame([], FileField::splitUploads(['name' => '', 'tmp_name' => '', 'error' => UPLOAD_ERR_NO_FILE, 'size' => 0]));
    }

    public function test_a_single_file_sent_without_brackets_counts_as_one(): void
    {
        $one = ['name' => 'a.pdf', 'type' => 'application/pdf', 'tmp_name' => '/tmp/phpA', 'error' => UPLOAD_ERR_OK, 'size' => 10];

        $this->assertSame([$one], FileField::splitUploads($one));
    }

    public function test_a_malformed_entry_cannot_be_split(): void
    {
        $this->assertNull(FileField::splitUploads('a.pdf'));
        $this->assertNull(FileField::splitUploads(['name' => ['a.pdf']]));
        $this->assertNull(FileField::splitUploads(['name' => ['a.pdf'], 'tmp_name' => '/tmp/x', 'error' => [0]]));
        $this->assertNull(FileField::splitUploads([
            'name' => [['a.pdf']], 'tmp_name' => [['/tmp/x']], 'error' => [[0]], 'size' => [[1]],
        ]));
    }

    public function test_more_files_than_allowed_are_refused_before_any_check(): void
    {
        $entry = [
            'name'     => ['a.pdf', 'b.pdf', 'c.pdf'],
            'tmp_name' => ['/tmp/a', '/tmp/b', '/tmp/c'],
            'error'    => [0, 0, 0],
            'size'     => [1, 1, 1],
        ];

        $result = FileField::checkMany($entry, ['formats' => ['pdf'], 'max_size' => FileField::MB, 'max_files' => 2], 'attachment');

        $this->assertSame(['The field "attachment" accepts 2 files at most.'], $result['errors']);
        $this->assertSame([], $result['files']);
    }

    public function test_no_file_is_no_error(): void
    {
        $limits = ['formats' => ['pdf'], 'max_size' => FileField::MB, 'max_files' => 3];

        $this->assertSame(['errors' => [], 'files' => []], FileField::checkMany(null, $limits, 'attachment'));
    }

    public function test_each_file_is_checked_and_its_error_names_the_field_and_the_file(): void
    {
        $entry = [
            'name'     => ['huge.pdf', 'broken.pdf', 'fake.pdf'],
            'tmp_name' => ['', '', '/tmp/not-uploaded'],
            'error'    => [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_PARTIAL, UPLOAD_ERR_OK],
            'size'     => [0, 0, 10],
        ];

        $result = FileField::checkMany($entry, ['formats' => ['pdf'], 'max_size' => 5 * FileField::MB, 'max_files' => 3], 'attachment');

        $this->assertSame([
            'The field "attachment": the file "huge.pdf" is too large, 5 MB maximum.',
            'The field "attachment": the file "broken.pdf" could not be received. Please try again.',
            'The field "attachment": the file "fake.pdf" could not be received. Please try again.',
        ], $result['errors']);
        $this->assertSame([], $result['files']);
    }

    public function test_a_malformed_entry_is_refused(): void
    {
        $result = FileField::checkMany('a.pdf', ['formats' => ['pdf'], 'max_size' => FileField::MB, 'max_files' => 3], 'attachment');

        $this->assertSame(['The file of the field "attachment" could not be received. Please try again.'], $result['errors']);
    }

    public function test_the_file_settings_show_only_for_the_fields_they_apply_to(): void
    {
        $field = static fn(string $type) => ['x' => ['name' => 'x', 'required' => false, 'type' => $type]];

        $this->assertSame(['row' => false, 'maxFiles' => false], FileField::settingsVisibility([]));
        $this->assertSame(['row' => false, 'maxFiles' => false], FileField::settingsVisibility($field('text')));
        $this->assertSame(['row' => true, 'maxFiles' => false], FileField::settingsVisibility($field('file')));
        $this->assertSame(['row' => true, 'maxFiles' => true], FileField::settingsVisibility($field('files')));
    }

    // ── _context ──

    public function test_the_context_of_a_multipart_body_is_decoded_from_json(): void
    {
        $this->assertSame(['page' => '/devis', 'seconds' => 4], FileField::decodeContext('{"page":"/devis","seconds":4}'));
        $this->assertSame(['page' => '/x'], FileField::decodeContext(['page' => '/x']));
        $this->assertNull(FileField::decodeContext('not json'));
        $this->assertNull(FileField::decodeContext('"a string"'));
        $this->assertNull(FileField::decodeContext('{"page":"' . str_repeat('a', 5000) . '"}'));
        $this->assertNull(FileField::decodeContext(str_repeat('[', 20) . str_repeat(']', 20)));
        $this->assertNull(FileField::decodeContext(null));
    }
}
