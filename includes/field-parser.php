<?php

namespace LcmtDevMailer;

if (!defined('ABSPATH')) {
    exit;
}

class FieldParser
{
    /**
     * Supported input types that can be specified in placeholders.
     */
    private const VALID_TYPES = [
        'text', 'email', 'tel', 'phone', 'url', 'number',
        'password', 'date', 'textarea', 'hidden',
    ];

    /**
     * Parse placeholders from the mail content + subject + to fields.
     *
     * Syntax:
     *   [firstname*]        — required, type defaults to "text"
     *   [phone]             — optional, type defaults to "text"
     *   [email* email]      — required, type "email"
     *   [company* text]     — required, type "text"
     *   [phone* phone]      — required, type "phone" (mapped to "tel")
     *   [message textarea]  — optional, type "textarea"
     *
     * @return array<string, array{name: string, required: bool, type: string}>
     */
    public static function parse(string ...$sources): array
    {
        $fields = [];

        $combined = implode(' ', $sources);

        // Match: [name], [name*], [name type], [name* type]
        preg_match_all(
            self::PATTERN,
            $combined,
            $matches,
            PREG_SET_ORDER
        );

        foreach ($matches as $match) {
            $name     = $match[1];
            $required = isset($match[2]) && $match[2] === '*';
            $type     = isset($match[3]) ? strtolower($match[3]) : 'text';

            // Normalize type aliases
            $type = self::normalizeType($type);

            // If already found, merge: required wins, explicit type wins over default "text"
            if (isset($fields[$name])) {
                if ($required) {
                    $fields[$name]['required'] = true;
                }
                if ($type !== 'text' && $fields[$name]['type'] === 'text') {
                    $fields[$name]['type'] = $type;
                }
                continue;
            }

            $fields[$name] = [
                'name'     => $name,
                'required' => $required,
                'type'     => $type,
            ];
        }

        return $fields;
    }

    /**
     * A placeholder: [name], [name*], [name type], [name* type].
     * Group 1: name, group 2: "*", group 3: type.
     */
    public const PATTERN = '/\[([a-zA-Z_][a-zA-Z0-9_]*)(\*)?(?:\s+([a-zA-Z]+))?\]/';

    /**
     * Built-in placeholders the server fills, never asked to the visitor.
     */
    public const SERVER_FILLED = ['submissionLanguage'];

    /**
     * The parsed fields a visitor fills: all but the server-filled ones.
     *
     * @param array<string, array{name: string, required: bool, type: string}> $fields
     * @return array<string, array{name: string, required: bool, type: string}>
     */
    public static function formFields(array $fields): array
    {
        return array_diff_key($fields, array_flip(self::SERVER_FILLED));
    }

    /**
     * Callback of the lcmt_pat/protected_patterns filter of a translation
     * plugin (Polylang AI Translate): placeholders of mail posts are kept
     * untouched when they are translated. Other content keeps its brackets
     * translatable ("[Voir plus]" on a page).
     *
     * @param array<int, string> $patterns
     * @return array<int, string>
     */
    public static function protectedPatterns(array $patterns, string $objectType = '', string $subtype = ''): array
    {
        if ($objectType === 'post' && $subtype === PostType::SLUG) {
            $patterns[] = self::PATTERN;
        }

        return $patterns;
    }

    /**
     * Generate a TypeScript interface from parsed fields.
     */
    public static function toTypeScript(string $key, array $fields): string
    {
        $interfaceName = self::toPascalCase($key) . 'FormData';

        $lines = ["export interface {$interfaceName} {"];

        foreach ($fields as $field) {
            $optional = $field['required'] ? '' : '?';
            $tsType   = $field['type'] === 'number' ? 'number' : 'string';
            $lines[]  = "  {$field['name']}{$optional}: {$tsType};";
        }

        $lines[] = '}';

        return implode("\n", $lines);
    }

    /**
     * Normalize type aliases to valid HTML input types.
     */
    private static function normalizeType(string $type): string
    {
        // Alias: phone -> tel
        if ($type === 'phone') {
            return 'tel';
        }

        // If valid, use as-is; otherwise default to text
        if (in_array($type, self::VALID_TYPES, true)) {
            return $type;
        }

        return 'text';
    }

    private static function toPascalCase(string $kebab): string
    {
        return str_replace(' ', '', ucwords(str_replace('-', ' ', $kebab)));
    }
}
