<?php

namespace LcmtDevMailer;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * The value of a `date` field as the mail shows it. The browser submits
 * YYYY-MM-DD (the format of <input type="date">), which reads poorly in a
 * mail: it is shown in the site's date format instead (Settings → General),
 * which the `lcmt_mailer_date_format` filter can change.
 */
class DateValue
{
    /**
     * The date in the given PHP date format, or the value untouched when it
     * is not a valid YYYY-MM-DD date or the format is empty.
     */
    public static function format(string $value, string $format): string
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', trim($value), new \DateTimeZone('UTC'));

        if ($format === '' || !$date || $date->format('Y-m-d') !== trim($value)) {
            return $value;
        }

        // wp_date() translates month and day names; it is missing only in the unit tests.
        if (function_exists('wp_date')) {
            return (string) wp_date($format, $date->getTimestamp(), new \DateTimeZone('UTC'));
        }

        return $date->format($format);
    }

    /**
     * The format dates are shown in, in the mails of this form.
     */
    public static function mailFormat(\WP_Post $post): string
    {
        /**
         * Filters the format of the `date` fields in the mails.
         *
         * @param string   $format A PHP date format; the site's date format by default.
         * @param \WP_Post $post   The mail post.
         */
        return (string) apply_filters('lcmt_mailer_date_format', (string) get_option('date_format', 'Y-m-d'), $post);
    }
}
