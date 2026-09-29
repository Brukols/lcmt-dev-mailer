<?php

namespace LcmtDevMailer;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Shapes what a visitor typed into a form for storage, and back.
 */
class SubmissionData
{
    /**
     * Columns that hold what the visitor typed, or text that can name them
     * (a mail error quotes the address it failed on). Anonymization clears
     * exactly these and keeps the context columns for statistics.
     */
    public const PERSONAL_COLUMNS = ['fields', 'mail_error'];

    /**
     * Field types never written to the database.
     */
    private const SKIPPED_TYPES = ['password'];

    /**
     * Freeze the submitted values with the name and type of their field, so
     * a submission still reads right after its template changed.
     *
     * @param array<string, array{name: string, required: bool, type: string}> $fields FieldParser::parse() output.
     * @param array<string, string> $values Sanitized values by field name.
     * @return list<array{name: string, type: string, value: string}>
     */
    public static function snapshot(array $fields, array $values): array
    {
        $snapshot = [];

        foreach ($fields as $field) {
            if (in_array($field['type'], self::SKIPPED_TYPES, true)) {
                continue;
            }

            $snapshot[] = [
                'name'  => $field['name'],
                'type'  => $field['type'],
                'value' => (string) ($values[$field['name']] ?? ''),
            ];
        }

        return $snapshot;
    }

    /**
     * Turn a snapshot back into the placeholders Mailer::sendByKey() expects.
     *
     * @param list<array{name: string, type: string, value: string}> $snapshot
     * @return array<string, string>
     */
    public static function toPlaceholders(array $snapshot): array
    {
        $placeholders = [];

        foreach ($snapshot as $field) {
            $placeholders['[' . $field['name'] . ']']  = $field['value'];
            $placeholders['[' . $field['name'] . '*]'] = $field['value'];
        }

        return $placeholders;
    }

    /**
     * Whether one of the values is this email address, ignoring case.
     *
     * @param list<array{name: string, type: string, value: string}> $snapshot
     */
    public static function containsEmail(array $snapshot, string $email): bool
    {
        $email = strtolower(trim($email));

        if ($email === '') {
            return false;
        }

        foreach ($snapshot as $field) {
            if (strtolower(trim($field['value'])) === $email) {
                return true;
            }
        }

        return false;
    }

    /**
     * A short line naming the sender in lists: the first text value and the
     * first email address.
     *
     * @param list<array{name: string, type: string, value: string}> $snapshot
     */
    public static function summary(array $snapshot): string
    {
        $parts = [];

        foreach (['text', 'email'] as $type) {
            foreach ($snapshot as $field) {
                if ($field['type'] === $type && $field['value'] !== '') {
                    $parts[] = $field['value'];
                    break;
                }
            }
        }

        return implode(' · ', $parts);
    }
}
