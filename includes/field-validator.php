<?php

namespace LcmtDevMailer;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Cleans submitted values and checks them against their field type.
 */
class FieldValidator
{
    /**
     * Digits a phone number holds: enough for the shortest national numbers,
     * and room for the 15 digits of E.164 written with a 00 prefix and a (0)
     * trunk digit.
     */
    private const PHONE_MIN_DIGITS = 6;
    private const PHONE_MAX_DIGITS = 20;

    /**
     * Turn a submitted value into plain text, keeping line breaks for textareas.
     *
     * @param mixed $raw
     */
    public static function sanitize($raw, string $type): string
    {
        if (!is_scalar($raw)) {
            return '';
        }

        $value = $type === 'textarea'
            ? sanitize_textarea_field((string) $raw)
            : sanitize_text_field((string) $raw);

        return trim($value);
    }

    /**
     * Check a non-empty value against its field type.
     */
    public static function isValid(string $value, string $type): bool
    {
        return match ($type) {
            'email'  => is_email($value) !== false,
            'number' => is_numeric($value),
            'url'    => self::isUrl($value),
            'tel'    => self::isPhone($value),
            default  => true,
        };
    }

    /**
     * Check a non-empty value against its field type.
     *
     * @return string|null The error message, or null when the value is valid.
     */
    public static function error(string $value, string $type, string $name): ?string
    {
        if (self::isValid($value, $type)) {
            return null;
        }

        return match ($type) {
            /* translators: %s: the field name */
            'email'  => sprintf(__('The field "%s" must be a valid email address.', 'lcmt-dev-mailer'), $name),
            /* translators: %s: the field name */
            'number' => sprintf(__('The field "%s" must be a number.', 'lcmt-dev-mailer'), $name),
            /* translators: %s: the field name */
            'url'    => sprintf(__('The field "%s" must be a valid URL.', 'lcmt-dev-mailer'), $name),
            /* translators: %s: the field name */
            default  => sprintf(__('The field "%s" must be a valid phone number.', 'lcmt-dev-mailer'), $name),
        };
    }

    public static function isUrl(string $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_URL) !== false
            && in_array(strtolower((string) parse_url($value, PHP_URL_SCHEME)), ['http', 'https'], true);
    }

    /**
     * Digits with an optional leading +, and the separators people type or
     * paste: spaces (non-breaking ones included, as copied from a French
     * formatted text), dots, dashes, slashes and brackets.
     */
    public static function isPhone(string $value): bool
    {
        if (!preg_match('/^\+?[0-9\s\p{Zs}().\/-]+$/u', $value)) {
            return false;
        }

        $digits = preg_match_all('/[0-9]/', $value);

        return $digits >= self::PHONE_MIN_DIGITS && $digits <= self::PHONE_MAX_DIGITS;
    }
}
