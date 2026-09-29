<?php

namespace LcmtDevMailer;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Every query on the submissions table.
 */
class SubmissionRepository
{
    public const STATUSES = ['new', 'read', 'processed', 'spam'];

    /**
     * Columns the stats screen may group by.
     */
    private const GROUPABLE = ['form_key', 'channel', 'utm_campaign', 'page_path', 'landing_path', 'referrer_host', 'device', 'browser', 'os'];

    /**
     * Seconds a row with mail_sent = 0 is left alone by the failure queries:
     * rows are saved before their email goes out, so a younger one is most
     * likely still being sent.
     */
    public const FAILURE_GRACE = 120;

    public static function table(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'lcmt_mailer_submissions';
    }

    public static function insert(array $row): int
    {
        global $wpdb;

        return $wpdb->insert(self::table(), $row) ? (int) $wpdb->insert_id : 0;
    }

    public static function update(int $id, array $data): bool
    {
        global $wpdb;

        return $wpdb->update(self::table(), $data, ['id' => $id]) !== false;
    }

    public static function find(int $id): ?array
    {
        global $wpdb;

        $row = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . self::table() . ' WHERE id = %d', $id), ARRAY_A);

        return $row ? self::hydrate($row) : null;
    }

    /**
     * @param array{form_key?: string, status?: string, failed?: bool, channel?: string, search?: string} $filters
     */
    public static function search(array $filters, int $perPage = 0, int $page = 1): array
    {
        global $wpdb;

        [$where, $args] = self::where($filters);

        $sql = 'SELECT * FROM ' . self::table() . " WHERE {$where} ORDER BY created_at DESC, id DESC";

        if ($perPage > 0) {
            $sql   .= ' LIMIT %d OFFSET %d';
            $args[] = $perPage;
            $args[] = max(0, ($page - 1) * $perPage);
        }

        return array_map([self::class, 'hydrate'], $wpdb->get_results(self::prepare($sql, $args), ARRAY_A) ?: []);
    }

    public static function count(array $filters): int
    {
        global $wpdb;

        [$where, $args] = self::where($filters);

        return (int) $wpdb->get_var(self::prepare('SELECT COUNT(*) FROM ' . self::table() . " WHERE {$where}", $args));
    }

    /**
     * @param list<int> $ids
     */
    public static function setStatus(array $ids, string $status): void
    {
        global $wpdb;

        if (!$ids || !in_array($status, self::STATUSES, true)) {
            return;
        }

        $wpdb->query(self::prepare(
            'UPDATE ' . self::table() . ' SET status = %s WHERE id IN (' . self::idList($ids) . ')',
            [$status]
        ));
    }

    /**
     * @param list<int> $ids
     */
    public static function delete(array $ids): void
    {
        global $wpdb;

        if ($ids) {
            $wpdb->query('DELETE FROM ' . self::table() . ' WHERE id IN (' . self::idList($ids) . ')');
        }
    }

    /**
     * @param list<int> $ids
     */
    public static function anonymize(array $ids): void
    {
        global $wpdb;

        if ($ids) {
            $wpdb->query(self::prepare(
                'UPDATE ' . self::table() . ' SET ' . self::anonymizeSet() . ' WHERE id IN (' . self::idList($ids) . ')',
                [current_time('mysql', true)]
            ));
        }
    }

    /**
     * Anonymize up to $limit rows sent before $cutoff.
     *
     * @return int Rows changed.
     */
    public static function anonymizeBefore(string $cutoff, int $limit): int
    {
        global $wpdb;

        return (int) $wpdb->query($wpdb->prepare(
            'UPDATE ' . self::table() . ' SET ' . self::anonymizeSet() . '
             WHERE anonymized_at IS NULL AND created_at < %s LIMIT %d',
            current_time('mysql', true),
            $cutoff,
            $limit
        ));
    }

    public static function deleteBefore(string $cutoff, int $limit): int
    {
        global $wpdb;

        return (int) $wpdb->query($wpdb->prepare(
            'DELETE FROM ' . self::table() . ' WHERE created_at < %s LIMIT %d',
            $cutoff,
            $limit
        ));
    }

    public static function deleteAnonymizedBefore(string $cutoff, int $limit): int
    {
        global $wpdb;

        return (int) $wpdb->query($wpdb->prepare(
            'DELETE FROM ' . self::table() . ' WHERE anonymized_at IS NOT NULL AND created_at < %s LIMIT %d',
            $cutoff,
            $limit
        ));
    }

    /**
     * Rows still holding personal data whose values contain $needle.
     */
    public static function findContaining(string $needle): array
    {
        global $wpdb;

        $rows = $wpdb->get_results($wpdb->prepare(
            'SELECT * FROM ' . self::table() . ' WHERE anonymized_at IS NULL AND fields LIKE %s ORDER BY id',
            '%' . $wpdb->esc_like($needle) . '%'
        ), ARRAY_A);

        return array_map([self::class, 'hydrate'], $rows ?: []);
    }

    public static function countUnread(): int
    {
        return self::count(['status' => 'new']);
    }

    /**
     * Failed emails nobody dealt with, newest first.
     */
    public static function unresolvedFailures(string $since, int $limit): array
    {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare(
            'SELECT id, form_key, created_at, mail_error FROM ' . self::table() . ' WHERE ' . self::unresolvedFailure() . '
             ORDER BY created_at DESC LIMIT %d',
            $since,
            self::settledBefore(),
            $limit
        ), ARRAY_A) ?: [];
    }

    public static function countUnresolvedFailures(string $since): int
    {
        global $wpdb;

        return (int) $wpdb->get_var($wpdb->prepare(
            'SELECT COUNT(*) FROM ' . self::table() . ' WHERE ' . self::unresolvedFailure(),
            $since,
            self::settledBefore()
        ));
    }

    /**
     * @return list<string>
     */
    public static function formKeys(): array
    {
        global $wpdb;

        return $wpdb->get_col('SELECT DISTINCT form_key FROM ' . self::table() . ' ORDER BY form_key') ?: [];
    }

    /**
     * @return list<array{label: string, total: int}>
     */
    public static function countBy(string $column, string $since, int $limit = 10): array
    {
        global $wpdb;

        if (!in_array($column, self::GROUPABLE, true)) {
            return [];
        }

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT {$column} AS label, COUNT(*) AS total FROM " . self::table() . "
             WHERE created_at >= %s AND status <> 'spam'
             GROUP BY {$column} ORDER BY total DESC LIMIT %d",
            $since,
            $limit
        ), ARRAY_A) ?: [];

        return array_map(static fn(array $row) => ['label' => (string) $row['label'], 'total' => (int) $row['total']], $rows);
    }

    /**
     * @return list<array{label: string, total: int}> Label as YYYY-MM, oldest first.
     */
    public static function countByMonth(string $since): array
    {
        global $wpdb;

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT DATE_FORMAT(created_at, '%%Y-%%m') AS label, COUNT(*) AS total FROM " . self::table() . "
             WHERE created_at >= %s AND status <> 'spam'
             GROUP BY label ORDER BY label",
            $since
        ), ARRAY_A) ?: [];

        return array_map(static fn(array $row) => ['label' => (string) $row['label'], 'total' => (int) $row['total']], $rows);
    }

    /**
     * @return array{total: int, failed: int, avg_seconds: ?int}
     */
    public static function totals(string $since): array
    {
        global $wpdb;

        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT COUNT(*) AS total, SUM(mail_sent = 0) AS failed, AVG(form_seconds) AS avg_seconds
             FROM " . self::table() . " WHERE created_at >= %s AND status <> 'spam'",
            $since
        ), ARRAY_A) ?: [];

        return [
            'total'       => (int) ($row['total'] ?? 0),
            'failed'      => (int) ($row['failed'] ?? 0),
            'avg_seconds' => isset($row['avg_seconds']) ? (int) round((float) $row['avg_seconds']) : null,
        ];
    }

    // ─── Private helpers ────────────────────────────────────────

    /**
     * @return array{0: string, 1: list<mixed>}
     */
    private static function where(array $filters): array
    {
        global $wpdb;

        $clauses = [];
        $args    = [];

        $status = (string) ($filters['status'] ?? '');

        if (in_array($status, self::STATUSES, true)) {
            $clauses[] = 'status = %s';
            $args[]    = $status;
        } else {
            // Spam stays out of sight unless asked for, like comments.
            $clauses[] = "status <> 'spam'";
        }

        if (!empty($filters['form_key'])) {
            $clauses[] = 'form_key = %s';
            $args[]    = (string) $filters['form_key'];
        }

        if (!empty($filters['failed'])) {
            $clauses[] = 'mail_sent = 0';
        }

        if (!empty($filters['channel'])) {
            $clauses[] = 'channel = %s';
            $args[]    = (string) $filters['channel'];
        }

        if (!empty($filters['search'])) {
            $clauses[] = 'fields LIKE %s';
            $args[]    = '%' . $wpdb->esc_like((string) $filters['search']) . '%';
        }

        return [implode(' AND ', $clauses), $args];
    }

    /**
     * wpdb::prepare() refuses a query without placeholders.
     */
    private static function prepare(string $sql, array $args): string
    {
        global $wpdb;

        return $args ? $wpdb->prepare($sql, ...$args) : $sql;
    }

    /**
     * @param list<int> $ids
     */
    private static function idList(array $ids): string
    {
        return implode(',', array_map('absint', $ids));
    }

    /**
     * The SET clause of both anonymizations. Takes one %s: the anonymization
     * date. Besides the personal columns, the exact time of the message and
     * the time spent on the form go too: together with the page and the
     * source, they could match a row back to one visit in an analytics or
     * server log. Only the day stays, so retention cutoffs only ever see the
     * row as older than it was, never younger.
     */
    private static function anonymizeSet(): string
    {
        $clear = array_map(static fn(string $column) => "{$column} = NULL", SubmissionData::PERSONAL_COLUMNS);

        return implode(', ', $clear) . ', created_at = DATE(created_at), form_seconds = NULL, anonymized_at = %s';
    }

    /**
     * Takes two %s: the date the admin last dismissed the failure banner, then
     * settledBefore().
     */
    private static function unresolvedFailure(): string
    {
        return "mail_sent = 0 AND anonymized_at IS NULL AND status NOT IN ('processed', 'spam') AND created_at > %s AND created_at <= %s";
    }

    /**
     * The UTC date before which an unsent row is a failure, not a send in progress.
     */
    private static function settledBefore(): string
    {
        return gmdate('Y-m-d H:i:s', time() - self::FAILURE_GRACE);
    }

    private static function hydrate(array $row): array
    {
        $row['fields'] = $row['fields'] === null ? null : (json_decode($row['fields'], true) ?: []);

        return $row;
    }
}
