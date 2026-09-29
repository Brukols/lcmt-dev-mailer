<?php

namespace LcmtDevMailer;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Turns submissions into spreadsheet rows.
 */
class SubmissionCsv
{
    public const COLUMNS = [
        'created_at', 'form_key', 'status', 'mail_sent', 'page_path', 'landing_path',
        'channel', 'utm_source', 'utm_medium', 'utm_campaign', 'referrer_host',
        'device', 'locale', 'form_seconds',
    ];

    /**
     * One header row, then one row per submission: the context columns, then
     * one column per field name found across all rows.
     *
     * @param list<array> $rows Hydrated submissions.
     * @return list<list<string>>
     */
    public static function table(array $rows): array
    {
        $names = [];

        foreach ($rows as $row) {
            foreach ($row['fields'] ?? [] as $field) {
                $names[$field['name']] = true;
            }
        }

        $names = array_keys($names);
        $table = [array_merge(self::COLUMNS, $names)];

        foreach ($rows as $row) {
            $line   = [];
            $values = array_column($row['fields'] ?? [], 'value', 'name');

            foreach (self::COLUMNS as $column) {
                $line[] = self::cell((string) ($row[$column] ?? ''));
            }

            foreach ($names as $name) {
                $line[] = self::cell((string) ($values[$name] ?? ''));
            }

            $table[] = $line;
        }

        return $table;
    }

    /**
     * Prefix a value a spreadsheet would run as a formula with a quote, so a
     * visitor cannot plant one in the export (CSV injection).
     */
    public static function cell(string $value): string
    {
        return preg_match('/^[=+\-@\t\r]/', $value) ? "'" . $value : $value;
    }
}
