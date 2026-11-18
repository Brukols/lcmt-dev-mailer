<?php

namespace LcmtDevMailer;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * File fields ([name file] / [name* file], one file; [name files] /
 * [name* files], several): the formats, size and number of files a mail
 * template accepts, the checks of an upload, and the private copy attached
 * to the email. A file is never kept on the site: the copy is deleted once
 * the email is sent, and received messages only keep its name.
 */
class FileField
{
    public const TYPE = 'file';

    /**
     * A field taking several files: <input type="file" name="x[]" multiple>.
     */
    public const MULTIPLE_TYPE = 'files';

    /**
     * Files a [name files] field takes when the template sets nothing.
     */
    public const DEFAULT_MAX_FILES = 3;

    /**
     * The highest "Maximum number of files" an admin can set.
     */
    public const MAX_FILES_LIMIT = 20;

    public const MB = 1048576;

    /**
     * Ticked for a template that has no choice of its own.
     */
    public const DEFAULT_FORMATS = ['jpg', 'png', 'pdf'];

    public const DEFAULT_MAX_MB = 10;

    /**
     * Characters kept of a file name, its extension included.
     */
    public const MAX_NAME_LENGTH = 120;

    /**
     * Folder prefix of the private copies, under get_temp_dir().
     */
    public const TEMP_PREFIX = 'lcmt-mailer-';

    /**
     * The formats an admin can tick, each with the extensions a file name may
     * end with and the MIME types its content may really have. A file passes
     * only when both match the same format.
     */
    public const FORMATS = [
        'jpg'  => ['label' => 'JPG / JPEG', 'short' => 'JPG', 'extensions' => ['jpg', 'jpeg'], 'mimes' => ['image/jpeg']],
        'png'  => ['label' => 'PNG', 'short' => 'PNG', 'extensions' => ['png'], 'mimes' => ['image/png']],
        'webp' => ['label' => 'WEBP', 'short' => 'WEBP', 'extensions' => ['webp'], 'mimes' => ['image/webp']],
        'gif'  => ['label' => 'GIF', 'short' => 'GIF', 'extensions' => ['gif'], 'mimes' => ['image/gif']],
        'tiff' => ['label' => 'TIFF', 'short' => 'TIFF', 'extensions' => ['tif', 'tiff'], 'mimes' => ['image/tiff']],
        'pdf'  => ['label' => 'PDF', 'short' => 'PDF', 'extensions' => ['pdf'], 'mimes' => ['application/pdf']],
        // Illustrator CS and later save a PDF inside, older files are PostScript.
        'ai'   => ['label' => 'AI (Adobe Illustrator)', 'short' => 'AI', 'extensions' => ['ai'], 'mimes' => ['application/pdf', 'application/postscript']],
        'eps'  => ['label' => 'EPS', 'short' => 'EPS', 'extensions' => ['eps'], 'mimes' => ['application/postscript', 'image/x-eps']],
        // Only ever attached to the email, never shown on the site.
        'svg'  => ['label' => 'SVG', 'short' => 'SVG', 'extensions' => ['svg'], 'mimes' => ['image/svg+xml']],
    ];

    /**
     * Extensions refused whatever a filter adds: scripts a web server could
     * run, and pages a browser would open as part of a site.
     */
    private const BLOCKED_EXTENSIONS = [
        'php', 'php3', 'php4', 'php5', 'php7', 'php8', 'phtml', 'pht', 'phps', 'phar',
        'cgi', 'pl', 'py', 'rb', 'sh', 'asp', 'aspx', 'jsp', 'exe', 'bat', 'cmd', 'com',
        'js', 'mjs', 'htm', 'html', 'shtml', 'xhtml', 'htaccess',
    ];

    /**
     * Limits by mail post ID, for the request.
     *
     * @var array<int, array{formats: list<string>, max_size: int, max_files: int}>
     */
    private static array $limits = [];

    // ─── Pure helpers ───────────────────────────────────────────

    /**
     * A format map from a filter, kept to well-formed entries: a slug id,
     * lowercase alphanumeric extensions (none of BLOCKED_EXTENSIONS), MIME
     * types of the type/subtype shape. Labels default to the id.
     *
     * @param mixed $map
     * @return array<string, array{label: string, short: string, extensions: list<string>, mimes: list<string>}>
     */
    public static function sanitizeFormatMap($map): array
    {
        $clean = [];

        foreach (is_array($map) ? $map : [] as $id => $format) {
            if (!is_string($id) || !preg_match('/^[a-z0-9_-]{1,20}$/', $id) || !is_array($format)) {
                continue;
            }

            $extensions = array_values(array_unique(array_filter(
                array_map(static fn($e) => is_string($e) ? strtolower(trim($e, " .\t")) : '', (array) ($format['extensions'] ?? [])),
                static fn(string $e) => preg_match('/^[a-z0-9]{1,10}$/', $e) && !in_array($e, self::BLOCKED_EXTENSIONS, true)
            )));

            $mimes = array_values(array_unique(array_filter(
                array_map(static fn($m) => is_string($m) ? strtolower(trim($m)) : '', (array) ($format['mimes'] ?? [])),
                static fn(string $m) => (bool) preg_match('#^[a-z0-9][a-z0-9.+-]*/[a-z0-9][a-z0-9.+-]*$#', $m)
            )));

            if (!$extensions || !$mimes) {
                continue;
            }

            $label = is_string($format['label'] ?? null) && $format['label'] !== '' ? $format['label'] : strtoupper($id);
            $short = is_string($format['short'] ?? null) && $format['short'] !== '' ? $format['short'] : strtoupper($id);

            $clean[$id] = ['label' => $label, 'short' => $short, 'extensions' => $extensions, 'mimes' => $mimes];
        }

        return $clean;
    }

    /**
     * Known format ids from a stored or posted value (a comma list or an
     * array), in the order of the map, without duplicates.
     *
     * @param mixed $raw
     * @param array<string, array> $map
     * @return list<string>
     */
    public static function sanitizeFormats($raw, array $map): array
    {
        if (is_string($raw)) {
            $raw = explode(',', $raw);
        }

        $wanted = array_map(static fn($id) => is_string($id) ? trim($id) : '', is_array($raw) ? $raw : []);

        return array_values(array_filter(array_keys($map), static fn($id) => in_array($id, $wanted, true)));
    }

    /**
     * A stored or posted maximum size, in whole MB: at least 1, the default
     * when empty or not a number.
     *
     * @param mixed $raw
     */
    public static function sanitizeMaxMb($raw): int
    {
        if (is_int($raw) || (is_string($raw) && preg_match('/^\s*\d+(\.\d+)?\s*$/', $raw)) || is_float($raw)) {
            return max(1, (int) floor((float) $raw));
        }

        return self::DEFAULT_MAX_MB;
    }

    /**
     * A stored or posted maximum number of files: a whole number from 1 to
     * MAX_FILES_LIMIT, the default when empty or not a number.
     *
     * @param mixed $raw
     */
    public static function sanitizeMaxFiles($raw): int
    {
        if (is_int($raw) || (is_string($raw) && preg_match('/^\s*\d+(\.\d+)?\s*$/', $raw)) || is_float($raw)) {
            return min(self::MAX_FILES_LIMIT, max(1, (int) floor((float) $raw)));
        }

        return self::DEFAULT_MAX_FILES;
    }

    /**
     * Files a field may take: the template's number, never more than PHP
     * receives in one request (max_file_uploads; 0, unknown, leaves it).
     */
    public static function capFiles(int $files, int $serverFiles): int
    {
        $files = max(1, $files);

        return $serverFiles > 0 ? min($files, $serverFiles) : $files;
    }

    /**
     * The largest whole MB value the server takes, at least 1 (the size is
     * capped in bytes anyway when the server allows less).
     */
    public static function serverMaxMb(int $serverBytes): int
    {
        return max(1, intdiv(max(0, $serverBytes), self::MB));
    }

    /**
     * Bytes a file may weigh: the template's size, never more than the
     * server takes. A server limit of 0 (unknown) leaves the size as is.
     */
    public static function capBytes(int $bytes, int $serverBytes): int
    {
        $bytes = max(0, $bytes);

        return $serverBytes > 0 ? min($bytes, $serverBytes) : $bytes;
    }

    /**
     * The extension of a file name, lowercase, '' without one.
     */
    public static function extension(string $name): string
    {
        $dot = strrpos($name, '.');

        return $dot === false || $dot === strlen($name) - 1 ? '' : strtolower(substr($name, $dot + 1));
    }

    /**
     * @param list<string> $formats
     * @param array<string, array> $map
     * @return list<string>
     */
    public static function extensions(array $formats, array $map): array
    {
        $extensions = [];

        foreach ($formats as $id) {
            foreach ($map[$id]['extensions'] ?? [] as $extension) {
                $extensions[] = $extension;
            }
        }

        return array_values(array_unique($extensions));
    }

    /**
     * The value of an `accept` attribute: ".jpg,.jpeg,.png,.pdf".
     *
     * @param list<string> $formats
     * @param array<string, array> $map
     */
    public static function accept(array $formats, array $map): string
    {
        return implode(',', array_map(static fn(string $e) => '.' . $e, self::extensions($formats, $map)));
    }

    /**
     * The format, among the allowed ones, that a file name's extension
     * belongs to.
     *
     * @param list<string> $formats
     * @param array<string, array> $map
     */
    public static function formatFor(string $extension, array $formats, array $map): ?string
    {
        if ($extension === '' || in_array($extension, self::BLOCKED_EXTENSIONS, true)) {
            return null;
        }

        foreach ($formats as $id) {
            if (in_array($extension, $map[$id]['extensions'] ?? [], true)) {
                return $id;
            }
        }

        return null;
    }

    /**
     * Whether the MIME type the content really has is one of its format's.
     *
     * @param string|false $realMime
     * @param array<string, array> $map
     */
    public static function mimeMatches(string $format, $realMime, array $map): bool
    {
        return is_string($realMime) && $realMime !== '' && in_array(strtolower($realMime), $map[$format]['mimes'] ?? [], true);
    }

    /**
     * What is wrong with an entry of $_FILES before looking at its content:
     * '' (a file to check), 'empty' (no file chosen), 'multiple' (several
     * files under one name), 'too_big' (over the server's limit) or 'failed'.
     *
     * @param mixed $file
     */
    public static function uploadProblem($file): string
    {
        if ($file === null) {
            return 'empty';
        }

        if (!is_array($file) || !array_key_exists('error', $file) || !array_key_exists('tmp_name', $file)) {
            return 'failed';
        }

        if (is_array($file['error']) || is_array($file['tmp_name']) || is_array($file['name'] ?? '')) {
            return 'multiple';
        }

        $error = is_numeric($file['error']) ? (int) $file['error'] : -1;

        return match ($error) {
            UPLOAD_ERR_OK                           => is_string($file['tmp_name']) && $file['tmp_name'] !== '' ? '' : 'failed',
            UPLOAD_ERR_NO_FILE                      => 'empty',
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'too_big',
            default                                 => 'failed',
        };
    }

    /**
     * A file name cut to MAX_NAME_LENGTH characters, keeping its extension.
     */
    public static function shortenName(string $name, int $max = self::MAX_NAME_LENGTH): string
    {
        if (mb_strlen($name, 'UTF-8') <= $max) {
            return $name;
        }

        $extension = self::extension($name);
        $suffix    = $extension === '' ? '' : '.' . $extension;
        $stem      = mb_substr($name, 0, mb_strlen($name, 'UTF-8') - mb_strlen($suffix, 'UTF-8'), 'UTF-8');

        return mb_substr($stem, 0, max(1, $max - mb_strlen($suffix, 'UTF-8')), 'UTF-8') . $suffix;
    }

    /**
     * Decimals to show a size with: none for whole MB, one otherwise.
     */
    public static function sizeDecimals(int $bytes): int
    {
        return $bytes % self::MB === 0 || $bytes >= 100 * self::MB ? 0 : 1;
    }

    /**
     * "Formats: JPG, PNG, PDF — 10 MB maximum"; with a number of files (a
     * template with a [name files] field), "Formats: JPG, PNG, PDF — 10 MB
     * maximum per file, 3 files maximum".
     *
     * @param list<string> $formats
     * @param array<string, array> $map
     */
    public static function hintText(array $formats, array $map, string $size, int $maxFiles = 0): string
    {
        $labels = implode(', ', array_map(static fn(string $id) => (string) ($map[$id]['short'] ?? strtoupper($id)), $formats));

        if ($maxFiles > 0) {
            return sprintf(
                /* translators: 1: list of file formats such as "JPG, PNG, PDF", 2: a size such as "10 MB", 3: a number of files */
                _n('Formats: %1$s — %2$s maximum per file, %3$d file maximum', 'Formats: %1$s — %2$s maximum per file, %3$d files maximum', $maxFiles, 'lcmt-dev-mailer'),
                $labels,
                $size,
                $maxFiles
            );
        }

        /* translators: 1: list of file formats such as "JPG, PNG, PDF", 2: a size such as "10 MB" */
        return sprintf(__('Formats: %1$s — %2$s maximum', 'lcmt-dev-mailer'), $labels, $size);
    }

    /**
     * The attributes the form script reads its limits from, escaped, with a
     * leading space.
     *
     * data-lcmt-file-max-files only comes with a number of files (a template
     * with a [name files] field).
     *
     * @param list<string> $extensions
     */
    public static function formAttributes(array $extensions, int $maxBytes, string $hint, int $maxFiles = 0): string
    {
        return ' enctype="multipart/form-data"'
            . ' data-lcmt-file-extensions="' . esc_attr(implode(',', $extensions)) . '"'
            . ' data-lcmt-file-max-size="' . esc_attr((string) $maxBytes) . '"'
            . ' data-lcmt-file-hint="' . esc_attr($hint) . '"'
            . ($maxFiles > 0 ? ' data-lcmt-file-max-files="' . esc_attr((string) $maxFiles) . '"' : '');
    }

    /**
     * Whether parsed fields hold a file field.
     *
     * @param array<string, array{name: string, required: bool, type: string}> $fields
     */
    public static function hasFileField(array $fields): bool
    {
        foreach ($fields as $field) {
            if ($field['type'] === self::TYPE || $field['type'] === self::MULTIPLE_TYPE) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether parsed fields hold a field taking several files.
     *
     * @param array<string, array{name: string, required: bool, type: string}> $fields
     */
    public static function hasMultipleFileField(array $fields): bool
    {
        foreach ($fields as $field) {
            if ($field['type'] === self::MULTIPLE_TYPE) {
                return true;
            }
        }

        return false;
    }

    /**
     * The entry of a [name files] field in $_FILES, one entry per file in
     * the shape of a single upload: PHP gives `name[]` as arrays of names,
     * tmp_names, errors and sizes. Slots without a file (an empty selection)
     * are left out; a single file sent without brackets counts as one.
     *
     * @param mixed $entry
     * @return list<array{name: mixed, type: mixed, tmp_name: mixed, error: mixed, size: mixed}>|null null when the entry is malformed.
     */
    public static function splitUploads($entry): ?array
    {
        if ($entry === null) {
            return [];
        }

        if (!is_array($entry) || !array_key_exists('error', $entry) || !array_key_exists('tmp_name', $entry)) {
            return null;
        }

        if (!is_array($entry['error'])) {
            if (is_array($entry['tmp_name']) || is_array($entry['name'] ?? '')) {
                return null;
            }

            return self::uploadProblem($entry) === 'empty' ? [] : [$entry];
        }

        if (!is_array($entry['tmp_name'])) {
            return null;
        }

        $files = [];

        foreach ($entry['error'] as $i => $error) {
            $file = [];

            foreach (['name', 'type', 'tmp_name', 'error', 'size'] as $part) {
                $value = $part === 'error' ? $error : ($entry[$part][$i] ?? null);

                // name[a][b]: not what a file input sends.
                if (is_array($value) || ($part !== 'error' && isset($entry[$part]) && !is_array($entry[$part]))) {
                    return null;
                }

                $file[$part] = $value;
            }

            if (self::uploadProblem($file) !== 'empty') {
                $files[] = $file;
            }
        }

        return $files;
    }

    /**
     * The `_context` value of a request: an object in a JSON body, a JSON
     * string in a multipart one.
     *
     * @param mixed $raw
     * @return mixed
     */
    public static function decodeContext($raw)
    {
        if (!is_string($raw)) {
            return $raw;
        }

        if (strlen($raw) > 4096) {
            return null;
        }

        $decoded = json_decode($raw, true, 8);

        return is_array($decoded) ? $decoded : null;
    }

    // ─── WordPress glue ─────────────────────────────────────────

    /**
     * The formats sites can offer, through the lcmt_mailer_file_formats filter.
     *
     * @return array<string, array{label: string, short: string, extensions: list<string>, mimes: list<string>}>
     */
    public static function formats(): array
    {
        /**
         * Filter the file formats a mail template can accept.
         *
         * @param array $formats id => {label, short, extensions, mimes}
         */
        return self::sanitizeFormatMap(apply_filters('lcmt_mailer_file_formats', self::FORMATS));
    }

    /**
     * The upload limit of the server, in bytes (upload_max_filesize and
     * post_max_size, through upload_size_limit).
     */
    public static function serverLimit(): int
    {
        return function_exists('wp_max_upload_size') ? (int) wp_max_upload_size() : 0;
    }

    /**
     * The formats ticked on a template, the defaults when none is stored.
     *
     * @return list<string>
     */
    public static function storedFormats(int $postId, array $map): array
    {
        $stored = get_post_meta($postId, MetaFields::FIELD_FILE_FORMATS, true);
        $ids    = $stored === '' ? [] : self::sanitizeFormats($stored, $map);

        return $ids ?: self::sanitizeFormats(self::DEFAULT_FORMATS, $map);
    }

    public static function storedMaxMb(int $postId): int
    {
        $stored = get_post_meta($postId, MetaFields::FIELD_FILE_MAX_SIZE, true);

        return $stored === '' ? self::DEFAULT_MAX_MB : self::sanitizeMaxMb($stored);
    }

    public static function storedMaxFiles(int $postId): int
    {
        $stored = get_post_meta($postId, MetaFields::FIELD_FILE_MAX_FILES, true);

        return $stored === '' ? self::DEFAULT_MAX_FILES : self::sanitizeMaxFiles($stored);
    }

    /**
     * Files PHP receives in one request (max_file_uploads), 0 when unknown.
     */
    public static function serverMaxFiles(): int
    {
        return max(0, (int) ini_get('max_file_uploads'));
    }

    /**
     * What a template's file fields accept, filtered, and capped to the
     * server's limits. max_files applies to each [name files] field.
     *
     * @return array{formats: list<string>, max_size: int, max_files: int}
     */
    public static function limitsFor(\WP_Post $post, string $key = ''): array
    {
        if (isset(self::$limits[$post->ID])) {
            return self::$limits[$post->ID];
        }

        $map    = self::formats();
        $server = self::serverLimit();

        $serverFiles = self::serverMaxFiles();

        $limits = [
            'formats'   => self::storedFormats($post->ID, $map),
            'max_size'  => self::capBytes(self::storedMaxMb($post->ID) * self::MB, $server),
            'max_files' => self::capFiles(self::storedMaxFiles($post->ID), $serverFiles),
        ];

        /**
         * Filter what the file fields of a template accept.
         *
         * @param array    $limits {formats: list of format ids, max_size: bytes per file, max_files: files per [name files] field}
         * @param string   $key    The form key.
         * @param \WP_Post $post   The mail post.
         */
        $filtered = apply_filters('lcmt_mailer_file_limits', $limits, $key, $post);

        if (is_array($filtered)) {
            $formats = self::sanitizeFormats($filtered['formats'] ?? [], $map);

            $limits = [
                'formats'   => $formats ?: $limits['formats'],
                'max_size'  => self::capBytes(is_numeric($filtered['max_size'] ?? null) ? (int) $filtered['max_size'] : $limits['max_size'], $server),
                'max_files' => self::capFiles(is_numeric($filtered['max_files'] ?? null) ? (int) $filtered['max_files'] : $limits['max_files'], $serverFiles),
            ];
        }

        return self::$limits[$post->ID] = $limits;
    }

    /**
     * Limits of the form with this key, null when there is no such form.
     *
     * @return array{formats: list<string>, max_size: int, max_files: int}|null
     */
    public static function limitsForKey(string $key): ?array
    {
        $post = Mailer::getPostByKey($key);

        return $post ? self::limitsFor($post, $key) : null;
    }

    public static function sizeText(int $bytes): string
    {
        return (string) size_format($bytes, self::sizeDecimals($bytes));
    }

    /**
     * The hint line; $multiple (a template with a [name files] field) adds
     * "per file" and the number of files.
     *
     * @param array{formats: list<string>, max_size: int, max_files?: int} $limits
     */
    public static function hint(array $limits, bool $multiple = false): string
    {
        return self::hintText(
            $limits['formats'],
            self::formats(),
            self::sizeText($limits['max_size']),
            $multiple ? (int) ($limits['max_files'] ?? self::DEFAULT_MAX_FILES) : 0
        );
    }

    /**
     * The hint line of the form with this key, '' when there is no such form.
     */
    public static function hintForKey(string $key): string
    {
        $post = Mailer::getPostByKey($key);

        if (!$post) {
            return '';
        }

        return self::hint(self::limitsFor($post, $key), self::hasMultipleFileField(self::fieldsOf($post)));
    }

    /**
     * Attributes for the <form> of a template with file fields, '' otherwise.
     */
    public static function formAttributesFor(\WP_Post $post, string $key): string
    {
        $fields = self::fieldsOf($post);

        if (!self::hasFileField($fields)) {
            return '';
        }

        $limits   = self::limitsFor($post, $key);
        $multiple = self::hasMultipleFileField($fields);

        return self::formAttributes(
            self::extensions($limits['formats'], self::formats()),
            $limits['max_size'],
            self::hint($limits, $multiple),
            $multiple ? $limits['max_files'] : 0
        );
    }

    /**
     * @return array<string, array{name: string, required: bool, type: string}>
     */
    private static function fieldsOf(\WP_Post $post): array
    {
        return FieldParser::parse(
            MetaFields::get($post->ID, 'to'),
            MetaFields::get($post->ID, 'reply_to'),
            MetaFields::get($post->ID, 'subject'),
            MetaFields::get($post->ID, 'content')
        );
    }

    /**
     * Check the upload of a file field.
     *
     * @param mixed $file The $_FILES entry, null when none was sent.
     * @param array{formats: list<string>, max_size: int} $limits
     * @return array{error: ?string, name: string, tmp: string} name is '' when no file was chosen.
     */
    public static function check($file, array $limits, string $field): array
    {
        $none   = ['error' => null, 'name' => '', 'tmp' => ''];
        $result = self::inspect($file, $limits);

        $message = match ($result['problem']) {
            '', 'empty' => null,
            'multiple'  => sprintf(
                /* translators: %s: the field name */
                __('The field "%s" accepts a single file.', 'lcmt-dev-mailer'),
                $field
            ),
            'too_big'   => sprintf(
                /* translators: 1: the field name, 2: a size such as "10 MB" */
                __('The file of the field "%1$s" is too large: %2$s maximum.', 'lcmt-dev-mailer'),
                $field,
                self::sizeText($limits['max_size'])
            ),
            'format'    => sprintf(
                /* translators: 1: the field name, 2: list of file formats such as "JPG, PNG, PDF" */
                __('The file of the field "%1$s" must be in one of these formats: %2$s.', 'lcmt-dev-mailer'),
                $field,
                self::formatsText($limits['formats'])
            ),
            default     => self::failedMessage($field),
        };

        if ($message !== null) {
            return ['error' => $message] + $none;
        }

        return $result['problem'] === '' ? ['error' => null, 'name' => $result['name'], 'tmp' => $result['tmp']] : $none;
    }

    /**
     * Check the upload of a [name files] field: at most max_files files, then
     * each one exactly as check() does a single file. Every refused file gets
     * its own error, naming the field and the file.
     *
     * @param mixed $entry The $_FILES entry, null when none was sent.
     * @param array{formats: list<string>, max_size: int, max_files: int} $limits
     * @return array{errors: list<string>, files: list<array{name: string, tmp: string}>} files is empty as soon as one error is found.
     */
    public static function checkMany($entry, array $limits, string $field): array
    {
        $uploads = self::splitUploads($entry);

        if ($uploads === null) {
            return ['errors' => [self::failedMessage($field)], 'files' => []];
        }

        $max = max(1, (int) ($limits['max_files'] ?? self::DEFAULT_MAX_FILES));

        if (count($uploads) > $max) {
            return ['errors' => [sprintf(
                /* translators: 1: the field name, 2: a number of files */
                _n('The field "%1$s" accepts %2$d file at most.', 'The field "%1$s" accepts %2$d files at most.', $max, 'lcmt-dev-mailer'),
                $field,
                $max
            )], 'files' => []];
        }

        $errors = [];
        $files  = [];

        foreach ($uploads as $upload) {
            $result = self::inspect($upload, $limits);

            if ($result['problem'] === '') {
                $files[] = ['name' => $result['name'], 'tmp' => $result['tmp']];
                continue;
            }

            $name = self::cleanName(is_string($upload['name'] ?? null) ? $upload['name'] : '');

            $errors[] = match ($result['problem']) {
                'too_big' => sprintf(
                    /* translators: 1: the field name, 2: a file name, 3: a size such as "10 MB" */
                    __('The field "%1$s": the file "%2$s" is too large, %3$s maximum.', 'lcmt-dev-mailer'),
                    $field,
                    $name,
                    self::sizeText($limits['max_size'])
                ),
                'format'  => sprintf(
                    /* translators: 1: the field name, 2: a file name, 3: list of file formats such as "JPG, PNG, PDF" */
                    __('The field "%1$s": the file "%2$s" must be in one of these formats: %3$s.', 'lcmt-dev-mailer'),
                    $field,
                    $name,
                    self::formatsText($limits['formats'])
                ),
                default   => sprintf(
                    /* translators: 1: the field name, 2: a file name */
                    __('The field "%1$s": the file "%2$s" could not be received. Please try again.', 'lcmt-dev-mailer'),
                    $field,
                    $name
                ),
            };
        }

        return $errors ? ['errors' => $errors, 'files' => []] : ['errors' => [], 'files' => $files];
    }

    /**
     * What is wrong with one upload: '' (accepted, with its clean name and
     * temporary path), or uploadProblem()'s answer, 'failed' (not a real
     * upload, empty), 'too_big' (over the template's size) or 'format'.
     *
     * @param mixed $file
     * @param array{formats: list<string>, max_size: int} $limits
     * @return array{problem: string, name: string, tmp: string}
     */
    private static function inspect($file, array $limits): array
    {
        $refused = static fn(string $problem) => ['problem' => $problem, 'name' => '', 'tmp' => ''];
        $problem = self::uploadProblem($file);

        if ($problem !== '') {
            return $refused($problem);
        }

        $tmp = (string) $file['tmp_name'];

        if (!is_uploaded_file($tmp)) {
            return $refused('failed');
        }

        $bytes = (int) filesize($tmp);

        if ($bytes > $limits['max_size'] || (int) ($file['size'] ?? 0) > $limits['max_size']) {
            return $refused('too_big');
        }

        if ($bytes === 0) {
            return $refused('failed');
        }

        $map    = self::formats();
        $name   = self::cleanName(is_string($file['name'] ?? null) ? $file['name'] : '');
        $format = self::formatFor(self::extension($name), $limits['formats'], $map);

        if ($format === null || !self::contentMatches($tmp, $name, $format, $map)) {
            return $refused('format');
        }

        return ['problem' => '', 'name' => $name, 'tmp' => $tmp];
    }

    private static function failedMessage(string $field): string
    {
        return sprintf(
            /* translators: %s: the field name */
            __('The file of the field "%s" could not be received. Please try again.', 'lcmt-dev-mailer'),
            $field
        );
    }

    /**
     * "JPG, PNG, PDF": the short labels of the allowed formats.
     *
     * @param list<string> $formats
     */
    private static function formatsText(array $formats): string
    {
        $map = self::formats();

        return implode(', ', array_map(static fn(string $id) => (string) ($map[$id]['short'] ?? $id), $formats));
    }

    /**
     * The visitor's file name, safe for a file system and for the email:
     * no path, no special characters (sanitize_file_name()), at most
     * MAX_NAME_LENGTH characters.
     */
    public static function cleanName(string $raw): string
    {
        $name = sanitize_file_name(wp_basename(str_replace('\\', '/', $raw)));

        return self::shortenName($name === '' ? 'file' : $name);
    }

    /**
     * Whether the content of the file is really of its format: the MIME type
     * the content has (fileinfo, or the image header) must be one of the
     * format's, and WordPress's own check must agree with it. Without
     * fileinfo, only images can be checked: other formats are refused.
     *
     * @param array<string, array> $map
     */
    private static function contentMatches(string $tmp, string $name, string $format, array $map): bool
    {
        $real = false;

        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $real  = $finfo ? finfo_file($finfo, $tmp) : false;
        }

        if ((!is_string($real) || !self::mimeMatches($format, $real, $map)) && function_exists('wp_get_image_mime')) {
            $image = wp_get_image_mime($tmp);
            $real  = $image ?: $real;
        }

        if (!self::mimeMatches($format, $real, $map)) {
            return false;
        }

        $real = strtolower((string) $real);

        // The extensions of this format only, mapped to the type the content
        // has, and allowed for this one check (SVG, AI and EPS are not in
        // WordPress's list of upload types).
        $mimes = [implode('|', $map[$format]['extensions']) => $real];
        $allow = static fn($allowed) => (is_array($allowed) ? $allowed : []) + $mimes;

        add_filter('upload_mimes', $allow, PHP_INT_MAX);

        try {
            $checked = wp_check_filetype_and_ext($tmp, $name, $mimes);
        } finally {
            remove_filter('upload_mimes', $allow, PHP_INT_MAX);
        }

        return !empty($checked['ext']) && ($checked['type'] ?? '') === $real && empty($checked['proper_filename']);
    }

    /**
     * Move a checked upload to a private folder of its own, under its clean
     * name, so the email carries the visitor's file name.
     *
     * @return string|null The path, null when it could not be moved.
     */
    public static function privateCopy(string $tmp, string $name): ?string
    {
        $dir = trailingslashit(get_temp_dir()) . self::TEMP_PREFIX . wp_generate_password(24, false, false);

        if (!@mkdir($dir, 0700)) {
            return null;
        }

        $path = $dir . '/' . $name;

        if (!@move_uploaded_file($tmp, $path)) {
            @rmdir($dir);
            return null;
        }

        @chmod($path, 0600);

        return $path;
    }

    /**
     * Delete the private copies and their folders.
     *
     * @param list<string> $paths
     */
    public static function cleanup(array $paths): void
    {
        foreach ($paths as $path) {
            $dir = dirname($path);

            // Only folders made by privateCopy().
            if (!str_starts_with(basename($dir), self::TEMP_PREFIX)) {
                continue;
            }

            if (is_file($path)) {
                @unlink($path);
            }

            @rmdir($dir);
        }
    }

    /**
     * Whether a multipart request was dropped by PHP for being larger than
     * post_max_size: its fields and files are then all empty.
     */
    public static function bodyTooLarge(array $body, array $files): bool
    {
        if ($body !== [] || $files !== []) {
            return false;
        }

        $length = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
        $limit  = function_exists('wp_convert_hr_to_bytes') ? (int) wp_convert_hr_to_bytes((string) ini_get('post_max_size')) : 0;

        return $limit > 0 && $length > $limit;
    }
}
