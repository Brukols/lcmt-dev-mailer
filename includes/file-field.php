<?php

namespace LcmtDevMailer;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * File fields ([name file] / [name* file]): the formats and size a mail
 * template accepts, the checks of an upload, and the private copy attached
 * to the email. The file is never kept on the site: the copy is deleted once
 * the email is sent, and received messages only keep its name.
 */
class FileField
{
    public const TYPE = 'file';

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
     * @var array<int, array{formats: list<string>, max_size: int}>
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
     * "Formats: JPG, PNG, PDF — 10 MB maximum".
     *
     * @param list<string> $formats
     * @param array<string, array> $map
     */
    public static function hintText(array $formats, array $map, string $size): string
    {
        $labels = array_map(static fn(string $id) => (string) ($map[$id]['short'] ?? strtoupper($id)), $formats);

        /* translators: 1: list of file formats such as "JPG, PNG, PDF", 2: a size such as "10 MB" */
        return sprintf(__('Formats: %1$s — %2$s maximum', 'lcmt-dev-mailer'), implode(', ', $labels), $size);
    }

    /**
     * The attributes the form script reads its limits from, escaped, with a
     * leading space.
     *
     * @param list<string> $extensions
     */
    public static function formAttributes(array $extensions, int $maxBytes, string $hint): string
    {
        return ' enctype="multipart/form-data"'
            . ' data-lcmt-file-extensions="' . esc_attr(implode(',', $extensions)) . '"'
            . ' data-lcmt-file-max-size="' . esc_attr((string) $maxBytes) . '"'
            . ' data-lcmt-file-hint="' . esc_attr($hint) . '"';
    }

    /**
     * Whether parsed fields hold a file field.
     *
     * @param array<string, array{name: string, required: bool, type: string}> $fields
     */
    public static function hasFileField(array $fields): bool
    {
        foreach ($fields as $field) {
            if ($field['type'] === self::TYPE) {
                return true;
            }
        }

        return false;
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

    /**
     * What a template's file fields accept, filtered, and capped to the
     * server's limit.
     *
     * @return array{formats: list<string>, max_size: int}
     */
    public static function limitsFor(\WP_Post $post, string $key = ''): array
    {
        if (isset(self::$limits[$post->ID])) {
            return self::$limits[$post->ID];
        }

        $map    = self::formats();
        $server = self::serverLimit();

        $limits = [
            'formats'  => self::storedFormats($post->ID, $map),
            'max_size' => self::capBytes(self::storedMaxMb($post->ID) * self::MB, $server),
        ];

        /**
         * Filter what the file fields of a template accept.
         *
         * @param array    $limits {formats: list of format ids, max_size: bytes}
         * @param string   $key    The form key.
         * @param \WP_Post $post   The mail post.
         */
        $filtered = apply_filters('lcmt_mailer_file_limits', $limits, $key, $post);

        if (is_array($filtered)) {
            $formats = self::sanitizeFormats($filtered['formats'] ?? [], $map);

            $limits = [
                'formats'  => $formats ?: $limits['formats'],
                'max_size' => self::capBytes(is_numeric($filtered['max_size'] ?? null) ? (int) $filtered['max_size'] : $limits['max_size'], $server),
            ];
        }

        return self::$limits[$post->ID] = $limits;
    }

    /**
     * Limits of the form with this key, null when there is no such form.
     *
     * @return array{formats: list<string>, max_size: int}|null
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
     * @param array{formats: list<string>, max_size: int} $limits
     */
    public static function hint(array $limits): string
    {
        return self::hintText($limits['formats'], self::formats(), self::sizeText($limits['max_size']));
    }

    /**
     * Attributes for the <form> of a template with file fields, '' otherwise.
     */
    public static function formAttributesFor(\WP_Post $post, string $key): string
    {
        $fields = FieldParser::parse(
            MetaFields::get($post->ID, 'to'),
            MetaFields::get($post->ID, 'reply_to'),
            MetaFields::get($post->ID, 'subject'),
            MetaFields::get($post->ID, 'content')
        );

        if (!self::hasFileField($fields)) {
            return '';
        }

        $limits = self::limitsFor($post, $key);

        return self::formAttributes(self::extensions($limits['formats'], self::formats()), $limits['max_size'], self::hint($limits));
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
        $none    = ['error' => null, 'name' => '', 'tmp' => ''];
        $map     = self::formats();
        $formats = implode(', ', array_map(static fn(string $id) => (string) ($map[$id]['short'] ?? $id), $limits['formats']));
        $size    = self::sizeText($limits['max_size']);

        $tooBig = static fn() => ['error' => sprintf(
            /* translators: 1: the field name, 2: a size such as "10 MB" */
            __('The file of the field "%1$s" is too large: %2$s maximum.', 'lcmt-dev-mailer'),
            $field,
            $size
        )] + $none;

        $failed = static fn() => ['error' => sprintf(
            /* translators: %s: the field name */
            __('The file of the field "%s" could not be received. Please try again.', 'lcmt-dev-mailer'),
            $field
        )] + $none;

        $wrongFormat = static fn() => ['error' => sprintf(
            /* translators: 1: the field name, 2: list of file formats such as "JPG, PNG, PDF" */
            __('The file of the field "%1$s" must be in one of these formats: %2$s.', 'lcmt-dev-mailer'),
            $field,
            $formats
        )] + $none;

        switch (self::uploadProblem($file)) {
            case 'empty':
                return $none;
            case 'multiple':
                return ['error' => sprintf(
                    /* translators: %s: the field name */
                    __('The field "%s" accepts a single file.', 'lcmt-dev-mailer'),
                    $field
                )] + $none;
            case 'too_big':
                return $tooBig();
            case 'failed':
                return $failed();
        }

        $tmp = (string) $file['tmp_name'];

        if (!is_uploaded_file($tmp)) {
            return $failed();
        }

        $bytes = (int) filesize($tmp);

        if ($bytes > $limits['max_size'] || (int) ($file['size'] ?? 0) > $limits['max_size']) {
            return $tooBig();
        }

        if ($bytes === 0) {
            return $failed();
        }

        $name   = self::cleanName(is_string($file['name'] ?? null) ? $file['name'] : '');
        $format = self::formatFor(self::extension($name), $limits['formats'], $map);

        if ($format === null) {
            return $wrongFormat();
        }

        if (!self::contentMatches($tmp, $name, $format, $map)) {
            return $wrongFormat();
        }

        return ['error' => null, 'name' => $name, 'tmp' => $tmp];
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
