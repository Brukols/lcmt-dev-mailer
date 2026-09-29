<?php

/**
 * A tiny assertion runner for the tests that need a real WordPress database.
 *
 * Every test runs inside a transaction that is always rolled back, so nothing
 * it writes survives. Never run DDL (CREATE/ALTER/DROP) in a test: MySQL
 * commits implicitly on it and the rollback would no longer protect the data.
 */

use LcmtDevMailer\SubmissionRepository;

$GLOBALS['lcmt_it_results'] = [];

function lcmt_it(string $name, callable $test): void
{
    global $wpdb;

    $wpdb->query('START TRANSACTION');

    try {
        $test();
        $GLOBALS['lcmt_it_results'][] = [$name, null];
    } catch (Throwable $e) {
        $GLOBALS['lcmt_it_results'][] = [$name, $e->getMessage()];
    }

    $wpdb->query('ROLLBACK');
    wp_cache_flush();
}

function lcmt_it_fail(string $message, $expected, $actual): void
{
    $message = ($message !== '' ? $message . ': ' : '')
        . 'expected ' . var_export($expected, true) . ', got ' . var_export($actual, true);

    throw new RuntimeException($message);
}

function lcmt_assert_same($expected, $actual, string $message = ''): void
{
    if ($expected !== $actual) {
        lcmt_it_fail($message, $expected, $actual);
    }
}

function lcmt_assert_true($value, string $message = ''): void
{
    if ($value !== true) {
        lcmt_it_fail($message, true, $value);
    }
}

function lcmt_assert_null($value, string $message = ''): void
{
    if ($value !== null) {
        lcmt_it_fail($message, null, $value);
    }
}

function lcmt_assert_count(int $expected, $array, string $message = ''): void
{
    $actual = is_countable($array) ? count($array) : $array;

    if ($expected !== $actual) {
        lcmt_it_fail($message !== '' ? $message : 'count', $expected, $actual);
    }
}

/**
 * Insert a valid submission row and return its id.
 *
 * @param array<string, mixed> $overrides Column values replacing the defaults.
 */
function lcmt_it_insert(array $overrides = []): int
{
    $id = SubmissionRepository::insert($overrides + [
        'form_key'   => 'it-form',
        'created_at' => current_time('mysql', true),
        'status'     => 'new',
        'mail_sent'  => 1,
        'fields'     => wp_json_encode(['email' => 'a@example.com'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'channel'    => 'direct',
    ]);

    if ($id === 0) {
        throw new RuntimeException('lcmt_it_insert() failed to insert a row');
    }

    return $id;
}

function lcmt_it_report(): void
{
    $failed = 0;

    foreach ($GLOBALS['lcmt_it_results'] as [$name, $error]) {
        if ($error === null) {
            WP_CLI::line("  ok    {$name}");
        } else {
            $failed++;
            WP_CLI::line("  FAIL  {$name}\n        {$error}");
        }
    }

    if ($failed > 0) {
        WP_CLI::error("{$failed} failed");
    }

    WP_CLI::success(count($GLOBALS['lcmt_it_results']) . ' passed');
}
