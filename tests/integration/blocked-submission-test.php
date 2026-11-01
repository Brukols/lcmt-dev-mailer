<?php

use LcmtDevMailer\Captcha;
use LcmtDevMailer\CaptchaProvider;
use LcmtDevMailer\CaptchaUnavailable;
use LcmtDevMailer\FailureNotice;
use LcmtDevMailer\MetaFields;
use LcmtDevMailer\Retention;
use LcmtDevMailer\SubmissionRepository;
use LcmtDevMailer\SubmissionSettings;

/**
 * Providers that block every message: one says the proof is wrong, one says
 * the verification service did not answer.
 */
abstract class LcmtItBlockingProvider implements CaptchaProvider
{
    public static function label(): string
    {
        return 'IT';
    }

    public static function isReady(): bool
    {
        return true;
    }

    public static function registerRoutes(): void
    {
    }

    public static function widget(): string
    {
        return '';
    }

    public static function renderSettings(): void
    {
    }
}

class LcmtItRejectingProvider extends LcmtItBlockingProvider
{
    public static function id(): string
    {
        return 'it-reject';
    }

    public static function verify(array $body): bool
    {
        return false;
    }
}

class LcmtItSilentProvider extends LcmtItBlockingProvider
{
    public static function id(): string
    {
        return 'it-silent';
    }

    public static function verify(array $body): bool
    {
        throw new CaptchaUnavailable('down');
    }
}

/**
 * Post a form to the REST endpoint with the blocking providers registered.
 */
function lcmt_bs_post(string $key, array $body): WP_REST_Response
{
    $providers = static fn(array $list) => $list + [
        'it-reject' => LcmtItRejectingProvider::class,
        'it-silent' => LcmtItSilentProvider::class,
    ];

    add_filter('lcmt_mailer_captcha_providers', $providers);
    lcmt_it_mail_ok();

    try {
        $request = new WP_REST_Request('POST', '/lcmt-mailer/v1/forms/' . $key);
        $request->set_header('content-type', 'application/json');
        $request->set_body(wp_json_encode($body));

        return rest_do_request($request);
    } finally {
        remove_filter('lcmt_mailer_captcha_providers', $providers);
    }
}

function lcmt_bs_valid(): array
{
    return ['firstname' => 'Ann', 'email' => 'ann@example.com', 'password' => 'x', 'message' => 'Hi'];
}

/**
 * Send a valid submission to a template protected by a blocking provider.
 *
 * @return array{0: WP_REST_Response, 1: string} The response and the form key.
 */
function lcmt_bs_send(string $provider = 'it-reject', array $meta = []): array
{
    // The template choice is only honoured while the provider is registered.
    $providers = static fn(array $list) => $list + [
        'it-reject' => LcmtItRejectingProvider::class,
        'it-silent' => LcmtItSilentProvider::class,
    ];
    add_filter('lcmt_mailer_captcha_providers', $providers);
    [, $key] = lcmt_it_template($meta + [MetaFields::FIELD_CAPTCHA => $provider]);
    remove_filter('lcmt_mailer_captcha_providers', $providers);

    return [lcmt_bs_post($key, lcmt_bs_valid()), $key];
}

function lcmt_bs_rows(string $key): array
{
    global $wpdb;

    return $wpdb->get_results($wpdb->prepare('SELECT * FROM ' . SubmissionRepository::table() . ' WHERE form_key = %s', $key), ARRAY_A) ?: [];
}

/**
 * A row created $days days ago.
 */
function lcmt_bs_row(int $days, string $status, array $overrides = []): int
{
    return lcmt_it_insert($overrides + [
        'form_key'   => 'it-bs',
        'created_at' => gmdate('Y-m-d H:i:s', time() - $days * 86400),
        'status'     => $status,
    ]);
}

function lcmt_bs_exists(int $id): bool
{
    return SubmissionRepository::find($id) !== null;
}

function lcmt_bs_reset(): void
{
    delete_transient('lcmt_mailer_blocked_' . gmdate('YmdH'));
    delete_option(Captcha::OPTION_KEEP_BLOCKED);
    delete_option(Captcha::OPTION_UNAVAILABLE);
    delete_option(SubmissionSettings::OPTION_SPAM_DAYS);
}

lcmt_it('keeps a blocked submission as spam with its reason, unsent', function () {
    lcmt_bs_reset();
    [$response, $key] = lcmt_bs_send('it-reject');
    $rows = lcmt_bs_rows($key);

    lcmt_assert_same(403, $response->get_status(), 'status');
    lcmt_assert_same('Security verification failed. Please try again.', $response->get_data()['message']);
    lcmt_assert_count(1, $rows);
    lcmt_assert_same('spam', $rows[0]['status']);
    lcmt_assert_same('captcha_failed', $rows[0]['spam_reason']);
    lcmt_assert_same(0, (int) $rows[0]['mail_sent']);
});

lcmt_it('keeps a message blocked because the service did not answer under its own reason', function () {
    lcmt_bs_reset();
    [$response, $key] = lcmt_bs_send('it-silent');
    $rows = lcmt_bs_rows($key);

    lcmt_assert_same(403, $response->get_status());
    lcmt_assert_count(1, $rows);
    lcmt_assert_same('captcha_unavailable', $rows[0]['spam_reason']);
});

lcmt_it('accepts a message when the service does not answer and the setting says accept', function () {
    lcmt_bs_reset();
    update_option(Captcha::OPTION_UNAVAILABLE, 'accept');
    [$response, $key] = lcmt_bs_send('it-silent');
    $rows = lcmt_bs_rows($key);

    lcmt_assert_same(200, $response->get_status());
    lcmt_assert_count(1, $rows);
    lcmt_assert_same('new', $rows[0]['status']);
    lcmt_assert_same('', $rows[0]['spam_reason']);
});

lcmt_it('keeps nothing from a blocked submission with invalid fields', function () {
    lcmt_bs_reset();
    [$response, $key] = lcmt_bs_send('it-reject');
    $before = count(lcmt_bs_rows($key));
    $invalid = lcmt_bs_post($key, ['firstname' => 'Ann', 'email' => 'not-an-email']);

    lcmt_assert_same(403, $invalid->get_status());
    lcmt_assert_same($before, count(lcmt_bs_rows($key)));
});

lcmt_it('stops keeping blocked submissions past the hourly limit', function () {
    lcmt_bs_reset();
    add_filter('lcmt_mailer_blocked_hourly_limit', $limit = static fn() => 2);

    [, $key] = lcmt_bs_send('it-reject');
    lcmt_bs_post($key, lcmt_bs_valid());
    $last = lcmt_bs_post($key, lcmt_bs_valid());
    remove_filter('lcmt_mailer_blocked_hourly_limit', $limit);

    lcmt_assert_same(403, $last->get_status());
    lcmt_assert_count(2, lcmt_bs_rows($key));
});

lcmt_it('keeps nothing when the setting is off', function () {
    lcmt_bs_reset();
    update_option(Captcha::OPTION_KEEP_BLOCKED, '0');
    [$response, $key] = lcmt_bs_send('it-reject');

    lcmt_assert_same(403, $response->get_status());
    lcmt_assert_count(0, lcmt_bs_rows($key));
});

lcmt_it('keeps nothing when the template does not store submissions', function () {
    lcmt_bs_reset();
    [, $key] = lcmt_bs_send('it-reject', ['_lcmt_mail_store_submissions' => '0']);

    lcmt_assert_count(0, lcmt_bs_rows($key));
});

lcmt_it('a blocked submission raises no failure notice and no unread bubble', function () {
    lcmt_bs_reset();
    update_option(FailureNotice::OPTION_DISMISSED_AT, gmdate('Y-m-d H:i:s', time() - 7200));
    $failures = SubmissionRepository::countUnresolvedFailures(gmdate('Y-m-d H:i:s', time() - 7200));
    $unread   = SubmissionRepository::count(['status' => 'new']);

    lcmt_bs_send('it-reject');
    // A row older than the failure grace period would count if it were a failure.
    global $wpdb;
    $wpdb->query('UPDATE ' . SubmissionRepository::table() . " SET created_at = created_at - INTERVAL 10 MINUTE WHERE spam_reason <> ''");

    lcmt_assert_same($failures, SubmissionRepository::countUnresolvedFailures(gmdate('Y-m-d H:i:s', time() - 7200)), 'failures');
    lcmt_assert_same($unread, SubmissionRepository::count(['status' => 'new']), 'unread');
});

lcmt_it('the purge deletes spam older than 30 days and keeps newer spam and old non-spam', function () {
    lcmt_bs_reset();
    update_option(SubmissionSettings::OPTION_DAYS, 3000);
    $old  = lcmt_bs_row(31, 'spam');
    $new  = lcmt_bs_row(29, 'spam');
    $read = lcmt_bs_row(31, 'read');

    $done = Retention::run();

    lcmt_assert_true(!lcmt_bs_exists($old), 'old spam deleted');
    lcmt_assert_true(lcmt_bs_exists($new), 'recent spam kept');
    lcmt_assert_true(lcmt_bs_exists($read), 'old non-spam kept');
    lcmt_assert_true($done['spam'] >= 1, 'counted');
});

lcmt_it('the purge deletes old spam that is in the trash', function () {
    lcmt_bs_reset();
    update_option(SubmissionSettings::OPTION_DAYS, 3000);
    $id = lcmt_bs_row(31, 'spam', ['trashed_at' => gmdate('Y-m-d H:i:s')]);

    Retention::run();

    lcmt_assert_true(!lcmt_bs_exists($id));
});

lcmt_it('a spam retention of 0 leaves spam to the normal retention', function () {
    lcmt_bs_reset();
    update_option(SubmissionSettings::OPTION_DAYS, 3000);
    update_option(SubmissionSettings::OPTION_SPAM_DAYS, 0);
    $id = lcmt_bs_row(100, 'spam');

    Retention::run();

    lcmt_assert_true(lcmt_bs_exists($id), 'kept');
    lcmt_assert_same(0, SubmissionSettings::spamRetentionDays());
});

lcmt_it('a spam retention of 90 days keeps 60-day-old spam', function () {
    lcmt_bs_reset();
    update_option(SubmissionSettings::OPTION_DAYS, 3000);
    update_option(SubmissionSettings::OPTION_SPAM_DAYS, 90);
    $kept = lcmt_bs_row(60, 'spam');
    $gone = lcmt_bs_row(91, 'spam');

    lcmt_assert_same(90, SubmissionSettings::spamRetentionDays());
    lcmt_assert_same('90', (string) sanitize_option(SubmissionSettings::OPTION_SPAM_DAYS, '90'));
    Retention::run();

    lcmt_assert_true(lcmt_bs_exists($kept), '60-day-old spam kept');
    lcmt_assert_true(!lcmt_bs_exists($gone), '91-day-old spam deleted');
});
