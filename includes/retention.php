<?php

namespace LcmtDevMailer;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Anonymizes or deletes received messages once their retention period ends.
 */
class Retention
{
    public const CRON_HOOK = 'lcmt_mailer_purge_submissions';

    /**
     * 3 years: the longest the CNIL accepts for prospect data.
     */
    public const DEFAULT_DAYS = 1095;
    public const MAX_DAYS = 3650;
    public const BATCH = 500;

    /**
     * A number of days from a settings field. Anything below $min or not a
     * number falls back to $default, so a typo can never purge every message
     * at the next run.
     *
     * @param mixed $value
     */
    public static function clampDays($value, int $min, int $default): int
    {
        if (!is_scalar($value) || !is_numeric($value)) {
            return $default;
        }

        $days = (int) $value;

        if ($days < $min) {
            return $default;
        }

        return min($days, self::MAX_DAYS);
    }

    /**
     * The UTC date before which rows are due, or null for "never".
     */
    public static function cutoff(int $days, int $now): ?string
    {
        return $days > 0 ? gmdate('Y-m-d H:i:s', $now - $days * 86400) : null;
    }

    /**
     * @return array{anonymized: int, deleted: int}
     */
    public static function run(): array
    {
        $now    = time();
        $done   = ['anonymized' => 0, 'deleted' => 0];
        $cutoff = (string) self::cutoff(SubmissionSettings::retentionDays(), $now);

        if (SubmissionSettings::retentionAction() === 'delete') {
            $done['deleted'] += self::drain(static fn() => SubmissionRepository::deleteBefore($cutoff, self::BATCH));
        } else {
            $done['anonymized'] += self::drain(static fn() => SubmissionRepository::anonymizeBefore($cutoff, self::BATCH));
        }

        $statsCutoff = self::cutoff(SubmissionSettings::statsRetentionDays(), $now);

        if ($statsCutoff) {
            $done['deleted'] += self::drain(static fn() => SubmissionRepository::deleteAnonymizedBefore($statsCutoff, self::BATCH));
        }

        return $done;
    }

    /**
     * Scheduled on every load rather than on activation, which updates from
     * GitHub never run.
     */
    public static function schedule(): void
    {
        if (!wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', self::CRON_HOOK);
        }
    }

    public static function unschedule(): void
    {
        wp_clear_scheduled_hook(self::CRON_HOOK);
    }

    /**
     * Repeat a batch until it comes back short.
     */
    private static function drain(callable $step): int
    {
        $total = 0;

        do {
            $count  = (int) $step();
            $total += $count;
        } while ($count === self::BATCH);

        return $total;
    }
}
