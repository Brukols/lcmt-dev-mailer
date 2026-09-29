# Received Messages Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Save every form submission in the database (like Flamingo), with where it came from (page, landing page, channel, campaign, device), show them in the admin with stats, flag emails that failed, and delete or anonymize personal data after a configurable retention period (GDPR).

**Architecture:** One custom table `{prefix}lcmt_mailer_submissions` holds one row per submission: personal data (`fields` JSON, `mail_error`) next to context columns that stay after anonymization. Pure classes (no WordPress calls, unit-tested) do the data shaping: `SubmissionData`, `SubmissionContext`, `ChannelClassifier`, `Retention::cutoff`, `SubmissionCsv`. WordPress-bound classes (`SubmissionRepository`, `SubmissionRecorder`, admin pages, cron, privacy hooks) call them. A small front-end script remembers the landing page and campaign parameters in `sessionStorage`, following WP Consent API when it is installed.

**Tech Stack:** PHP 8.1, WordPress 6.0+ (`$wpdb`, `dbDelta`, `WP_List_Table`, WP-Cron, privacy exporters/erasers), vanilla JS bundled by esbuild, PHPUnit 11 without WordPress.

**Spec:** the decisions agreed with Amaury on 2026-09-29, recorded in the next section (no separate spec file).

## Decisions

- Storage in a custom table, not a post type: stats need `GROUP BY`, anonymization is `SET fields = NULL`.
- A row is written **before** the email is sent, `mail_sent` is updated after, so a failed email never loses the message. The reason comes from the `wp_mail_failed` hook.
- Per-form checkbox "Save the messages sent through this form", on by default.
- `password` fields are never stored.
- The visitor's IP is never stored. **No country** (dropped).
- Retention defaults at the legal maximum: personal data **1095 days** (3 years, CNIL ceiling for prospects), then **anonymize** (setting: anonymize or delete). Anonymized rows kept **forever** by default (setting: number of days, 0 = unlimited).
- Attribution memory (`sessionStorage`): when WP Consent API is present, only with `statistics` consent; when **no consent tool is detected, store anyway** — an advanced setting can turn that off.
- Failed emails: **no notification email**. A red admin banner (with the error) and a dashboard widget, until the failures are resent, marked processed/spam, or the banner is dismissed.
- Extras kept: CSV export, time spent on the form, resend a failed email.

## Global Constraints

- Code, comments, docblocks, docs and commit messages in **English**. Translatable strings in English in the code, French in `languages/lcmt-dev-mailer-fr_FR.po`.
- Commit prefix emoji as in the history (`✨` feature, `🐛` fix, `🔒` security, `📝` docs, `🌐` translations, `🔖` release), English imperative title, ending with `Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>`.
- Namespace `LcmtDevMailer`, PascalCase classes, kebab-case file names in `includes/`.
- Slug stays `lcmt-dev-mailer`; option, hook and REST prefixes stay `lcmt_mailer_` / `lcmt-mailer`.
- No jQuery in new JS, no ACF, no new Composer/npm dependency.
- Rebuild JS with `nvm use 20 && yarn build` after editing `assets/src/`, commit `assets/dist/`.
- Admin screens require `SubmissionsPage::capability()` (`manage_options` by default).
- Dates stored in UTC (`current_time('mysql', true)`), displayed with `get_date_from_gmt()`.
- Work on branch `feature/received-messages` in `/Volumes/Samsung_T5/Freelance/lcmt-dev-mailer`.
- Manual tests run on the local Live Decor site: sync the plugin with
  `rsync -a --delete --exclude .git --exclude node_modules --exclude docs --exclude tests --exclude .phpunit.cache /Volumes/Samsung_T5/Freelance/lcmt-dev-mailer/ /Volumes/Samsung_T5/Freelance/live-decor-production/wp-content/plugins/lcmt-dev-mailer/`
  (the site copy is not a git checkout; never commit from it).

## Review Focus

1. **A forged or oversized `_context`** (arrays instead of strings, 10 kB strings, `javascript:` or `//evil.com` paths, bad UTF-8) must be cleaned to empty values, never stored raw and never cause a PHP error — test in Task 2.
2. **A referrer from the site itself** (`www.` vs bare host, `http` vs `https`) must count as internal (no referrer host, not "referral") — test in Task 2.
3. **Password fields** must never reach the database, the CSV, the privacy export or a resend — test in Task 1.
4. **CSV cells starting with `=`, `+`, `-`, `@`** must not run as spreadsheet formulas — test in Task 8.
5. **Retention input of 0, negative or non-numeric** must not purge everything at the next cron run — test in Task 9.

---

## File Structure

| File | Status | Responsibility |
|---|---|---|
| `includes/submission-data.php` | Create | Pure: field snapshot, placeholders from a snapshot, email match, sender summary, personal columns list |
| `includes/submission-context.php` | Create | Pure: clean the browser `_context` into stored columns |
| `includes/channel-classifier.php` | Create | Pure: context → channel id, channel labels |
| `includes/submission-schema.php` | Create | Table creation/upgrade with `dbDelta` |
| `includes/submission-repository.php` | Create | All SQL on the submissions table |
| `includes/submission-recorder.php` | Create | Write a row from the endpoint, send and track the email result |
| `includes/attribution.php` | Create | Enqueue the attribution script with its config |
| `assets/src/lib/attribution.js` | Create | Shared JS: current touch, storage, consent, device, form context |
| `assets/src/attribution.js` | Create | Entry loaded on every page: remember the landing |
| `assets/src/form-handler.js` | Modify | Send `_context` with the form |
| `includes/submissions-page.php` | Create | Admin: list/detail screens, actions, CSV export, capability |
| `includes/submissions-list-table.php` | Create | `WP_List_Table` for the list screen (loaded only on that screen) |
| `includes/submission-csv.php` | Create | Pure: rows → CSV table, formula neutralising |
| `includes/submission-settings.php` | Create | Admin: retention + advanced attribution settings page, purge now |
| `includes/retention.php` | Create | Pure cutoff/clamp helpers + cron run |
| `includes/privacy.php` | Create | Personal data exporter/eraser, privacy policy text, `[lcmt-retention-days]` |
| `includes/failure-notice.php` | Create | Red admin banner, dashboard widget, dismiss |
| `includes/stats-page.php` | Create | Admin: statistics screen |
| `includes/meta-fields.php` | Modify | Per-form "save messages" checkbox |
| `includes/form-endpoint.php` | Modify | Record, then send through the recorder |
| `lcmt-dev-mailer.php` | Modify | Requires, hooks, deactivation, version |
| `uninstall.php` | Create | Drop the table and the new options |
| `tests/bootstrap.php` | Modify | Require the new pure classes |
| `tests/SubmissionDataTest.php`, `tests/SubmissionContextTest.php`, `tests/ChannelClassifierTest.php`, `tests/SubmissionCsvTest.php`, `tests/RetentionTest.php` | Create | Unit tests |
| `.gitattributes` | Modify | `docs` export-ignore |
| `.claude/*.md`, `README.md`, `languages/*` | Modify | Docs and French translations |

---

### Task 0: Branch

- [ ] **Step 1: Create the branch and keep plans out of the release zip**

```bash
cd /Volumes/Samsung_T5/Freelance/lcmt-dev-mailer
git checkout main && git pull
git checkout -b feature/received-messages
```

Add to `.gitattributes`, after the `/.claude` line:

```
/docs             export-ignore
```

- [ ] **Step 2: Commit**

```bash
git add .gitattributes docs/superpowers/plans/2026-09-29-received-messages.md
git commit -m "📝 Plan the received messages feature

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 1: SubmissionData (pure)

**Files:**
- Create: `includes/submission-data.php`
- Modify: `tests/bootstrap.php`
- Test: `tests/SubmissionDataTest.php`

**Interfaces:**
- Consumes: `FieldParser::parse()` output shape `array<string, array{name: string, required: bool, type: string}>`.
- Produces:
  - `SubmissionData::PERSONAL_COLUMNS` = `['fields', 'mail_error']`
  - `SubmissionData::snapshot(array $fields, array $values): array` → `list<array{name: string, type: string, value: string}>`
  - `SubmissionData::toPlaceholders(array $snapshot): array<string, string>`
  - `SubmissionData::containsEmail(array $snapshot, string $email): bool`
  - `SubmissionData::summary(array $snapshot): string`

- [ ] **Step 1: Write the failing test**

`tests/SubmissionDataTest.php`:

```php
<?php

use LcmtDevMailer\SubmissionData;
use PHPUnit\Framework\TestCase;

class SubmissionDataTest extends TestCase
{
    private const FIELDS = [
        'firstname' => ['name' => 'firstname', 'required' => true, 'type' => 'text'],
        'email'     => ['name' => 'email', 'required' => true, 'type' => 'email'],
        'secret'    => ['name' => 'secret', 'required' => false, 'type' => 'password'],
        'message'   => ['name' => 'message', 'required' => false, 'type' => 'textarea'],
    ];

    private const VALUES = [
        'firstname' => 'Jeanne',
        'email'     => 'Jeanne@Example.com',
        'secret'    => 'hunter2',
        'message'   => "Line 1\nLine 2",
    ];

    public function testSnapshotKeepsEveryFieldButPasswords(): void
    {
        $this->assertSame([
            ['name' => 'firstname', 'type' => 'text', 'value' => 'Jeanne'],
            ['name' => 'email', 'type' => 'email', 'value' => 'Jeanne@Example.com'],
            ['name' => 'message', 'type' => 'textarea', 'value' => "Line 1\nLine 2"],
        ], SubmissionData::snapshot(self::FIELDS, self::VALUES));
    }

    public function testSnapshotStoresMissingValuesAsEmpty(): void
    {
        $snapshot = SubmissionData::snapshot(self::FIELDS, []);

        $this->assertSame('', $snapshot[0]['value']);
    }

    public function testPlaceholdersNeverCarryAPassword(): void
    {
        $placeholders = SubmissionData::toPlaceholders(SubmissionData::snapshot(self::FIELDS, self::VALUES));

        $this->assertSame('Jeanne', $placeholders['[firstname]']);
        $this->assertSame('Jeanne', $placeholders['[firstname*]']);
        $this->assertArrayNotHasKey('[secret]', $placeholders);
        $this->assertNotContains('hunter2', $placeholders);
    }

    public function testMatchesAnEmailWhateverItsCase(): void
    {
        $snapshot = SubmissionData::snapshot(self::FIELDS, self::VALUES);

        $this->assertTrue(SubmissionData::containsEmail($snapshot, ' jeanne@example.COM '));
        $this->assertFalse(SubmissionData::containsEmail($snapshot, 'jeanne@example.org'));
        $this->assertFalse(SubmissionData::containsEmail($snapshot, ''));
    }

    public function testSummaryNamesTheSender(): void
    {
        $snapshot = SubmissionData::snapshot(self::FIELDS, self::VALUES);

        $this->assertSame('Jeanne · Jeanne@Example.com', SubmissionData::summary($snapshot));
        $this->assertSame('', SubmissionData::summary([]));
    }

    public function testPersonalColumnsAreTheOnesAnonymizationClears(): void
    {
        $this->assertSame(['fields', 'mail_error'], SubmissionData::PERSONAL_COLUMNS);
    }
}
```

Add to `tests/bootstrap.php`, after the `field-validator.php` require:

```php
require_once dirname(__DIR__) . '/includes/submission-data.php';
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `cd /Volumes/Samsung_T5/Freelance/lcmt-dev-mailer && phpunit --filter SubmissionDataTest`
Expected: FAIL — `Failed opening required '.../includes/submission-data.php'`.

- [ ] **Step 3: Write the implementation**

`includes/submission-data.php`:

```php
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
```

- [ ] **Step 4: Run the tests to verify they pass**

Run: `phpunit`
Expected: all tests PASS (existing ones included).

- [ ] **Step 5: Commit**

```bash
git add includes/submission-data.php tests/SubmissionDataTest.php tests/bootstrap.php
git commit -m "✨ Shape form values for storage without their passwords

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 2: SubmissionContext (pure)

**Files:**
- Create: `includes/submission-context.php`
- Modify: `tests/bootstrap.php`
- Test: `tests/SubmissionContextTest.php`

**Interfaces:**
- Consumes: the `_context` JSON object built by `formContext()` in Task 6:
  `{page: string, landing: {path, referrer, utm_source?, utm_medium?, utm_campaign?, click_id?}, device: string, locale: string, seconds: int|null}`.
- Produces:
  - `SubmissionContext::CLICK_IDS` = `['gclid', 'gbraid', 'wbraid', 'msclkid', 'fbclid', 'ttclid', 'li_fat_id']`
  - `SubmissionContext::fromRequest(mixed $raw, string $siteHost): array` with exactly these keys:
    `page_path, landing_path, referrer_host, utm_source, utm_medium, utm_campaign, click_id_type, device, locale` (strings) and `form_seconds` (`?int`).

- [ ] **Step 1: Write the failing test**

`tests/SubmissionContextTest.php`:

```php
<?php

use LcmtDevMailer\SubmissionContext;
use PHPUnit\Framework\TestCase;

class SubmissionContextTest extends TestCase
{
    private const SITE = 'www.live-decor.fr';

    private function context(array $raw): array
    {
        return SubmissionContext::fromRequest($raw, self::SITE);
    }

    public function testKeepsAWellFormedContext(): void
    {
        $this->assertSame([
            'page_path'     => '/contact/',
            'landing_path'  => '/location-decor/',
            'referrer_host' => 'google.fr',
            'utm_source'    => 'google',
            'utm_medium'    => 'cpc',
            'utm_campaign'  => 'Mariage été 2026',
            'click_id_type' => 'gclid',
            'device'        => 'mobile',
            'locale'        => 'fr-FR',
            'form_seconds'  => 42,
        ], $this->context([
            'page'    => '/contact/?email=jeanne@example.com#top',
            'landing' => [
                'path'         => '/location-decor/',
                'referrer'     => 'https://www.google.fr/',
                'utm_source'   => 'Google',
                'utm_medium'   => 'CPC',
                'utm_campaign' => 'Mariage été 2026',
                'click_id'     => 'gclid',
            ],
            'device'  => 'mobile',
            'locale'  => 'fr-FR',
            'seconds' => 42,
        ]));
    }

    public function testReturnsEmptyValuesForAMissingContext(): void
    {
        foreach ([null, 'junk', 42, []] as $raw) {
            $context = SubmissionContext::fromRequest($raw, self::SITE);

            $this->assertSame('', $context['page_path']);
            $this->assertSame('', $context['referrer_host']);
            $this->assertNull($context['form_seconds']);
            $this->assertCount(10, $context);
        }
    }

    public function testRejectsPathsThatAreNotSitePaths(): void
    {
        foreach (['javascript:alert(1)', '//evil.com/x', 'https://evil.com/', 'contact', '/a b', ['/x']] as $page) {
            $this->assertSame('', $this->context(['page' => $page])['page_path'], var_export($page, true));
        }
    }

    public function testCutsOversizedValues(): void
    {
        $context = $this->context([
            'page'    => '/' . str_repeat('a', 5000),
            'landing' => ['utm_campaign' => str_repeat('é', 5000)],
        ]);

        $this->assertSame(255, strlen($context['page_path']));
        $this->assertSame(150, mb_strlen($context['utm_campaign']));
    }

    public function testDropsTagsQuotesAndBrokenUtf8FromCampaigns(): void
    {
        $context = $this->context(['landing' => [
            'utm_source'   => '<script>"x"</script>',
            'utm_campaign' => "\xC3\x28",
        ]]);

        $this->assertSame('scriptx/script', $context['utm_source']);
        $this->assertSame('', $context['utm_campaign']);
    }

    public function testTreatsTheSiteItselfAsNoReferrer(): void
    {
        foreach (['https://www.live-decor.fr/a', 'http://live-decor.fr/', 'https://LIVE-DECOR.fr'] as $referrer) {
            $this->assertSame('', $this->context(['landing' => ['referrer' => $referrer]])['referrer_host'], $referrer);
        }
    }

    public function testKeepsOnlyTheHostOfAnExternalReferrer(): void
    {
        $context = $this->context(['landing' => ['referrer' => 'https://m.facebook.com/story.php?id=123']]);

        $this->assertSame('m.facebook.com', $context['referrer_host']);
    }

    public function testIgnoresReferrersThatAreNotWebPages(): void
    {
        foreach (['android-app://com.google.android.gm', 'not a url', 'ftp://files.example.com'] as $referrer) {
            $this->assertSame('', $this->context(['landing' => ['referrer' => $referrer]])['referrer_host'], $referrer);
        }
    }

    public function testAcceptsOnlyKnownClickIdsDevicesAndLocales(): void
    {
        $context = $this->context([
            'landing' => ['click_id' => 'evil'],
            'device'  => 'fridge',
            'locale'  => 'fr_FR"><',
        ]);

        $this->assertSame('', $context['click_id_type']);
        $this->assertSame('', $context['device']);
        $this->assertSame('', $context['locale']);
    }

    public function testKeepsFormTimeWithinADay(): void
    {
        $this->assertSame(0, $this->context(['seconds' => '0'])['form_seconds']);
        $this->assertNull($this->context(['seconds' => -5])['form_seconds']);
        $this->assertNull($this->context(['seconds' => 999999])['form_seconds']);
        $this->assertNull($this->context(['seconds' => 'soon'])['form_seconds']);
    }
}
```

Add to `tests/bootstrap.php`:

```php
require_once dirname(__DIR__) . '/includes/submission-context.php';
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `phpunit --filter SubmissionContextTest`
Expected: FAIL — `Failed opening required '.../includes/submission-context.php'`.

- [ ] **Step 3: Write the implementation**

`includes/submission-context.php`:

```php
<?php

namespace LcmtDevMailer;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Cleans the context the browser sends with a form into stored columns.
 *
 * Nothing in it is trusted: each value is checked against the shape it must
 * have and cut to its column size, anything else becomes empty. Only paths
 * and hosts are kept, never query strings, which can carry personal data.
 */
class SubmissionContext
{
    public const CLICK_IDS = ['gclid', 'gbraid', 'wbraid', 'msclkid', 'fbclid', 'ttclid', 'li_fat_id'];

    private const DEVICES = ['mobile', 'tablet', 'desktop'];

    private const MAX_SECONDS = 86400;

    /**
     * @param mixed  $raw      The `_context` value from the JSON body.
     * @param string $siteHost The host of home_url(), to spot internal referrers.
     * @return array{page_path: string, landing_path: string, referrer_host: string, utm_source: string, utm_medium: string, utm_campaign: string, click_id_type: string, device: string, locale: string, form_seconds: ?int}
     */
    public static function fromRequest($raw, string $siteHost): array
    {
        $raw     = is_array($raw) ? $raw : [];
        $landing = isset($raw['landing']) && is_array($raw['landing']) ? $raw['landing'] : [];

        return [
            'page_path'     => self::path($raw['page'] ?? ''),
            'landing_path'  => self::path($landing['path'] ?? ''),
            'referrer_host' => self::externalHost($landing['referrer'] ?? '', $siteHost),
            'utm_source'    => strtolower(self::token($landing['utm_source'] ?? '', 100)),
            'utm_medium'    => strtolower(self::token($landing['utm_medium'] ?? '', 100)),
            'utm_campaign'  => self::token($landing['utm_campaign'] ?? '', 150),
            'click_id_type' => self::oneOf($landing['click_id'] ?? '', self::CLICK_IDS),
            'device'        => self::oneOf($raw['device'] ?? '', self::DEVICES),
            'locale'        => self::locale($raw['locale'] ?? ''),
            'form_seconds'  => self::seconds($raw['seconds'] ?? null),
        ];
    }

    /**
     * A path on this site, without its query string or fragment.
     *
     * @param mixed $value
     */
    private static function path($value): string
    {
        if (!is_string($value) || !str_starts_with($value, '/') || str_starts_with($value, '//')) {
            return '';
        }

        $path = (string) parse_url($value, PHP_URL_PATH);

        if (!preg_match('#^/[A-Za-z0-9\-._~/%]*$#', $path)) {
            return '';
        }

        return substr($path, 0, 255);
    }

    /**
     * The host of a web page on another site, without "www.", or empty.
     *
     * @param mixed $url
     */
    private static function externalHost($url, string $siteHost): string
    {
        if (!is_string($url) || $url === '') {
            return '';
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        $host   = strtolower((string) parse_url($url, PHP_URL_HOST));

        if (!in_array($scheme, ['http', 'https'], true) || !preg_match('/^[a-z0-9.-]+$/', $host)) {
            return '';
        }

        $host = preg_replace('/^www\./', '', $host);
        $site = preg_replace('/^www\./', '', strtolower($siteHost));

        return $host === $site ? '' : substr($host, 0, 191);
    }

    /**
     * Free text from a campaign parameter, without control characters,
     * tags delimiters or quotes.
     *
     * @param mixed $value
     */
    private static function token($value, int $max): string
    {
        if (!is_string($value)) {
            return '';
        }

        $clean = preg_replace('/[\x00-\x1F\x7F<>"\']/u', '', $value);

        // preg_replace returns null on invalid UTF-8.
        if ($clean === null) {
            return '';
        }

        return mb_substr(trim($clean), 0, $max);
    }

    /**
     * @param mixed         $value
     * @param list<string>  $allowed
     */
    private static function oneOf($value, array $allowed): string
    {
        return is_string($value) && in_array($value, $allowed, true) ? $value : '';
    }

    /**
     * @param mixed $value
     */
    private static function locale($value): string
    {
        return is_string($value) && preg_match('/^[a-z]{2,3}(-[A-Za-z]{2})?$/', $value) ? $value : '';
    }

    /**
     * @param mixed $value
     */
    private static function seconds($value): ?int
    {
        if (!is_numeric($value)) {
            return null;
        }

        $seconds = (int) $value;

        return $seconds >= 0 && $seconds <= self::MAX_SECONDS ? $seconds : null;
    }
}
```

- [ ] **Step 4: Run the tests to verify they pass**

Run: `phpunit`
Expected: all PASS.

- [ ] **Step 5: Commit**

```bash
git add includes/submission-context.php tests/SubmissionContextTest.php tests/bootstrap.php
git commit -m "✨ Clean the page and campaign context sent with a form

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 3: ChannelClassifier (pure)

**Files:**
- Create: `includes/channel-classifier.php`
- Modify: `tests/bootstrap.php`
- Test: `tests/ChannelClassifierTest.php`

**Interfaces:**
- Consumes: the array returned by `SubmissionContext::fromRequest()` (keys `click_id_type`, `utm_source`, `utm_medium`, `referrer_host`).
- Produces:
  - `ChannelClassifier::CHANNELS` = `['google_ads', 'paid_social', 'paid_other', 'email', 'social', 'organic_search', 'campaign', 'referral', 'direct']`
  - `ChannelClassifier::classify(array $context): string` (one of `CHANNELS`)
  - `ChannelClassifier::label(string $channel): string` (translated)

- [ ] **Step 1: Write the failing test**

`tests/ChannelClassifierTest.php`:

```php
<?php

use LcmtDevMailer\ChannelClassifier;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ChannelClassifierTest extends TestCase
{
    public static function cases(): array
    {
        return [
            'gclid'                     => ['google_ads', ['click_id_type' => 'gclid']],
            'gbraid'                    => ['google_ads', ['click_id_type' => 'gbraid']],
            'google cpc without gclid'  => ['google_ads', ['utm_source' => 'google', 'utm_medium' => 'cpc']],
            'meta ads'                  => ['paid_social', ['utm_source' => 'fb', 'utm_medium' => 'paid_social']],
            'tiktok click'              => ['paid_social', ['click_id_type' => 'ttclid']],
            'bing ads'                  => ['paid_other', ['click_id_type' => 'msclkid']],
            'other paid medium'         => ['paid_other', ['utm_source' => 'partner', 'utm_medium' => 'display']],
            'newsletter'                => ['email', ['utm_source' => 'brevo', 'utm_medium' => 'email']],
            'fbclid on organic share'   => ['social', ['click_id_type' => 'fbclid', 'referrer_host' => 'm.facebook.com']],
            'instagram referrer'        => ['social', ['referrer_host' => 'l.instagram.com']],
            'linkedin short link'       => ['social', ['referrer_host' => 'lnkd.in']],
            'google search'             => ['organic_search', ['referrer_host' => 'google.fr']],
            'bing search'               => ['organic_search', ['referrer_host' => 'bing.com']],
            'qwant'                     => ['organic_search', ['referrer_host' => 'qwant.com']],
            'brave search'              => ['organic_search', ['referrer_host' => 'search.brave.com']],
            'unknown utm source'        => ['campaign', ['utm_source' => 'flyer-salon-2026']],
            'other site'                => ['referral', ['referrer_host' => 'mariages.net']],
            'nothing'                   => ['direct', []],
            'lookalike host'            => ['referral', ['referrer_host' => 'notgoogle.com']],
        ];
    }

    #[DataProvider('cases')]
    public function testClassifies(string $expected, array $context): void
    {
        $this->assertSame($expected, ChannelClassifier::classify($context));
    }

    public function testLabelsEveryChannel(): void
    {
        foreach (ChannelClassifier::CHANNELS as $channel) {
            $this->assertNotSame($channel, ChannelClassifier::label($channel), $channel);
        }

        $this->assertSame('unknown', ChannelClassifier::label('unknown'));
    }
}
```

Add to `tests/bootstrap.php`:

```php
require_once dirname(__DIR__) . '/includes/channel-classifier.php';
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `phpunit --filter ChannelClassifierTest`
Expected: FAIL — missing file.

- [ ] **Step 3: Write the implementation**

`includes/channel-classifier.php`:

```php
<?php

namespace LcmtDevMailer;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Sorts a submission into the channel that brought the visitor.
 *
 * Paid signals win over everything (an ad click that went through a search
 * page is still an ad), then explicit campaign mediums, then the referrer.
 */
class ChannelClassifier
{
    public const CHANNELS = [
        'google_ads', 'paid_social', 'paid_other', 'email',
        'social', 'organic_search', 'campaign', 'referral', 'direct',
    ];

    private const SEARCH_ENGINES = [
        'google', 'bing', 'yahoo', 'duckduckgo', 'qwant', 'ecosia',
        'yandex', 'baidu', 'startpage', 'lilo', 'search.brave.com',
    ];

    private const SOCIAL_NETWORKS = [
        'facebook', 'fb', 'instagram', 'ig', 'linkedin', 'lnkd.in', 'twitter',
        't.co', 'x.com', 'pinterest', 'youtube', 'tiktok', 'threads.net',
        'reddit', 'snapchat',
    ];

    /**
     * @param array<string, mixed> $context SubmissionContext::fromRequest() output.
     */
    public static function classify(array $context): string
    {
        $click  = (string) ($context['click_id_type'] ?? '');
        $source = strtolower((string) ($context['utm_source'] ?? ''));
        $medium = strtolower((string) ($context['utm_medium'] ?? ''));
        $host   = strtolower((string) ($context['referrer_host'] ?? ''));

        $paid = (bool) preg_match('/^(cpc|ppc|cpm|display|banner|paid.*)$/', $medium);

        if (in_array($click, ['gclid', 'gbraid', 'wbraid'], true) || ($paid && $source === 'google')) {
            return 'google_ads';
        }

        if (in_array($click, ['ttclid', 'li_fat_id'], true) || ($paid && self::matches($source, self::SOCIAL_NETWORKS))) {
            return 'paid_social';
        }

        if ($click === 'msclkid' || $paid) {
            return 'paid_other';
        }

        if (in_array($medium, ['email', 'e-mail', 'newsletter'], true)) {
            return 'email';
        }

        // fbclid is added to every outgoing Facebook link, ads or not.
        if ($medium === 'social' || $click === 'fbclid'
            || self::matches($source, self::SOCIAL_NETWORKS) || self::matches($host, self::SOCIAL_NETWORKS)) {
            return 'social';
        }

        if ($medium === 'organic' || self::matches($host, self::SEARCH_ENGINES)) {
            return 'organic_search';
        }

        if ($source !== '') {
            return 'campaign';
        }

        return $host !== '' ? 'referral' : 'direct';
    }

    public static function label(string $channel): string
    {
        return match ($channel) {
            'google_ads'     => __('Google Ads', 'lcmt-dev-mailer'),
            'paid_social'    => __('Paid social', 'lcmt-dev-mailer'),
            'paid_other'     => __('Other ads', 'lcmt-dev-mailer'),
            'email'          => __('Email', 'lcmt-dev-mailer'),
            'social'         => __('Social networks', 'lcmt-dev-mailer'),
            'organic_search' => __('Organic search', 'lcmt-dev-mailer'),
            'campaign'       => __('Other campaign', 'lcmt-dev-mailer'),
            'referral'       => __('Other website', 'lcmt-dev-mailer'),
            'direct'         => __('Direct', 'lcmt-dev-mailer'),
            default          => $channel,
        };
    }

    /**
     * Whether a source or host is one of the names: a plain name matches a
     * whole domain label ("google" in "www.google.fr", not in "notgoogle.com"),
     * a dotted name matches the host or its subdomains.
     *
     * @param list<string> $names
     */
    private static function matches(string $value, array $names): bool
    {
        if ($value === '') {
            return false;
        }

        foreach ($names as $name) {
            if ($value === $name) {
                return true;
            }

            $matched = str_contains($name, '.')
                ? str_ends_with($value, '.' . $name)
                : (bool) preg_match('/(^|\.)' . preg_quote($name, '/') . '\./', $value);

            if ($matched) {
                return true;
            }
        }

        return false;
    }
}
```

- [ ] **Step 4: Run the tests to verify they pass**

Run: `phpunit`
Expected: all PASS.

- [ ] **Step 5: Commit**

```bash
git add includes/channel-classifier.php tests/ChannelClassifierTest.php tests/bootstrap.php
git commit -m "✨ Sort submissions into the channel that brought the visitor

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 4: Table, repository, uninstall

**Files:**
- Create: `includes/submission-schema.php`, `includes/submission-repository.php`, `uninstall.php`
- Modify: `lcmt-dev-mailer.php`

**Interfaces:**
- Consumes: `SubmissionData::PERSONAL_COLUMNS`, `ChannelClassifier::CHANNELS`.
- Produces (all static on `SubmissionRepository`, rows are associative arrays with `fields` decoded to `?array`):
  - `STATUSES` = `['new', 'read', 'processed', 'spam']`
  - `table(): string`
  - `insert(array $row): int` (0 on failure)
  - `update(int $id, array $data): bool`
  - `find(int $id): ?array`
  - `search(array $filters, int $perPage = 0, int $page = 1): array` — `$perPage = 0` returns every row
  - `count(array $filters): int`
  - filters: `form_key` (string), `status` (one of `STATUSES`, or `''` = every status but spam), `failed` (bool), `channel` (string), `search` (string)
  - `setStatus(array $ids, string $status): void`, `delete(array $ids): void`, `anonymize(array $ids): void`
  - `anonymizeBefore(string $cutoff, int $limit): int`, `deleteBefore(string $cutoff, int $limit): int`, `deleteAnonymizedBefore(string $cutoff, int $limit): int`
  - `findContaining(string $needle): array` (not anonymized, no limit)
  - `countUnread(): int`
  - `unresolvedFailures(string $since, int $limit): array`, `countUnresolvedFailures(string $since): int`
  - `formKeys(): list<string>`
  - `countBy(string $column, string $since, int $limit = 10): list<array{label: string, total: int}>`
  - `countByMonth(string $since): list<array{label: string, total: int}>`
  - `totals(string $since): array{total: int, failed: int, avg_seconds: ?int}`
  - `SubmissionSchema::maybeUpgrade(): void`

- [ ] **Step 1: Write the schema**

`includes/submission-schema.php`:

```php
<?php

namespace LcmtDevMailer;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Creates and upgrades the submissions table.
 *
 * Sites update through the GitHub releases, which never run the activation
 * hook, so the stored schema version is checked on every load instead.
 */
class SubmissionSchema
{
    public const VERSION = '1';
    public const OPTION_VERSION = 'lcmt_mailer_db_version';

    public static function maybeUpgrade(): void
    {
        if (get_option(self::OPTION_VERSION) === self::VERSION) {
            return;
        }

        self::install();
    }

    public static function install(): void
    {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $table   = SubmissionRepository::table();
        $charset = $wpdb->get_charset_collate();

        // dbDelta needs two spaces after PRIMARY KEY and one field per line.
        dbDelta("CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            form_key varchar(100) NOT NULL DEFAULT '',
            mail_post_id bigint(20) unsigned NOT NULL DEFAULT 0,
            created_at datetime NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'new',
            mail_sent tinyint(1) NOT NULL DEFAULT 0,
            mail_error text NULL,
            fields longtext NULL,
            page_path varchar(255) NOT NULL DEFAULT '',
            page_id bigint(20) unsigned NOT NULL DEFAULT 0,
            landing_path varchar(255) NOT NULL DEFAULT '',
            referrer_host varchar(191) NOT NULL DEFAULT '',
            channel varchar(30) NOT NULL DEFAULT '',
            utm_source varchar(100) NOT NULL DEFAULT '',
            utm_medium varchar(100) NOT NULL DEFAULT '',
            utm_campaign varchar(150) NOT NULL DEFAULT '',
            click_id_type varchar(20) NOT NULL DEFAULT '',
            device varchar(10) NOT NULL DEFAULT '',
            locale varchar(10) NOT NULL DEFAULT '',
            form_seconds int(10) unsigned NULL,
            anonymized_at datetime NULL,
            PRIMARY KEY  (id),
            KEY created_at (created_at),
            KEY form_key (form_key),
            KEY status (status),
            KEY anonymized_at (anonymized_at)
        ) {$charset};");

        update_option(self::OPTION_VERSION, self::VERSION);
    }
}
```

- [ ] **Step 2: Write the repository**

`includes/submission-repository.php`:

```php
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
    private const GROUPABLE = ['form_key', 'channel', 'utm_campaign', 'page_path', 'landing_path', 'referrer_host', 'device'];

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
                'UPDATE ' . self::table() . ' SET ' . self::clearPersonal() . ', anonymized_at = %s WHERE id IN (' . self::idList($ids) . ')',
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
            'UPDATE ' . self::table() . ' SET ' . self::clearPersonal() . ', anonymized_at = %s
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
            $limit
        ), ARRAY_A) ?: [];
    }

    public static function countUnresolvedFailures(string $since): int
    {
        global $wpdb;

        return (int) $wpdb->get_var($wpdb->prepare(
            'SELECT COUNT(*) FROM ' . self::table() . ' WHERE ' . self::unresolvedFailure(),
            $since
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

    private static function clearPersonal(): string
    {
        return implode(', ', array_map(static fn(string $column) => "{$column} = NULL", SubmissionData::PERSONAL_COLUMNS));
    }

    /**
     * Takes one %s: the date the admin last dismissed the failure banner.
     */
    private static function unresolvedFailure(): string
    {
        return "mail_sent = 0 AND anonymized_at IS NULL AND status NOT IN ('processed', 'spam') AND created_at > %s";
    }

    private static function hydrate(array $row): array
    {
        $row['fields'] = $row['fields'] === null ? null : (json_decode($row['fields'], true) ?: []);

        return $row;
    }
}
```

- [ ] **Step 3: Write the uninstaller**

`uninstall.php`:

```php
<?php

/**
 * Removes the received messages and their settings when the plugin is deleted.
 *
 * Deactivating or updating the plugin keeps them; only "Delete" runs this.
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

global $wpdb;

$wpdb->query('DROP TABLE IF EXISTS ' . $wpdb->prefix . 'lcmt_mailer_submissions');

foreach ([
    'lcmt_mailer_db_version',
    'lcmt_mailer_retention_days',
    'lcmt_mailer_retention_action',
    'lcmt_mailer_stats_retention_days',
    'lcmt_mailer_attribution_without_consent',
    'lcmt_mailer_failures_dismissed_at',
] as $option) {
    delete_option($option);
}

wp_clear_scheduled_hook('lcmt_mailer_purge_submissions');
```

- [ ] **Step 4: Wire the bootstrap**

In `lcmt-dev-mailer.php`, after `require_once LCMT_MAILER_PATH . 'includes/updater.php';` add:

```php
require_once LCMT_MAILER_PATH . 'includes/submission-data.php';
require_once LCMT_MAILER_PATH . 'includes/submission-context.php';
require_once LCMT_MAILER_PATH . 'includes/channel-classifier.php';
require_once LCMT_MAILER_PATH . 'includes/submission-schema.php';
require_once LCMT_MAILER_PATH . 'includes/submission-repository.php';
```

and after the `// ── Translations ──` block:

```php
// ── Received messages ──
add_action('plugins_loaded', ['LcmtDevMailer\\SubmissionSchema', 'maybeUpgrade']);
```

- [ ] **Step 5: Verify on the local site**

Run the rsync command from Global Constraints, load any admin page, then:

```bash
cd /Volumes/Samsung_T5/Freelance/live-decor-production
wp db query "SHOW CREATE TABLE $(wp db prefix)lcmt_mailer_submissions\G"
wp option get lcmt_mailer_db_version
```

Expected: the table with the 21 columns and 5 keys; option `1`. Reload an admin page and check `wp db query "SHOW WARNINGS"` shows nothing new (no second `CREATE`).

- [ ] **Step 6: Commit**

```bash
git add includes/submission-schema.php includes/submission-repository.php uninstall.php lcmt-dev-mailer.php
git commit -m "✨ Store received messages in their own table

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 5: Record submissions and track the email result

**Files:**
- Create: `includes/submission-recorder.php`
- Modify: `includes/meta-fields.php`, `includes/form-endpoint.php`, `lcmt-dev-mailer.php`

**Interfaces:**
- Consumes: `SubmissionData::snapshot()`, `SubmissionContext::fromRequest()`, `ChannelClassifier::classify()`, `SubmissionRepository::insert()/update()`, `Mailer::sendByKey()`.
- Produces:
  - `MetaFields::FIELD_STORE_SUBMISSIONS` = `'_lcmt_mail_store_submissions'`, `MetaFields::storesSubmissions(int $postId): bool`
  - `SubmissionRecorder::record(\WP_Post $post, string $key, array $fields, array $values, mixed $rawContext): int` (0 when not stored)
  - `SubmissionRecorder::send(int $id, string $key, array $placeholders): bool` — sends and writes `mail_sent`/`mail_error` when `$id > 0`
  - `SubmissionRecorder::captureMailError(\WP_Error $error): void` (hooked to `wp_mail_failed`)
  - filter `lcmt_mailer_submission_channel` (`string $channel, array $context`)

- [ ] **Step 1: Per-form checkbox**

In `includes/meta-fields.php`, add the constant after `FIELD_CONTENT`:

```php
    public const FIELD_STORE_SUBMISSIONS = '_lcmt_mail_store_submissions';
```

In `render()`, before `echo '</table>';`:

```php
        echo '<tr>';
        echo '<th>' . esc_html__('Received messages', 'lcmt-dev-mailer') . '</th>';
        echo '<td>';
        echo '<label><input type="checkbox" name="lcmt_mail_store_submissions" value="1" ' . checked(self::storesSubmissions($post->ID), true, false) . ' /> ';
        echo esc_html__('Save the messages sent through this form', 'lcmt-dev-mailer') . '</label>';
        echo '<p class="description">' . esc_html__('They appear under Email templates → Received messages, and are anonymized or deleted after the retention period set there.', 'lcmt-dev-mailer') . '</p>';
        echo '</td>';
        echo '</tr>';
```

In `save()`, after the `lcmt_mail_content` block (the nonce checks above guarantee the box was on the page, so a missing key means unchecked):

```php
        update_post_meta($postId, self::FIELD_STORE_SUBMISSIONS, isset($_POST['lcmt_mail_store_submissions']) ? '1' : '0');
```

Add after `save()`:

```php
    /**
     * Whether submissions of this form are saved. On unless turned off, so
     * templates created before the option existed save theirs too.
     */
    public static function storesSubmissions(int $postId): bool
    {
        return get_post_meta($postId, self::FIELD_STORE_SUBMISSIONS, true) !== '0';
    }
```

- [ ] **Step 2: Write the recorder**

`includes/submission-recorder.php`:

```php
<?php

namespace LcmtDevMailer;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Saves a submission before its email goes out, then records whether it did.
 */
class SubmissionRecorder
{
    /**
     * The message of the last wp_mail failure during send().
     */
    private static ?string $mailError = null;

    /**
     * @param array<string, array{name: string, required: bool, type: string}> $fields
     * @param array<string, string> $values
     * @param mixed $rawContext The `_context` value of the request body.
     * @return int The submission id, or 0 when this form does not save messages.
     */
    public static function record(\WP_Post $post, string $key, array $fields, array $values, $rawContext): int
    {
        if (!MetaFields::storesSubmissions($post->ID)) {
            return 0;
        }

        $home    = home_url();
        $context = SubmissionContext::fromRequest($rawContext, (string) parse_url($home, PHP_URL_HOST));

        /**
         * Filter the channel a submission is counted under.
         *
         * @param string $channel One of ChannelClassifier::CHANNELS.
         * @param array  $context The cleaned context columns.
         */
        $channel = (string) apply_filters('lcmt_mailer_submission_channel', ChannelClassifier::classify($context), $context);

        return SubmissionRepository::insert([
            'form_key'     => $key,
            'mail_post_id' => $post->ID,
            'created_at'   => current_time('mysql', true),
            'status'       => 'new',
            'mail_sent'    => 0,
            'fields'       => wp_json_encode(SubmissionData::snapshot($fields, $values), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'page_id'      => self::pageId($home, $context['page_path']),
            'channel'      => substr($channel, 0, 30),
        ] + $context);
    }

    /**
     * Send the email of a submission and store the outcome on it.
     *
     * @param int $id 0 sends without recording anything.
     */
    public static function send(int $id, string $key, array $placeholders): bool
    {
        self::$mailError = null;

        $sent = Mailer::sendByKey($key, $placeholders);

        if ($id) {
            SubmissionRepository::update($id, [
                'mail_sent'  => $sent ? 1 : 0,
                'mail_error' => $sent ? null : (self::$mailError ?? __('The email could not be built: check that its template is published and has a recipient.', 'lcmt-dev-mailer')),
            ]);
        }

        self::$mailError = null;

        return $sent;
    }

    public static function captureMailError(\WP_Error $error): void
    {
        self::$mailError = $error->get_error_message();
    }

    /**
     * The post the form was sent from, when the path resolves to one.
     */
    private static function pageId(string $home, string $path): int
    {
        if ($path === '') {
            return 0;
        }

        // The path already holds any subdirectory of home_url().
        $origin = (string) preg_replace('#^(https?://[^/]+).*$#', '$1', $home);

        return (int) url_to_postid($origin . $path);
    }
}
```

- [ ] **Step 3: Use it in the endpoint**

In `includes/form-endpoint.php`, `handle()`:

Replace

```php
        $errors = [];
        $placeholders = [];
```

with

```php
        $errors = [];
        $placeholders = [];
        $values = [];
```

and after `$placeholders['[' . $field['name'] . '*]'] = $value;` add:

```php
            $values[$field['name']] = $value;
```

Replace the block from `do_action('lcmt_mailer_before_send', ...)` down to `$sent = Mailer::sendByKey($key, $placeholders);` so it reads:

```php
        $submissionId = SubmissionRecorder::record($post, $key, $fields, $values, $body['_context'] ?? null);

        /**
         * Action fired before sending the form email.
         *
         * @param string $key          The form key.
         * @param array  $placeholders The sanitized form data as placeholders.
         * @param \WP_Post $post       The mail post.
         */
        do_action('lcmt_mailer_before_send', $key, $placeholders, $post);

        $sent = SubmissionRecorder::send($submissionId, $key, $placeholders);
```

- [ ] **Step 4: Wire the bootstrap**

In `lcmt-dev-mailer.php`, add the require after `submission-repository.php`:

```php
require_once LCMT_MAILER_PATH . 'includes/submission-recorder.php';
```

and under `// ── Received messages ──`:

```php
add_action('wp_mail_failed', ['LcmtDevMailer\\SubmissionRecorder', 'captureMailError']);
```

- [ ] **Step 5: Verify on the local site**

Rsync, then send the contact form of the local Live Decor site. Then:

```bash
wp db query "SELECT id, form_key, status, mail_sent, mail_error, fields, page_path, page_id, channel FROM $(wp db prefix)lcmt_mailer_submissions ORDER BY id DESC LIMIT 1\G"
```

Expected: one row, `mail_sent = 1`, `fields` holds the typed values with accents readable, `page_path` empty (the JS context comes in Task 6), `channel = direct`.

Force a failure: add in the theme's `functions.php` temporarily `add_filter('pre_wp_mail', fn() => (do_action('wp_mail_failed', new WP_Error('wp_mail_failed', 'SMTP connect() failed.')) ?: false));`, send the form again. Expected: the visitor sees "Failed to send email.", and the new row has `mail_sent = 0`, `mail_error = SMTP connect() failed.`. Remove the filter.

Untick "Save the messages sent through this form" on the template, send again: no new row, email still sent. Tick it back.

- [ ] **Step 6: Commit**

```bash
git add includes/submission-recorder.php includes/meta-fields.php includes/form-endpoint.php lcmt-dev-mailer.php
git commit -m "✨ Save each submission before its email goes out, with the send result

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 6: Remember where the visit came from (front end)

**Files:**
- Create: `assets/src/lib/attribution.js`, `assets/src/attribution.js`, `includes/attribution.php`
- Modify: `assets/src/form-handler.js`, `lcmt-dev-mailer.php`
- Build output: `assets/dist/attribution.js`, `assets/dist/form-handler.js`

**Interfaces:**
- Consumes: global `wp_has_consent(category)` and the `wp_listen_for_consent_change` event from WP Consent API when present; `window.lcmtMailerAttribution.storeWithoutConsent` (bool).
- Produces:
  - JS `formContext(startedAt)` → the `_context` object consumed by `SubmissionContext::fromRequest()` (Task 2).
  - PHP `Attribution::enqueue(): void`.
  - PHP `Attribution::storesWithoutConsent(): bool` — reads option `lcmt_mailer_attribution_without_consent`, default `'1'` (the settings screen in Task 9 writes it).

Note: esbuild bundles `assets/src/*.js` only, so `assets/src/lib/` is imported, never emitted on its own.

- [ ] **Step 1: Shared module**

`assets/src/lib/attribution.js`:

```js
/**
 * Where a visit came from, remembered across the pages of a session so a
 * form sent three pages later still knows the ad or search that brought
 * the visitor.
 *
 * Only paths, the referrer and campaign parameters are kept, in
 * sessionStorage (gone when the tab closes). Click ids are kept by name,
 * never their value.
 */
var STORAGE_KEY = 'lcmtMailerLanding';
var UTM_KEYS = ['utm_source', 'utm_medium', 'utm_campaign'];
var CLICK_IDS = ['gclid', 'gbraid', 'wbraid', 'msclkid', 'fbclid', 'ttclid', 'li_fat_id'];

export function currentTouch() {
  var params = new URLSearchParams(window.location.search);
  var touch = { path: window.location.pathname, referrer: document.referrer || '' };

  UTM_KEYS.forEach(function (key) {
    var value = params.get(key);
    if (value) touch[key] = value.slice(0, 150);
  });

  for (var i = 0; i < CLICK_IDS.length; i++) {
    if (params.has(CLICK_IDS[i])) {
      touch.click_id = CLICK_IDS[i];
      break;
    }
  }

  return touch;
}

/**
 * A touch that starts a new visit: campaign parameters, or a link from
 * another site. It replaces the landing already remembered.
 */
export function startsVisit(touch) {
  if (touch.click_id || touch.utm_source) return true;
  if (!touch.referrer) return false;

  try {
    return new URL(touch.referrer).host !== window.location.host;
  } catch (err) {
    return false;
  }
}

export function storedTouch() {
  try {
    var raw = window.sessionStorage.getItem(STORAGE_KEY);
    return raw ? JSON.parse(raw) : null;
  } catch (err) {
    return null;
  }
}

export function storeTouch(touch) {
  try {
    window.sessionStorage.setItem(STORAGE_KEY, JSON.stringify(touch));
  } catch (err) {
    // Storage blocked or full: the form falls back to the current page.
  }
}

/**
 * Whether the landing may be written to the browser: the statistics consent
 * when WP Consent API is installed, the site setting otherwise.
 */
export function mayStore() {
  if (typeof window.wp_has_consent === 'function') {
    return window.wp_has_consent('statistics');
  }

  var config = window.lcmtMailerAttribution || {};
  return !!config.storeWithoutConsent;
}

export function device() {
  var width = window.innerWidth || document.documentElement.clientWidth;
  var coarse = !!(window.matchMedia && window.matchMedia('(pointer: coarse)').matches);

  if (width < 768) return 'mobile';
  if (coarse && width < 1280) return 'tablet';
  return 'desktop';
}

/**
 * The context sent with a form. Without a remembered landing (no consent,
 * storage blocked, form on the landing page), the current page stands in.
 *
 * @param {number|null} startedAt Time of the first interaction with the form.
 */
export function formContext(startedAt) {
  var current = currentTouch();
  var landing = storedTouch();

  if (!landing || startsVisit(current)) landing = current;

  return {
    page: current.path,
    landing: landing,
    device: device(),
    locale: navigator.language || '',
    seconds: startedAt ? Math.round((Date.now() - startedAt) / 1000) : null,
  };
}
```

- [ ] **Step 2: Entry loaded on every page**

`assets/src/attribution.js`:

```js
/**
 * LCMT Mailer — remembers the landing page of the visit for form statistics.
 */
import { currentTouch, startsVisit, storedTouch, storeTouch, mayStore } from './lib/attribution';

(function () {
  'use strict';

  var touch = currentTouch();

  if (storedTouch() && !startsVisit(touch)) return;

  if (mayStore()) {
    storeTouch(touch);
    return;
  }

  // Keep the landing in memory until the visitor accepts statistics on
  // this page, which is where most consent banners are answered.
  document.addEventListener('wp_listen_for_consent_change', function (e) {
    if (e.detail && e.detail.statistics === 'allow') storeTouch(touch);
  });
})();
```

- [ ] **Step 3: Send the context with the form**

In `assets/src/form-handler.js`:

After the other imports add:

```js
import { formContext } from './lib/attribution';
```

In `bindForm(form)`, right after the ALTCHA cache-bust block (before `form.addEventListener('submit', ...)`), add:

```js
    // Time spent filling the form, from the first field the visitor enters.
    var startedAt = null;

    form.addEventListener('focusin', function () {
      if (!startedAt) startedAt = Date.now();
    });
```

In the `.then(function (payload) { ... })` before `fetch`, after `if (payload) data['altcha'] = payload;` add:

```js
          data['_context'] = formContext(startedAt);
```

In the success branch, after `form.reset();` add:

```js
            startedAt = null;
```

- [ ] **Step 4: Enqueue from PHP**

`includes/attribution.php`:

```php
<?php

namespace LcmtDevMailer;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Loads the script that remembers the landing page on every public page, so
 * a form sent later in the visit knows where the visitor came from.
 */
class Attribution
{
    public const OPTION_WITHOUT_CONSENT = 'lcmt_mailer_attribution_without_consent';

    public static function enqueue(): void
    {
        // Run after WP Consent API when it is there, so wp_has_consent exists.
        $deps = wp_script_is('wp-consent-api', 'registered') ? ['wp-consent-api'] : [];

        wp_enqueue_script(
            'lcmt-attribution',
            LCMT_MAILER_URL . 'assets/dist/attribution.js',
            $deps,
            lcmt_mailer_asset_version('assets/dist/attribution.js'),
            true
        );

        wp_localize_script('lcmt-attribution', 'lcmtMailerAttribution', [
            'storeWithoutConsent' => self::storesWithoutConsent(),
        ]);
    }

    /**
     * Whether the landing is remembered when no consent tool is installed.
     */
    public static function storesWithoutConsent(): bool
    {
        return get_option(self::OPTION_WITHOUT_CONSENT, '1') === '1';
    }
}
```

In `lcmt-dev-mailer.php`, add the require after `submission-recorder.php`:

```php
require_once LCMT_MAILER_PATH . 'includes/attribution.php';
```

Under `// ── Received messages ──`:

```php
add_action('wp_enqueue_scripts', ['LcmtDevMailer\\Attribution', 'enqueue']);

// Tell WP Consent API this plugin follows its consent categories.
add_filter('wp_consent_api_registered_' . plugin_basename(__FILE__), '__return_true');
```

- [ ] **Step 5: Build**

```bash
cd /Volumes/Samsung_T5/Freelance/lcmt-dev-mailer
nvm use 20 && yarn build
ls assets/dist
```

Expected: `attribution.js` next to the other dist files, no `lib/` folder in `assets/dist`.

- [ ] **Step 6: Verify on the local site**

Rsync. In a private window:
1. Open `/?utm_source=google&utm_medium=cpc&utm_campaign=test-plan&gclid=abc`, then navigate to two other pages, then send the contact form.
2. Check the row:

```bash
wp db query "SELECT page_path, page_id, landing_path, referrer_host, utm_source, utm_medium, utm_campaign, click_id_type, channel, device, locale, form_seconds FROM $(wp db prefix)lcmt_mailer_submissions ORDER BY id DESC LIMIT 1\G"
```

Expected: `page_path` = contact page path with its `page_id`, `landing_path = /`, `utm_campaign = test-plan`, `click_id_type = gclid`, `channel = google_ads`, `device = desktop`, `locale = fr-FR` (or the browser language), `form_seconds` a positive number.

3. In DevTools → Application → Session Storage, `lcmtMailerLanding` holds `{"path":"/","referrer":"","utm_source":"google",...,"click_id":"gclid"}` with no `abc` anywhere.
4. Temporarily `wp option update lcmt_mailer_attribution_without_consent 0`, open a new private window with the same URL, check Session Storage stays empty, then send the form from the landing page itself: the campaign is still recorded (current page stands in). Restore with `wp option delete lcmt_mailer_attribution_without_consent`.

- [ ] **Step 7: Commit**

```bash
git add assets/src/lib/attribution.js assets/src/attribution.js assets/src/form-handler.js assets/dist includes/attribution.php lcmt-dev-mailer.php
git commit -m "✨ Remember the landing page and campaign of a visit for form statistics

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 7: Received messages screen

**Files:**
- Create: `includes/submissions-page.php`, `includes/submissions-list-table.php`
- Modify: `lcmt-dev-mailer.php`

**Interfaces:**
- Consumes: `SubmissionRepository` (search, count, find, setStatus, delete, countUnread, formKeys), `SubmissionData::summary()`, `ChannelClassifier::label()/CHANNELS`.
- Produces:
  - `SubmissionsPage::PAGE_SLUG` = `'lcmt-mailer-submissions'`
  - `SubmissionsPage::capability(): string` (filter `lcmt_mailer_submissions_capability`, default `manage_options`)
  - `SubmissionsPage::url(array $args = []): string`
  - `SubmissionsPage::filtersFromRequest(): array` (shape of `SubmissionRepository` filters)
  - `SubmissionsPage::apply(string $do, array $ids): string` — `$do` in `STATUSES`, `delete`, `resend` (resend is added in Task 8); returns a notice code
  - admin-post action `lcmt_mailer_submission` (params `id`, `do`, nonce `lcmt_submission_{id}`)

- [ ] **Step 1: The page controller**

`includes/submissions-page.php`:

```php
<?php

namespace LcmtDevMailer;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Email templates → Received messages: the list, one message, and its actions.
 */
class SubmissionsPage
{
    public const PAGE_SLUG = 'lcmt-mailer-submissions';
    public const ACTION = 'lcmt_mailer_submission';

    public static function capability(): string
    {
        return (string) apply_filters('lcmt_mailer_submissions_capability', 'manage_options');
    }

    public static function url(array $args = []): string
    {
        return add_query_arg(
            array_merge(['post_type' => PostType::SLUG, 'page' => self::PAGE_SLUG], $args),
            admin_url('edit.php')
        );
    }

    public static function addSubmenu(): void
    {
        $unread = SubmissionRepository::countUnread();
        $title  = __('Received messages', 'lcmt-dev-mailer');
        $menu   = $unread
            ? $title . ' <span class="awaiting-mod">' . number_format_i18n($unread) . '</span>'
            : $title;

        $hook = add_submenu_page(
            'edit.php?post_type=' . PostType::SLUG,
            $title,
            $menu,
            self::capability(),
            self::PAGE_SLUG,
            [self::class, 'render']
        );

        if ($hook) {
            add_action('load-' . $hook, [self::class, 'handleLoad']);
        }
    }

    /**
     * @return array{form_key: string, status: string, failed: bool, channel: string, search: string}
     */
    public static function filtersFromRequest(): array
    {
        $status  = sanitize_key($_GET['status'] ?? '');
        $channel = sanitize_key($_GET['channel'] ?? '');

        return [
            'form_key' => sanitize_title(wp_unslash($_GET['form_key'] ?? '')),
            'status'   => in_array($status, SubmissionRepository::STATUSES, true) ? $status : '',
            'failed'   => !empty($_GET['failed']),
            'channel'  => in_array($channel, ChannelClassifier::CHANNELS, true) ? $channel : '',
            'search'   => sanitize_text_field(wp_unslash($_GET['s'] ?? '')),
        ];
    }

    /**
     * Before any output: mark an opened message as read, run bulk actions.
     */
    public static function handleLoad(): void
    {
        $id = absint($_GET['submission'] ?? 0);

        if ($id) {
            $row = SubmissionRepository::find($id);

            if ($row && $row['status'] === 'new') {
                SubmissionRepository::setStatus([$id], 'read');
            }

            return;
        }

        $action = sanitize_key($_GET['action'] ?? '-1');

        if ($action === '-1') {
            $action = sanitize_key($_GET['action2'] ?? '-1');
        }

        $ids = array_map('absint', (array) ($_GET['ids'] ?? []));

        if ($action === '-1' || !$ids) {
            return;
        }

        check_admin_referer('bulk-submissions');

        $notice = self::apply($action, $ids);

        wp_safe_redirect(self::url(array_filter(self::filtersFromRequest()) + ['notice' => $notice]));
        exit;
    }

    /**
     * admin-post.php handler for the buttons of one message.
     */
    public static function handleSingle(): void
    {
        $id = absint($_REQUEST['id'] ?? 0);
        $do = sanitize_key($_REQUEST['do'] ?? '');

        if (!current_user_can(self::capability())) {
            wp_die(esc_html__('You are not allowed to do this.', 'lcmt-dev-mailer'), 403);
        }

        check_admin_referer('lcmt_submission_' . $id);

        $notice = self::apply($do, [$id]);

        // Back to the list after "unread" too: reopening the message would mark it read again.
        $args = in_array($do, ['delete', 'new'], true) ? ['notice' => $notice] : ['submission' => $id, 'notice' => $notice];

        wp_safe_redirect(self::url($args));
        exit;
    }

    /**
     * @param list<int> $ids
     * @return string A notice code for render().
     */
    public static function apply(string $do, array $ids): string
    {
        if (in_array($do, SubmissionRepository::STATUSES, true)) {
            SubmissionRepository::setStatus($ids, $do);
            return 'updated';
        }

        if ($do === 'delete') {
            SubmissionRepository::delete($ids);
            return 'deleted';
        }

        return '';
    }

    public static function singleActionUrl(int $id, string $do): string
    {
        return wp_nonce_url(
            add_query_arg(['action' => self::ACTION, 'id' => $id, 'do' => $do], admin_url('admin-post.php')),
            'lcmt_submission_' . $id
        );
    }

    public static function render(): void
    {
        echo '<div class="wrap">';

        self::renderNotice();

        $id = absint($_GET['submission'] ?? 0);

        if ($id) {
            self::renderDetail($id);
        } else {
            self::renderList();
        }

        echo '</div>';
    }

    private static function renderNotice(): void
    {
        $messages = [
            'updated'       => [__('Messages updated.', 'lcmt-dev-mailer'), 'success'],
            'deleted'       => [__('Messages deleted.', 'lcmt-dev-mailer'), 'success'],
            'resent'        => [__('Email sent.', 'lcmt-dev-mailer'), 'success'],
            'resend_failed' => [__('The email could not be sent again. The reason is shown below.', 'lcmt-dev-mailer'), 'error'],
        ];

        $code = sanitize_key($_GET['notice'] ?? '');

        if (isset($messages[$code])) {
            printf(
                '<div class="notice notice-%s is-dismissible"><p>%s</p></div>',
                esc_attr($messages[$code][1]),
                esc_html($messages[$code][0])
            );
        }
    }

    private static function renderList(): void
    {
        require_once LCMT_MAILER_PATH . 'includes/submissions-list-table.php';

        $table = new SubmissionsListTable();
        $table->prepare_items();

        echo '<h1 class="wp-heading-inline">' . esc_html__('Received messages', 'lcmt-dev-mailer') . '</h1>';
        echo '<hr class="wp-header-end">';

        $table->views();

        echo '<form method="get">';
        echo '<input type="hidden" name="post_type" value="' . esc_attr(PostType::SLUG) . '" />';
        echo '<input type="hidden" name="page" value="' . esc_attr(self::PAGE_SLUG) . '" />';

        foreach (['status', 'failed'] as $kept) {
            if (!empty($_GET[$kept])) {
                echo '<input type="hidden" name="' . esc_attr($kept) . '" value="' . esc_attr(sanitize_key($_GET[$kept])) . '" />';
            }
        }

        $table->search_box(__('Search messages', 'lcmt-dev-mailer'), 'lcmt-submissions');
        $table->display();
        echo '</form>';
    }

    private static function renderDetail(int $id): void
    {
        $row = SubmissionRepository::find($id);

        echo '<h1>' . esc_html__('Received message', 'lcmt-dev-mailer') . '</h1>';
        echo '<p><a href="' . esc_url(self::url()) . '">&larr; ' . esc_html__('Back to the messages', 'lcmt-dev-mailer') . '</a></p>';

        if (!$row) {
            echo '<p>' . esc_html__('This message no longer exists.', 'lcmt-dev-mailer') . '</p>';
            return;
        }

        $format = get_option('date_format') . ' ' . get_option('time_format');

        // ── What the visitor typed ──
        echo '<h2>' . esc_html__('Message', 'lcmt-dev-mailer') . '</h2>';

        if ($row['fields'] === null) {
            printf(
                '<p><em>%s</em></p>',
                esc_html(sprintf(
                    /* translators: %s: date of the anonymization */
                    __('Personal data anonymized on %s.', 'lcmt-dev-mailer'),
                    get_date_from_gmt((string) $row['anonymized_at'], $format)
                ))
            );
        } else {
            echo '<table class="widefat striped" style="max-width: 900px;"><tbody>';

            foreach ($row['fields'] as $field) {
                echo '<tr>';
                echo '<th style="width: 200px;"><code>' . esc_html($field['name']) . '</code></th>';
                echo '<td>' . nl2br(esc_html($field['value'])) . '</td>';
                echo '</tr>';
            }

            echo '</tbody></table>';
        }

        // ── Email ──
        echo '<h2>' . esc_html__('Email', 'lcmt-dev-mailer') . '</h2>';

        if ((int) $row['mail_sent'] === 1) {
            echo '<p style="color: #008a20;">&#10003; ' . esc_html__('Sent', 'lcmt-dev-mailer') . '</p>';
        } else {
            echo '<p style="color: #d63638;">&#10007; ' . esc_html__('Not sent', 'lcmt-dev-mailer') . '</p>';
            echo '<p><code>' . esc_html($row['mail_error'] ?: __('The request stopped before the email was sent.', 'lcmt-dev-mailer')) . '</code></p>';
        }

        // ── Context ──
        $context = [
            __('Date', 'lcmt-dev-mailer')             => get_date_from_gmt((string) $row['created_at'], $format),
            __('Form', 'lcmt-dev-mailer')             => $row['form_key'],
            __('Sent from', 'lcmt-dev-mailer')        => $row['page_path'],
            __('Landing page', 'lcmt-dev-mailer')     => $row['landing_path'],
            __('Source', 'lcmt-dev-mailer')           => ChannelClassifier::label((string) $row['channel']),
            __('Referring site', 'lcmt-dev-mailer')   => $row['referrer_host'],
            __('Campaign', 'lcmt-dev-mailer')         => trim($row['utm_source'] . ' / ' . $row['utm_medium'] . ' / ' . $row['utm_campaign'], ' /'),
            __('Ad click', 'lcmt-dev-mailer')         => $row['click_id_type'],
            __('Device', 'lcmt-dev-mailer')           => $row['device'],
            __('Browser language', 'lcmt-dev-mailer') => $row['locale'],
            __('Time on the form', 'lcmt-dev-mailer') => $row['form_seconds'] === null ? '' : human_time_diff(0, (int) $row['form_seconds']),
        ];

        echo '<h2>' . esc_html__('Context', 'lcmt-dev-mailer') . '</h2>';
        echo '<table class="widefat striped" style="max-width: 900px;"><tbody>';

        foreach ($context as $label => $value) {
            if ((string) $value === '') {
                continue;
            }

            echo '<tr><th style="width: 200px;">' . esc_html($label) . '</th><td>' . esc_html((string) $value) . '</td></tr>';
        }

        echo '</tbody></table>';

        // ── Actions ──
        $buttons = [
            'processed' => __('Mark as processed', 'lcmt-dev-mailer'),
            'new'       => __('Mark as unread', 'lcmt-dev-mailer'),
            'spam'      => __('Mark as spam', 'lcmt-dev-mailer'),
        ];

        /**
         * Filter the action buttons of a received message.
         *
         * @param array<string, string> $buttons Action => label.
         * @param array                 $row     The submission.
         */
        $buttons = (array) apply_filters('lcmt_mailer_submission_actions', $buttons, $row);

        echo '<p style="margin-top: 20px; display: flex; gap: 8px; flex-wrap: wrap;">';

        foreach ($buttons as $do => $label) {
            echo '<a class="button" href="' . esc_url(self::singleActionUrl($id, $do)) . '">' . esc_html($label) . '</a>';
        }

        echo '<a class="button button-link-delete" href="' . esc_url(self::singleActionUrl($id, 'delete')) . '" onclick="return confirm(' . esc_attr(wp_json_encode(__('Delete this message for good?', 'lcmt-dev-mailer'))) . ');">' . esc_html__('Delete', 'lcmt-dev-mailer') . '</a>';
        echo '</p>';
    }
}
```

- [ ] **Step 2: The list table**

`includes/submissions-list-table.php`:

```php
<?php

namespace LcmtDevMailer;

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('WP_List_Table')) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * The table of Email templates → Received messages. Loaded only on that screen.
 */
class SubmissionsListTable extends \WP_List_Table
{
    private const PER_PAGE = 20;

    public function __construct()
    {
        parent::__construct([
            'singular' => 'submission',
            'plural'   => 'submissions',
            'ajax'     => false,
        ]);
    }

    public function get_columns(): array
    {
        return [
            'cb'         => '<input type="checkbox" />',
            'summary'    => __('Sender', 'lcmt-dev-mailer'),
            'form_key'   => __('Form', 'lcmt-dev-mailer'),
            'page_path'  => __('Sent from', 'lcmt-dev-mailer'),
            'channel'    => __('Source', 'lcmt-dev-mailer'),
            'mail'       => __('Email', 'lcmt-dev-mailer'),
            'created_at' => __('Date', 'lcmt-dev-mailer'),
        ];
    }

    public function prepare_items(): void
    {
        $filters = SubmissionsPage::filtersFromRequest();

        $this->items = SubmissionRepository::search($filters, self::PER_PAGE, $this->get_pagenum());

        $this->set_pagination_args([
            'total_items' => SubmissionRepository::count($filters),
            'per_page'    => self::PER_PAGE,
        ]);

        $this->_column_headers = [$this->get_columns(), [], []];
    }

    public function no_items(): void
    {
        esc_html_e('No messages yet.', 'lcmt-dev-mailer');
    }

    protected function get_views(): array
    {
        $current = SubmissionsPage::filtersFromRequest();

        $views = [
            'all'    => [__('All', 'lcmt-dev-mailer'), [], $current['status'] === '' && !$current['failed']],
            'new'    => [__('Unread', 'lcmt-dev-mailer'), ['status' => 'new'], $current['status'] === 'new'],
            'failed' => [__('Email failed', 'lcmt-dev-mailer'), ['failed' => 1], $current['failed']],
            'spam'   => [__('Spam', 'lcmt-dev-mailer'), ['status' => 'spam'], $current['status'] === 'spam'],
        ];

        $links = [];

        foreach ($views as $key => [$label, $args, $active]) {
            $links[$key] = sprintf(
                '<a href="%s"%s>%s <span class="count">(%s)</span></a>',
                esc_url(SubmissionsPage::url($args)),
                $active ? ' class="current" aria-current="page"' : '',
                esc_html($label),
                number_format_i18n(SubmissionRepository::count($args))
            );
        }

        return $links;
    }

    protected function get_bulk_actions(): array
    {
        return [
            'read'      => __('Mark as read', 'lcmt-dev-mailer'),
            'processed' => __('Mark as processed', 'lcmt-dev-mailer'),
            'spam'      => __('Mark as spam', 'lcmt-dev-mailer'),
            'delete'    => __('Delete', 'lcmt-dev-mailer'),
        ];
    }

    protected function extra_tablenav($which): void
    {
        if ($which !== 'top') {
            return;
        }

        $filters = SubmissionsPage::filtersFromRequest();

        echo '<div class="alignleft actions">';

        echo '<select name="form_key"><option value="">' . esc_html__('All forms', 'lcmt-dev-mailer') . '</option>';
        foreach (SubmissionRepository::formKeys() as $key) {
            printf('<option value="%1$s"%2$s>%1$s</option>', esc_attr($key), selected($filters['form_key'], $key, false));
        }
        echo '</select>';

        echo '<select name="channel"><option value="">' . esc_html__('All sources', 'lcmt-dev-mailer') . '</option>';
        foreach (ChannelClassifier::CHANNELS as $channel) {
            printf('<option value="%s"%s>%s</option>', esc_attr($channel), selected($filters['channel'], $channel, false), esc_html(ChannelClassifier::label($channel)));
        }
        echo '</select>';

        submit_button(__('Filter', 'lcmt-dev-mailer'), '', 'filter_action', false);

        echo '</div>';
    }

    protected function column_cb($item): string
    {
        return '<input type="checkbox" name="ids[]" value="' . (int) $item['id'] . '" />';
    }

    protected function column_summary(array $item): string
    {
        $label = $item['fields'] === null
            ? __('Anonymized', 'lcmt-dev-mailer')
            : (SubmissionData::summary($item['fields']) ?: __('(no text)', 'lcmt-dev-mailer'));

        $text = esc_html($label);

        if ($item['status'] === 'new') {
            $text = '<strong>' . $text . '</strong>';
        }

        $actions = [
            'view'   => '<a href="' . esc_url(SubmissionsPage::url(['submission' => $item['id']])) . '">' . esc_html__('View', 'lcmt-dev-mailer') . '</a>',
            'delete' => '<a class="submitdelete" href="' . esc_url(SubmissionsPage::singleActionUrl((int) $item['id'], 'delete')) . '">' . esc_html__('Delete', 'lcmt-dev-mailer') . '</a>',
        ];

        return '<a href="' . esc_url(SubmissionsPage::url(['submission' => $item['id']])) . '">' . $text . '</a>' . $this->row_actions($actions);
    }

    protected function column_channel(array $item): string
    {
        $html = esc_html(ChannelClassifier::label((string) $item['channel']));

        if ($item['utm_campaign'] !== '') {
            $html .= '<br><small>' . esc_html($item['utm_campaign']) . '</small>';
        }

        return $html;
    }

    protected function column_mail(array $item): string
    {
        return (int) $item['mail_sent'] === 1
            ? '<span style="color: #008a20;">&#10003; ' . esc_html__('Sent', 'lcmt-dev-mailer') . '</span>'
            : '<span style="color: #d63638;">&#10007; ' . esc_html__('Not sent', 'lcmt-dev-mailer') . '</span>';
    }

    protected function column_created_at(array $item): string
    {
        return esc_html(get_date_from_gmt((string) $item['created_at'], get_option('date_format') . ' ' . get_option('time_format')));
    }

    protected function column_default($item, $column_name): string
    {
        return esc_html((string) ($item[$column_name] ?? ''));
    }
}
```

- [ ] **Step 3: Wire the bootstrap**

In `lcmt-dev-mailer.php`, add the require after `attribution.php` (not the list table, it is loaded on its screen):

```php
require_once LCMT_MAILER_PATH . 'includes/submissions-page.php';
```

Under `// ── Admin UI ──`, **before** the `Settings` submenu line so the new screen sits right after "Add New":

```php
add_action('admin_menu', ['LcmtDevMailer\\SubmissionsPage', 'addSubmenu']);
add_action('admin_post_' . LcmtDevMailer\SubmissionsPage::ACTION, ['LcmtDevMailer\\SubmissionsPage', 'handleSingle']);
```

- [ ] **Step 4: Verify on the local site**

Rsync. With the rows from Tasks 5–6:
1. The menu shows "Received messages" with a red bubble equal to the unread count.
2. The list shows sender, form, page, source (with campaign under it), email state and local date. Unread rows are bold.
3. Views All / Unread / Email failed / Spam filter and count correctly; the form and source selects filter; search finds a word from a message (try one with an accent).
4. Open a message: bubble drops by one, fields show with line breaks, context shows only filled rows, the failed row shows its error.
5. "Mark as spam" hides it from All; "Delete" asks for confirmation and removes it; bulk "Mark as processed" on two rows works and keeps the active filters after redirect.
6. Log in as an editor: the menu entry is absent and `admin-post.php?action=lcmt_mailer_submission&id=1&do=delete` answers 403.

- [ ] **Step 5: Commit**

```bash
git add includes/submissions-page.php includes/submissions-list-table.php lcmt-dev-mailer.php
git commit -m "✨ List and read received messages in the admin

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 8: Resend a failed email, CSV export

**Files:**
- Create: `includes/submission-csv.php`
- Modify: `includes/submissions-page.php`, `includes/submissions-list-table.php`, `lcmt-dev-mailer.php`, `tests/bootstrap.php`
- Test: `tests/SubmissionCsvTest.php`

**Interfaces:**
- Consumes: `SubmissionRecorder::send()`, `SubmissionData::toPlaceholders()`, `SubmissionRepository::find()/search()`, `SubmissionsPage::filtersFromRequest()`.
- Produces:
  - `SubmissionCsv::COLUMNS` (list of context column names)
  - `SubmissionCsv::table(array $rows): list<list<string>>` — header row then one row per submission
  - `SubmissionCsv::cell(string $value): string`
  - `SubmissionsPage::apply('resend', [$id])` returns `resent` or `resend_failed`
  - admin-post action `lcmt_mailer_export` (nonce `lcmt_mailer_export`)

- [ ] **Step 1: Write the failing test**

`tests/SubmissionCsvTest.php`:

```php
<?php

use LcmtDevMailer\SubmissionCsv;
use PHPUnit\Framework\TestCase;

class SubmissionCsvTest extends TestCase
{
    private static function row(array $overrides): array
    {
        return array_merge(array_fill_keys(SubmissionCsv::COLUMNS, ''), ['fields' => []], $overrides);
    }

    public function testAddsOneColumnPerFieldAcrossForms(): void
    {
        $table = SubmissionCsv::table([
            self::row(['form_key' => 'contact', 'fields' => [['name' => 'email', 'type' => 'email', 'value' => 'a@b.fr']]]),
            self::row(['form_key' => 'quote', 'fields' => [['name' => 'budget', 'type' => 'number', 'value' => '1200']]]),
        ]);

        $header = $table[0];
        $this->assertSame(['email', 'budget'], array_slice($header, count(SubmissionCsv::COLUMNS)));
        $this->assertSame(['a@b.fr', ''], array_slice($table[1], count(SubmissionCsv::COLUMNS)));
        $this->assertSame(['', '1200'], array_slice($table[2], count(SubmissionCsv::COLUMNS)));
    }

    public function testExportsAnonymizedRowsWithEmptyFields(): void
    {
        $table = SubmissionCsv::table([self::row(['form_key' => 'contact', 'fields' => null])]);

        $this->assertCount(count(SubmissionCsv::COLUMNS), $table[1]);
    }

    public function testNeutralisesFormulas(): void
    {
        foreach (['=HYPERLINK("x")', '+33 6 12', '-2+3', '@SUM(A1)', "\tcmd"] as $value) {
            $this->assertSame("'" . $value, SubmissionCsv::cell($value), $value);
        }

        $this->assertSame('Bonjour = merci', SubmissionCsv::cell('Bonjour = merci'));
    }

    public function testNeutralisesFormulasInsideFields(): void
    {
        $table = SubmissionCsv::table([
            self::row(['fields' => [['name' => 'message', 'type' => 'textarea', 'value' => '=1+1']]]),
        ]);

        $this->assertSame("'=1+1", end($table[1]));
    }
}
```

Add to `tests/bootstrap.php`:

```php
require_once dirname(__DIR__) . '/includes/submission-csv.php';
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `phpunit --filter SubmissionCsvTest`
Expected: FAIL — missing file.

- [ ] **Step 3: Write the CSV builder**

`includes/submission-csv.php`:

```php
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
```

- [ ] **Step 4: Run the tests to verify they pass**

Run: `phpunit`
Expected: all PASS.

- [ ] **Step 5: Resend**

In `includes/submissions-page.php`, `apply()`, before the final `return '';`:

```php
        if ($do === 'resend') {
            $row = SubmissionRepository::find((int) ($ids[0] ?? 0));

            if (!$row || $row['fields'] === null) {
                return 'resend_failed';
            }

            $sent = SubmissionRecorder::send((int) $row['id'], (string) $row['form_key'], SubmissionData::toPlaceholders($row['fields']));

            return $sent ? 'resent' : 'resend_failed';
        }
```

In `renderDetail()`, replace

```php
        $buttons = [
            'processed' => __('Mark as processed', 'lcmt-dev-mailer'),
```

with

```php
        $buttons = [];

        if ((int) $row['mail_sent'] !== 1 && $row['fields'] !== null) {
            $buttons['resend'] = __('Send the email again', 'lcmt-dev-mailer');
        }

        $buttons += [
            'processed' => __('Mark as processed', 'lcmt-dev-mailer'),
```

- [ ] **Step 6: CSV export**

In `includes/submissions-page.php` add the constant after `ACTION`:

```php
    public const EXPORT_ACTION = 'lcmt_mailer_export';
```

and the handler after `handleSingle()`:

```php
    /**
     * admin-post.php handler: the filtered list as a CSV file Excel opens
     * with accents and columns right (UTF-8 BOM, semicolons).
     */
    public static function handleExport(): void
    {
        if (!current_user_can(self::capability())) {
            wp_die(esc_html__('You are not allowed to do this.', 'lcmt-dev-mailer'), 403);
        }

        check_admin_referer(self::EXPORT_ACTION);

        $rows = SubmissionRepository::search(self::filtersFromRequest());

        foreach ($rows as &$row) {
            $row['created_at'] = get_date_from_gmt((string) $row['created_at'], 'Y-m-d H:i:s');
        }
        unset($row);

        nocache_headers();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="lcmt-messages-' . gmdate('Y-m-d') . '.csv"');

        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF");

        foreach (SubmissionCsv::table($rows) as $line) {
            fputcsv($out, $line, ';');
        }

        fclose($out);
        exit;
    }

    public static function exportUrl(): string
    {
        return wp_nonce_url(
            add_query_arg(['action' => self::EXPORT_ACTION] + array_filter(self::filtersFromRequest()), admin_url('admin-post.php')),
            self::EXPORT_ACTION
        );
    }
```

In `renderList()`, replace

```php
        echo '<h1 class="wp-heading-inline">' . esc_html__('Received messages', 'lcmt-dev-mailer') . '</h1>';
```

with

```php
        echo '<h1 class="wp-heading-inline">' . esc_html__('Received messages', 'lcmt-dev-mailer') . '</h1>';
        echo ' <a href="' . esc_url(self::exportUrl()) . '" class="page-title-action">' . esc_html__('Export CSV', 'lcmt-dev-mailer') . '</a>';
```

In `lcmt-dev-mailer.php`, add the require after `submissions-page.php`:

```php
require_once LCMT_MAILER_PATH . 'includes/submission-csv.php';
```

and after the `SubmissionsPage::ACTION` hook:

```php
add_action('admin_post_' . LcmtDevMailer\SubmissionsPage::EXPORT_ACTION, ['LcmtDevMailer\\SubmissionsPage', 'handleExport']);
```

- [ ] **Step 7: Verify on the local site**

Rsync.
1. On the failed row from Task 5, "Send the email again" appears; click it: notice "Email sent.", the row shows "Sent", `mail_error` is NULL in the database. With the `pre_wp_mail` failure filter back on, clicking gives "The email could not be sent again" and the new error. Remove the filter.
2. Send the form with the message `=HYPERLINK("http://example.com","x")`. Filter the list on one form, click "Export CSV": the file only holds that form's rows, opens in Excel/Numbers with accents right, one column per field, and the formula shows as text starting with `'`.

- [ ] **Step 8: Commit**

```bash
git add includes/submission-csv.php tests/SubmissionCsvTest.php tests/bootstrap.php includes/submissions-page.php lcmt-dev-mailer.php
git commit -m "✨ Resend a failed email and export received messages as CSV

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 9: Retention settings, daily purge

**Files:**
- Create: `includes/retention.php`, `includes/submission-settings.php`
- Modify: `lcmt-dev-mailer.php`, `tests/bootstrap.php`
- Test: `tests/RetentionTest.php`

**Interfaces:**
- Consumes: `SubmissionRepository::anonymizeBefore()/deleteBefore()/deleteAnonymizedBefore()`, `Attribution::OPTION_WITHOUT_CONSENT`.
- Produces:
  - `Retention::CRON_HOOK` = `'lcmt_mailer_purge_submissions'`, `Retention::DEFAULT_DAYS` = `1095`, `Retention::MAX_DAYS` = `3650`, `Retention::BATCH` = `500`
  - `Retention::clampDays(mixed $value, int $min, int $default): int`
  - `Retention::cutoff(int $days, int $now): ?string` (UTC `Y-m-d H:i:s`, null when `$days <= 0`)
  - `Retention::run(): array{anonymized: int, deleted: int}`, `Retention::schedule(): void`, `Retention::unschedule(): void`
  - `SubmissionSettings::retentionDays(): int`, `retentionAction(): string` (`anonymize`|`delete`), `statsRetentionDays(): int` (0 = unlimited)
  - admin-post action `lcmt_mailer_purge_now`

- [ ] **Step 1: Write the failing test**

`tests/RetentionTest.php`:

```php
<?php

use LcmtDevMailer\Retention;
use PHPUnit\Framework\TestCase;

class RetentionTest extends TestCase
{
    public function testKeepsAValidNumberOfDays(): void
    {
        $this->assertSame(365, Retention::clampDays('365', 1, Retention::DEFAULT_DAYS));
        $this->assertSame(1, Retention::clampDays(1, 1, Retention::DEFAULT_DAYS));
    }

    public function testFallsBackToTheDefaultOnInputThatWouldPurgeEverything(): void
    {
        foreach (['0', '-5', 'abc', '', null, ['1']] as $value) {
            $this->assertSame(Retention::DEFAULT_DAYS, Retention::clampDays($value, 1, Retention::DEFAULT_DAYS), var_export($value, true));
        }
    }

    public function testAllowsZeroWhenZeroMeansForever(): void
    {
        $this->assertSame(0, Retention::clampDays('0', 0, 0));
        $this->assertSame(0, Retention::clampDays('-3', 0, 0));
    }

    public function testCapsAbsurdlyLongPeriods(): void
    {
        $this->assertSame(Retention::MAX_DAYS, Retention::clampDays('99999', 1, Retention::DEFAULT_DAYS));
    }

    public function testComputesTheCutoffInUtc(): void
    {
        $now = gmmktime(12, 0, 0, 9, 29, 2026);

        $this->assertSame('2026-09-19 12:00:00', Retention::cutoff(10, $now));
        $this->assertNull(Retention::cutoff(0, $now));
    }
}
```

Add to `tests/bootstrap.php`:

```php
require_once dirname(__DIR__) . '/includes/retention.php';
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `phpunit --filter RetentionTest`
Expected: FAIL — missing file.

- [ ] **Step 3: Write Retention**

`includes/retention.php`:

```php
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
```

- [ ] **Step 4: Run the tests to verify they pass**

Run: `phpunit`
Expected: all PASS.

- [ ] **Step 5: Write the settings screen**

`includes/submission-settings.php`:

```php
<?php

namespace LcmtDevMailer;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Email templates → Data retention: how long received messages are kept.
 */
class SubmissionSettings
{
    public const PAGE_SLUG = 'lcmt-mailer-retention';
    public const GROUP = 'lcmt_mailer_retention';
    public const OPTION_DAYS = 'lcmt_mailer_retention_days';
    public const OPTION_ACTION = 'lcmt_mailer_retention_action';
    public const OPTION_STATS_DAYS = 'lcmt_mailer_stats_retention_days';
    public const PURGE_ACTION = 'lcmt_mailer_purge_now';

    public static function retentionDays(): int
    {
        return Retention::clampDays(get_option(self::OPTION_DAYS, Retention::DEFAULT_DAYS), 1, Retention::DEFAULT_DAYS);
    }

    public static function retentionAction(): string
    {
        return get_option(self::OPTION_ACTION, 'anonymize') === 'delete' ? 'delete' : 'anonymize';
    }

    public static function statsRetentionDays(): int
    {
        return Retention::clampDays(get_option(self::OPTION_STATS_DAYS, 0), 0, 0);
    }

    public static function addSubmenu(): void
    {
        add_submenu_page(
            'edit.php?post_type=' . PostType::SLUG,
            __('Data retention', 'lcmt-dev-mailer'),
            __('Data retention', 'lcmt-dev-mailer'),
            'manage_options',
            self::PAGE_SLUG,
            [self::class, 'renderPage']
        );
    }

    public static function registerSettings(): void
    {
        register_setting(self::GROUP, self::OPTION_DAYS, [
            'type'              => 'integer',
            'sanitize_callback' => static fn($value) => Retention::clampDays($value, 1, Retention::DEFAULT_DAYS),
            'default'           => Retention::DEFAULT_DAYS,
        ]);

        register_setting(self::GROUP, self::OPTION_ACTION, [
            'type'              => 'string',
            'sanitize_callback' => static fn($value) => $value === 'delete' ? 'delete' : 'anonymize',
            'default'           => 'anonymize',
        ]);

        register_setting(self::GROUP, self::OPTION_STATS_DAYS, [
            'type'              => 'integer',
            'sanitize_callback' => static fn($value) => Retention::clampDays($value, 0, 0),
            'default'           => 0,
        ]);

        // options.php sends null for an unchecked box.
        register_setting(self::GROUP, Attribution::OPTION_WITHOUT_CONSENT, [
            'type'              => 'string',
            'sanitize_callback' => static fn($value) => $value ? '1' : '0',
            'default'           => '1',
        ]);
    }

    public static function handlePurgeNow(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You are not allowed to do this.', 'lcmt-dev-mailer'), 403);
        }

        check_admin_referer(self::PURGE_ACTION);

        $done = Retention::run();

        wp_safe_redirect(add_query_arg([
            'post_type'  => PostType::SLUG,
            'page'       => self::PAGE_SLUG,
            'anonymized' => $done['anonymized'],
            'deleted'    => $done['deleted'],
        ], admin_url('edit.php')));
        exit;
    }

    public static function renderPage(): void
    {
        $days      = self::retentionDays();
        $action    = self::retentionAction();
        $statsDays = self::statsRetentionDays();
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Data retention', 'lcmt-dev-mailer'); ?></h1>

            <?php if (isset($_GET['anonymized'])): ?>
                <div class="notice notice-success is-dismissible"><p>
                    <?php
                    printf(
                        /* translators: 1: number of anonymized messages, 2: number of deleted messages */
                        esc_html__('Purge done: %1$d messages anonymized, %2$d deleted.', 'lcmt-dev-mailer'),
                        absint($_GET['anonymized']),
                        absint($_GET['deleted'] ?? 0)
                    );
                    ?>
                </p></div>
            <?php endif; ?>

            <form method="post" action="options.php">
                <?php settings_fields(self::GROUP); ?>

                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="lcmt-retention-days"><?php esc_html_e('Keep personal data for', 'lcmt-dev-mailer'); ?></label></th>
                        <td>
                            <input type="number" min="1" max="<?= (int) Retention::MAX_DAYS ?>" id="lcmt-retention-days"
                                   name="<?= esc_attr(self::OPTION_DAYS) ?>" value="<?= esc_attr((string) $days) ?>" class="small-text" />
                            <?php esc_html_e('days after the message was sent', 'lcmt-dev-mailer'); ?>
                            <p class="description">
                                <?php esc_html_e('The GDPR sets no fixed period: data may be kept as long as its purpose needs it. For prospects, the CNIL accepts at most 3 years (1095 days) after the last contact. State this period in your privacy policy.', 'lcmt-dev-mailer'); ?>
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><?php esc_html_e('When the period ends', 'lcmt-dev-mailer'); ?></th>
                        <td>
                            <fieldset>
                                <label><input type="radio" name="<?= esc_attr(self::OPTION_ACTION) ?>" value="anonymize" <?php checked($action, 'anonymize'); ?> />
                                    <?php esc_html_e('Anonymize: erase what the visitor typed, keep the date, form, page and source for statistics', 'lcmt-dev-mailer'); ?></label><br>
                                <label><input type="radio" name="<?= esc_attr(self::OPTION_ACTION) ?>" value="delete" <?php checked($action, 'delete'); ?> />
                                    <?php esc_html_e('Delete the whole message', 'lcmt-dev-mailer'); ?></label>
                            </fieldset>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><label for="lcmt-stats-days"><?php esc_html_e('Keep anonymized statistics for', 'lcmt-dev-mailer'); ?></label></th>
                        <td>
                            <input type="number" min="0" max="<?= (int) Retention::MAX_DAYS ?>" id="lcmt-stats-days"
                                   name="<?= esc_attr(self::OPTION_STATS_DAYS) ?>" value="<?= esc_attr((string) $statsDays) ?>" class="small-text" />
                            <?php esc_html_e('days', 'lcmt-dev-mailer'); ?>
                            <p class="description"><?php esc_html_e('0 keeps them forever: once anonymized, they are no longer personal data.', 'lcmt-dev-mailer'); ?></p>
                        </td>
                    </tr>
                </table>

                <details style="margin: 1em 0;">
                    <summary style="cursor: pointer; font-weight: 600;"><?php esc_html_e('Advanced settings', 'lcmt-dev-mailer'); ?></summary>

                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php esc_html_e('Visit origin without a consent tool', 'lcmt-dev-mailer'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="<?= esc_attr(Attribution::OPTION_WITHOUT_CONSENT) ?>" value="1" <?php checked(Attribution::storesWithoutConsent()); ?> />
                                    <?php esc_html_e('Remember the landing page and campaign in the visitor\'s browser when no consent tool is installed', 'lcmt-dev-mailer'); ?>
                                </label>
                                <p class="description">
                                    <?php esc_html_e('With a consent tool compatible with WP Consent API, this is only done after the visitor accepts statistics, whatever this setting. Without one, storing it needs consent under the ePrivacy rules: untick this to only record the page the form was sent from.', 'lcmt-dev-mailer'); ?>
                                </p>
                            </td>
                        </tr>
                    </table>
                </details>

                <?php submit_button(); ?>
            </form>

            <hr>

            <h2><?php esc_html_e('Purge now', 'lcmt-dev-mailer'); ?></h2>
            <p><?php esc_html_e('The purge runs once a day, when the site gets visits. Run it now to apply new settings at once.', 'lcmt-dev-mailer'); ?></p>
            <form method="post" action="<?= esc_url(admin_url('admin-post.php')) ?>">
                <input type="hidden" name="action" value="<?= esc_attr(self::PURGE_ACTION) ?>" />
                <?php wp_nonce_field(self::PURGE_ACTION); ?>
                <?php submit_button(__('Purge now', 'lcmt-dev-mailer'), 'secondary', 'submit', false); ?>
            </form>
        </div>
        <?php
    }
}
```

- [ ] **Step 6: Wire the bootstrap**

In `lcmt-dev-mailer.php`, add the requires after `submission-csv.php`:

```php
require_once LCMT_MAILER_PATH . 'includes/retention.php';
require_once LCMT_MAILER_PATH . 'includes/submission-settings.php';
```

Under `// ── Received messages ──`:

```php
add_action('init', ['LcmtDevMailer\\Retention', 'schedule']);
add_action(LcmtDevMailer\Retention::CRON_HOOK, ['LcmtDevMailer\\Retention', 'run']);
register_deactivation_hook(__FILE__, ['LcmtDevMailer\\Retention', 'unschedule']);
```

Under `// ── Admin UI ──`, after the `CaptchaSettings` lines:

```php
add_action('admin_menu', ['LcmtDevMailer\\SubmissionSettings', 'addSubmenu']);
add_action('admin_init', ['LcmtDevMailer\\SubmissionSettings', 'registerSettings']);
add_action('admin_post_' . LcmtDevMailer\SubmissionSettings::PURGE_ACTION, ['LcmtDevMailer\\SubmissionSettings', 'handlePurgeNow']);
```

- [ ] **Step 7: Verify on the local site**

Rsync, then:
1. `wp cron event list | grep lcmt_mailer_purge_submissions` → one daily event.
2. The Data retention screen shows 1095 / Anonymize / 0 and the advanced setting ticked. Save `0` or `-5` in the first field: it comes back as 1095. Untick the advanced box, save, `wp option get lcmt_mailer_attribution_without_consent` → `0`; tick it back.
3. Age a row: `wp db query "UPDATE $(wp db prefix)lcmt_mailer_submissions SET created_at = '2020-01-01 00:00:00' WHERE id = <id>"`. Click "Purge now": notice "1 messages anonymized, 0 deleted"; the row has `fields` and `mail_error` NULL, `anonymized_at` set, context columns intact; the list shows "Anonymized" and the detail "Personal data anonymized on …".
4. Set statistics to 30 days, Purge now: the anonymized row is deleted. Set it back to 0.
5. Switch to "Delete", age another row, Purge now: it is gone. Switch back to "Anonymize".

- [ ] **Step 8: Commit**

```bash
git add includes/retention.php includes/submission-settings.php tests/RetentionTest.php tests/bootstrap.php lcmt-dev-mailer.php
git commit -m "🔒 Anonymize or delete received messages after a retention period

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 10: WordPress privacy tools

**Files:**
- Create: `includes/privacy.php`
- Modify: `lcmt-dev-mailer.php`

**Interfaces:**
- Consumes: `SubmissionRepository::findContaining()/anonymize()`, `SubmissionData::containsEmail()`, `SubmissionSettings::retentionDays()/retentionAction()`.
- Produces: `Privacy::registerExporter(array): array`, `Privacy::registerEraser(array): array`, `Privacy::export(string $email, int $page = 1): array`, `Privacy::erase(string $email, int $page = 1): array`, `Privacy::addPolicyContent(): void`, `Privacy::retentionShortcode(): string` (shortcode `[lcmt-retention-days]`).

- [ ] **Step 1: Write the class**

`includes/privacy.php`:

```php
<?php

namespace LcmtDevMailer;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Hooks received messages into Tools → Export / Erase Personal Data and the
 * privacy policy guide.
 */
class Privacy
{
    private const ID = 'lcmt-dev-mailer';

    public static function registerExporter(array $exporters): array
    {
        $exporters[self::ID] = [
            'exporter_friendly_name' => __('Received messages', 'lcmt-dev-mailer'),
            'callback'               => [self::class, 'export'],
        ];

        return $exporters;
    }

    public static function registerEraser(array $erasers): array
    {
        $erasers[self::ID] = [
            'eraser_friendly_name' => __('Received messages', 'lcmt-dev-mailer'),
            'callback'             => [self::class, 'erase'],
        ];

        return $erasers;
    }

    /**
     * One person sends a handful of messages, so everything is done in one page.
     */
    public static function export(string $email, int $page = 1): array
    {
        $data = [];

        foreach (self::matching($email) as $row) {
            $items = [
                ['name' => __('Date', 'lcmt-dev-mailer'), 'value' => get_date_from_gmt((string) $row['created_at'])],
                ['name' => __('Form', 'lcmt-dev-mailer'), 'value' => $row['form_key']],
                ['name' => __('Sent from', 'lcmt-dev-mailer'), 'value' => $row['page_path']],
            ];

            foreach ($row['fields'] as $field) {
                $items[] = ['name' => $field['name'], 'value' => $field['value']];
            }

            $data[] = [
                'group_id'    => self::ID,
                'group_label' => __('Received messages', 'lcmt-dev-mailer'),
                'item_id'     => 'lcmt-submission-' . $row['id'],
                'data'        => $items,
            ];
        }

        return ['data' => $data, 'done' => true];
    }

    /**
     * Anonymizes rather than deletes, so statistics keep the message.
     */
    public static function erase(string $email, int $page = 1): array
    {
        $ids = array_map(static fn(array $row) => (int) $row['id'], self::matching($email));

        SubmissionRepository::anonymize($ids);

        return [
            'items_removed'  => count($ids) > 0,
            'items_retained' => false,
            'messages'       => [],
            'done'           => true,
        ];
    }

    public static function addPolicyContent(): void
    {
        if (!function_exists('wp_add_privacy_policy_content')) {
            return;
        }

        $end = SubmissionSettings::retentionAction() === 'delete'
            ? __('they are then deleted', 'lcmt-dev-mailer')
            : __('they are then anonymized: only the date, the form, the page and the origin of the visit are kept, for statistics', 'lcmt-dev-mailer');

        $text = sprintf(
            /* translators: 1: number of days, 2: what happens after */
            __('The messages you send through our forms are saved on this site for %1$d days so we can answer and follow up on your request; %2$s. We record the page you sent it from and how you reached the site (search engine, ad, other site, campaign), never your IP address.', 'lcmt-dev-mailer'),
            SubmissionSettings::retentionDays(),
            $end
        );

        wp_add_privacy_policy_content('LCMT Mailer', wp_kses_post(wpautop($text)));
    }

    /**
     * [lcmt-retention-days] prints the retention period, for privacy notices
     * that follow the setting.
     */
    public static function retentionShortcode(): string
    {
        return (string) SubmissionSettings::retentionDays();
    }

    /**
     * Rows holding personal data where one value is exactly this address.
     */
    private static function matching(string $email): array
    {
        $email = trim($email);

        if ($email === '') {
            return [];
        }

        return array_values(array_filter(
            SubmissionRepository::findContaining($email),
            static fn(array $row) => SubmissionData::containsEmail($row['fields'] ?? [], $email)
        ));
    }
}
```

Note: `findContaining` uses `LIKE`, which is case-insensitive with WordPress' default `utf8mb4_unicode_ci` collation, then `containsEmail` keeps only exact matches.

- [ ] **Step 2: Wire the bootstrap**

In `lcmt-dev-mailer.php`, add the require after `submission-settings.php`:

```php
require_once LCMT_MAILER_PATH . 'includes/privacy.php';
```

Under `// ── Received messages ──`:

```php
add_filter('wp_privacy_personal_data_exporters', ['LcmtDevMailer\\Privacy', 'registerExporter']);
add_filter('wp_privacy_personal_data_erasers', ['LcmtDevMailer\\Privacy', 'registerEraser']);
add_action('admin_init', ['LcmtDevMailer\\Privacy', 'addPolicyContent']);
add_shortcode('lcmt-retention-days', ['LcmtDevMailer\\Privacy', 'retentionShortcode']);
```

- [ ] **Step 3: Verify on the local site**

Rsync.
1. Tools → Export Personal Data: request for the email used in the test submissions, confirm it from the admin ("Mark as confirmed" in the row actions), download: a "Received messages" group lists each message with its fields.
2. Tools → Erase Personal Data for that email: the matching rows become anonymized, a row sent with another email is untouched.
3. Settings → Privacy → policy guide: an "LCMT Mailer" section shows the text with 1095 days.
4. A page with `[lcmt-retention-days]` prints `1095`.

- [ ] **Step 4: Commit**

```bash
git add includes/privacy.php lcmt-dev-mailer.php
git commit -m "🔒 Export and erase received messages from the WordPress privacy tools

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 11: Failed email banner and dashboard widget

**Files:**
- Create: `includes/failure-notice.php`
- Modify: `lcmt-dev-mailer.php`

**Interfaces:**
- Consumes: `SubmissionRepository::countUnresolvedFailures()/unresolvedFailures()`, `SubmissionsPage::capability()/url()`.
- Produces: `FailureNotice::OPTION_DISMISSED_AT` = `'lcmt_mailer_failures_dismissed_at'`, `FailureNotice::banner()`, `FailureNotice::addDashboardWidget()`, `FailureNotice::renderWidget()`, `FailureNotice::handleDismiss()`, admin-post action `lcmt_mailer_dismiss_failures`.

- [ ] **Step 1: Write the class**

`includes/failure-notice.php`:

```php
<?php

namespace LcmtDevMailer;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Warns administrators about form emails that could not be sent, with the
 * reason, until each one is resent, marked processed or spam, or dismissed.
 */
class FailureNotice
{
    public const OPTION_DISMISSED_AT = 'lcmt_mailer_failures_dismissed_at';
    public const DISMISS_ACTION = 'lcmt_mailer_dismiss_failures';

    public static function banner(): void
    {
        if (!current_user_can(SubmissionsPage::capability())) {
            return;
        }

        $since = self::since();
        $count = SubmissionRepository::countUnresolvedFailures($since);

        if (!$count) {
            return;
        }

        $latest = SubmissionRepository::unresolvedFailures($since, 1)[0] ?? [];

        echo '<div class="notice notice-error"><p>';
        echo '<strong>' . esc_html(sprintf(
            /* translators: %d: number of messages */
            _n('%d form message could not be emailed.', '%d form messages could not be emailed.', $count, 'lcmt-dev-mailer'),
            $count
        )) . '</strong> ';
        echo esc_html__('They are saved: you can read them and send them again.', 'lcmt-dev-mailer');
        echo '</p>';

        if (!empty($latest['mail_error'])) {
            echo '<p>' . esc_html__('Last error:', 'lcmt-dev-mailer') . ' <code>' . esc_html($latest['mail_error']) . '</code></p>';
        }

        echo '<p>';
        echo '<a class="button button-primary" href="' . esc_url(SubmissionsPage::url(['failed' => 1])) . '">' . esc_html__('See the messages', 'lcmt-dev-mailer') . '</a> ';
        echo '<a class="button" href="' . esc_url(self::dismissUrl()) . '">' . esc_html__('Dismiss', 'lcmt-dev-mailer') . '</a>';
        echo '</p></div>';
    }

    public static function addDashboardWidget(): void
    {
        if (!current_user_can(SubmissionsPage::capability()) || !SubmissionRepository::countUnresolvedFailures(self::since())) {
            return;
        }

        wp_add_dashboard_widget('lcmt_mailer_failures', __('Form emails that failed', 'lcmt-dev-mailer'), [self::class, 'renderWidget']);
    }

    public static function renderWidget(): void
    {
        $format = get_option('date_format') . ' ' . get_option('time_format');

        echo '<ul>';

        foreach (SubmissionRepository::unresolvedFailures(self::since(), 5) as $failure) {
            printf(
                '<li><a href="%s">%s</a> · <code>%s</code><br><span style="color: #d63638;">%s</span></li>',
                esc_url(SubmissionsPage::url(['submission' => $failure['id']])),
                esc_html(get_date_from_gmt((string) $failure['created_at'], $format)),
                esc_html($failure['form_key']),
                esc_html($failure['mail_error'] ?: __('The request stopped before the email was sent.', 'lcmt-dev-mailer'))
            );
        }

        echo '</ul>';
        echo '<p><a href="' . esc_url(SubmissionsPage::url(['failed' => 1])) . '">' . esc_html__('All failed emails', 'lcmt-dev-mailer') . '</a></p>';
    }

    /**
     * Hides the failures seen so far; a new failure brings the banner back.
     */
    public static function handleDismiss(): void
    {
        if (!current_user_can(SubmissionsPage::capability())) {
            wp_die(esc_html__('You are not allowed to do this.', 'lcmt-dev-mailer'), 403);
        }

        check_admin_referer(self::DISMISS_ACTION);

        update_option(self::OPTION_DISMISSED_AT, current_time('mysql', true), false);

        wp_safe_redirect(wp_get_referer() ?: admin_url());
        exit;
    }

    private static function since(): string
    {
        return (string) get_option(self::OPTION_DISMISSED_AT, '1970-01-01 00:00:00');
    }

    private static function dismissUrl(): string
    {
        return wp_nonce_url(add_query_arg('action', self::DISMISS_ACTION, admin_url('admin-post.php')), self::DISMISS_ACTION);
    }
}
```

- [ ] **Step 2: Wire the bootstrap**

In `lcmt-dev-mailer.php`, add the require after `privacy.php`:

```php
require_once LCMT_MAILER_PATH . 'includes/failure-notice.php';
```

Under `// ── Admin UI ──`:

```php
add_action('admin_notices', ['LcmtDevMailer\\FailureNotice', 'banner']);
add_action('wp_dashboard_setup', ['LcmtDevMailer\\FailureNotice', 'addDashboardWidget']);
add_action('admin_post_' . LcmtDevMailer\FailureNotice::DISMISS_ACTION, ['LcmtDevMailer\\FailureNotice', 'handleDismiss']);
```

- [ ] **Step 3: Verify on the local site**

Rsync. With the `pre_wp_mail` failure filter from Task 5 on, send the form twice.
1. Every admin page shows the red banner "2 form messages could not be emailed." with `SMTP connect() failed.`; the dashboard shows the widget with both.
2. Mark one as processed: banner says 1. Remove the filter, resend the other from its detail screen: banner and widget disappear.
3. Filter back on, send once, click "Dismiss": banner gone. Send again: banner back with 1. Remove the filter.
4. As an editor: no banner, no widget.

- [ ] **Step 4: Commit**

```bash
git add includes/failure-notice.php lcmt-dev-mailer.php
git commit -m "✨ Warn in the admin when a form email could not be sent, with the reason

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 12: Statistics screen

**Files:**
- Create: `includes/stats-page.php`
- Modify: `lcmt-dev-mailer.php`

**Interfaces:**
- Consumes: `SubmissionRepository::totals()/countByMonth()/countBy()`, `Retention::cutoff()`, `ChannelClassifier::label()`, `SubmissionsPage::capability()`.
- Produces: `StatsPage::PAGE_SLUG` = `'lcmt-mailer-stats'`, `StatsPage::addSubmenu()`, `StatsPage::render()`.

- [ ] **Step 1: Write the page**

`includes/stats-page.php`:

```php
<?php

namespace LcmtDevMailer;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Email templates → Statistics: where the received messages come from.
 * Counts include anonymized messages and leave spam out.
 */
class StatsPage
{
    public const PAGE_SLUG = 'lcmt-mailer-stats';

    /**
     * Period key => days (0 = everything).
     */
    private const PERIODS = ['30' => 30, '90' => 90, '365' => 365, 'all' => 0];

    public static function addSubmenu(): void
    {
        add_submenu_page(
            'edit.php?post_type=' . PostType::SLUG,
            __('Statistics', 'lcmt-dev-mailer'),
            __('Statistics', 'lcmt-dev-mailer'),
            SubmissionsPage::capability(),
            self::PAGE_SLUG,
            [self::class, 'render']
        );
    }

    public static function render(): void
    {
        $period = sanitize_key($_GET['period'] ?? '90');
        $period = isset(self::PERIODS[$period]) ? $period : '90';
        $since  = Retention::cutoff(self::PERIODS[$period], time()) ?? '1970-01-01 00:00:00';
        $totals = SubmissionRepository::totals($since);

        $labels = [
            '30'  => __('Last 30 days', 'lcmt-dev-mailer'),
            '90'  => __('Last 90 days', 'lcmt-dev-mailer'),
            '365' => __('Last 12 months', 'lcmt-dev-mailer'),
            'all' => __('Everything', 'lcmt-dev-mailer'),
        ];

        echo '<div class="wrap lcmt-stats">';
        echo '<h1>' . esc_html__('Statistics', 'lcmt-dev-mailer') . '</h1>';

        echo '<ul class="subsubsub">';
        $links = [];
        foreach ($labels as $key => $label) {
            $url     = add_query_arg(['post_type' => PostType::SLUG, 'page' => self::PAGE_SLUG, 'period' => $key], admin_url('edit.php'));
            $links[] = '<li><a href="' . esc_url($url) . '"' . ($key === $period ? ' class="current" aria-current="page"' : '') . '>' . esc_html($label) . '</a>';
        }
        echo implode(' | </li>', $links) . '</li></ul><br class="clear">';

        echo '<div class="lcmt-stats__tiles">';
        self::tile(__('Messages', 'lcmt-dev-mailer'), number_format_i18n($totals['total']));
        self::tile(__('Emails not sent', 'lcmt-dev-mailer'), number_format_i18n($totals['failed']));
        self::tile(
            __('Average time on the form', 'lcmt-dev-mailer'),
            $totals['avg_seconds'] === null ? '–' : human_time_diff(0, $totals['avg_seconds'])
        );
        echo '</div>';

        echo '<div class="lcmt-stats__grid">';
        self::bars(__('By month', 'lcmt-dev-mailer'), SubmissionRepository::countByMonth($since), static fn(string $month) => date_i18n('F Y', strtotime($month . '-01')));
        self::bars(__('By source', 'lcmt-dev-mailer'), SubmissionRepository::countBy('channel', $since), [ChannelClassifier::class, 'label']);
        self::bars(__('By campaign', 'lcmt-dev-mailer'), SubmissionRepository::countBy('utm_campaign', $since));
        self::bars(__('By form', 'lcmt-dev-mailer'), SubmissionRepository::countBy('form_key', $since));
        self::bars(__('Sent from', 'lcmt-dev-mailer'), SubmissionRepository::countBy('page_path', $since));
        self::bars(__('Landing page', 'lcmt-dev-mailer'), SubmissionRepository::countBy('landing_path', $since));
        self::bars(__('Referring site', 'lcmt-dev-mailer'), SubmissionRepository::countBy('referrer_host', $since));
        self::bars(__('Device', 'lcmt-dev-mailer'), SubmissionRepository::countBy('device', $since));
        echo '</div>';

        ?>
        <style>
            .lcmt-stats__tiles { display: flex; gap: 16px; margin: 16px 0; flex-wrap: wrap; }
            .lcmt-stats__tile { background: #fff; border: 1px solid #c3c4c7; padding: 12px 16px; min-width: 180px; }
            .lcmt-stats__tile strong { display: block; font-size: 24px; line-height: 1.3; }
            .lcmt-stats__grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(420px, 1fr)); gap: 16px; }
            .lcmt-stats__bar { background: #2271b1; height: 8px; border-radius: 4px; min-width: 2px; }
            .lcmt-stats td.num { width: 60px; text-align: right; font-variant-numeric: tabular-nums; }
            .lcmt-stats td.bar { width: 40%; }
        </style>
        <?php

        echo '</div>';
    }

    private static function tile(string $label, string $value): void
    {
        echo '<div class="lcmt-stats__tile"><span>' . esc_html($label) . '</span><strong>' . esc_html($value) . '</strong></div>';
    }

    /**
     * @param list<array{label: string, total: int}> $rows
     */
    private static function bars(string $title, array $rows, ?callable $label = null): void
    {
        echo '<div class="postbox"><div class="inside">';
        echo '<h2 style="padding: 0;">' . esc_html($title) . '</h2>';

        if (!$rows) {
            echo '<p>' . esc_html__('No data for this period.', 'lcmt-dev-mailer') . '</p></div></div>';
            return;
        }

        $max = max(array_column($rows, 'total')) ?: 1;

        echo '<table class="widefat striped"><tbody>';

        foreach ($rows as $row) {
            $text = $row['label'] === '' ? __('(not set)', 'lcmt-dev-mailer') : ($label ? $label($row['label']) : $row['label']);

            printf(
                '<tr><td>%s</td><td class="bar"><div class="lcmt-stats__bar" style="width: %.1f%%;"></div></td><td class="num">%s</td></tr>',
                esc_html((string) $text),
                $row['total'] / $max * 100,
                esc_html(number_format_i18n($row['total']))
            );
        }

        echo '</tbody></table></div></div>';
    }
}
```

- [ ] **Step 2: Wire the bootstrap**

In `lcmt-dev-mailer.php`, add the require after `failure-notice.php`:

```php
require_once LCMT_MAILER_PATH . 'includes/stats-page.php';
```

Under `// ── Admin UI ──`, right after the `SubmissionsPage::addSubmenu` line:

```php
add_action('admin_menu', ['LcmtDevMailer\\StatsPage', 'addSubmenu']);
```

- [ ] **Step 3: Verify on the local site**

Rsync. Seed a spread of rows to see the bars:

```bash
P=$(wp db prefix)
for c in google_ads google_ads organic_search direct direct direct social referral; do
  wp db query "INSERT INTO ${P}lcmt_mailer_submissions (form_key, created_at, status, mail_sent, channel, page_path, landing_path, device) VALUES ('contact', UTC_TIMESTAMP() - INTERVAL FLOOR(RAND()*200) DAY, 'read', 1, '$c', '/contact/', '/', 'desktop')"
done
```

1. Statistics shows three tiles and eight boxes; "By source" lists Google Ads 2 (plus earlier rows), Direct 3, etc. with bars proportional to the top value; empty labels read "(not set)".
2. Switching periods changes the totals; spam rows are not counted.
3. The screen has no horizontal scroll at 1280 px wide.

Delete the seeded rows: `wp db query "DELETE FROM ${P}lcmt_mailer_submissions WHERE fields IS NULL AND anonymized_at IS NULL"`.

- [ ] **Step 4: Commit**

```bash
git add includes/stats-page.php lcmt-dev-mailer.php
git commit -m "✨ Show where received messages come from on a statistics screen

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 13: Docs, translations, release 2.2.0

**Files:**
- Modify: `.claude/architecture.md`, `.claude/hooks.md`, `.claude/frontend.md`, `.claude/build.md`, `CLAUDE.md`, `README.md`, `languages/lcmt-dev-mailer.pot`, `languages/lcmt-dev-mailer-fr_FR.po`, `languages/lcmt-dev-mailer-fr_FR.mo`, `lcmt-dev-mailer.php`, `package.json`

- [ ] **Step 1: Developer docs**

- `.claude/architecture.md`: add every new file of the File Structure table to the tree and a section per class (`SubmissionData`, `SubmissionContext`, `ChannelClassifier`, `SubmissionSchema`, `SubmissionRepository`, `SubmissionRecorder`, `Attribution`, `SubmissionsPage`, `SubmissionsListTable`, `SubmissionCsv`, `SubmissionSettings`, `Retention`, `Privacy`, `FailureNotice`, `StatsPage`), plus the data flow: *endpoint validates → `SubmissionRecorder::record()` inserts → `lcmt_mailer_before_send` → `SubmissionRecorder::send()` updates `mail_sent`/`mail_error` → `lcmt_mailer_after_send`*. Document the table columns and which ones anonymization clears.
- `.claude/hooks.md`: add filters `lcmt_mailer_submission_channel`, `lcmt_mailer_submissions_capability`, `lcmt_mailer_submission_actions` with an example each, and the `wp_mail_failed` listener.
- `.claude/frontend.md`: section "Attribution" — `attribution.js` on every page, `sessionStorage` key `lcmtMailerLanding`, consent rules (WP Consent API `statistics`, fallback option), and the `_context` object sent by `form-handler.js` (reserved field name `_context`).
- `.claude/build.md`: add `src/attribution.js` to the JS table, note that `src/lib/` holds shared modules not emitted on their own, and list the new tests.
- `CLAUDE.md`: under Detailed instructions, one line "Received messages: storage, retention and stats — see Architecture".
- `README.md`: a "Received messages" section for site owners: where to find them, per-form checkbox, retention defaults (1095 days then anonymize, stats forever) and the CNIL rationale, the consent behaviour, `[lcmt-retention-days]`, and "deleting the plugin deletes the messages".

- [ ] **Step 2: Translations**

```bash
cd /Volumes/Samsung_T5/Freelance/lcmt-dev-mailer
wp i18n make-pot . languages/lcmt-dev-mailer.pot --exclude=lib,node_modules,assets,tests,docs --domain=lcmt-dev-mailer
msgmerge --update --backup=none languages/lcmt-dev-mailer-fr_FR.po languages/lcmt-dev-mailer.pot
grep -c 'msgstr ""$' languages/lcmt-dev-mailer-fr_FR.po
```

Translate every new empty `msgstr` in `languages/lcmt-dev-mailer-fr_FR.po` into French (e.g. "Received messages" → "Messages reçus", "Data retention" → "Conservation des données", "Statistics" → "Statistiques", "Send the email again" → "Renvoyer l'e-mail", "Google Ads" stays "Google Ads", "Organic search" → "Recherche naturelle", "Direct" → "Accès direct"), remove `#, fuzzy` flags after checking them, fill both `msgstr[0]`/`msgstr[1]` of the `_n()` string, then:

```bash
msgfmt --check -o languages/lcmt-dev-mailer-fr_FR.mo languages/lcmt-dev-mailer-fr_FR.po
```

Expected: no error. Rsync and check the new screens read in French on the local site.

- [ ] **Step 3: Commit docs and translations**

```bash
git add .claude CLAUDE.md README.md languages
git commit -m "📝 Document received messages and translate them into French

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

- [ ] **Step 4: Full check**

```bash
phpunit
nvm use 20 && yarn build && git status --short assets/dist
for f in includes/*.php uninstall.php lcmt-dev-mailer.php; do php -l "$f" | grep -v 'No syntax errors'; done
```

Expected: all tests PASS, `assets/dist` unchanged, no syntax errors printed.

Run a last end-to-end pass on the local site (form sent with campaign → list → detail → CSV → stats → purge) with `WP_DEBUG` on and check `wp-content/debug.log` has no notice from the plugin.

- [ ] **Step 5: Version bump**

Set `Version: 2.2.0` in the `lcmt-dev-mailer.php` header and `"version": "2.2.0"` in `package.json`.

```bash
git add lcmt-dev-mailer.php package.json
git commit -m "🔖 Release 2.2.0

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

- [ ] **Step 6: Merge and release — ask Amaury first**

Merging to `main` and pushing the `v2.2.0` tag publishes the update to every site running the plugin. Only after Amaury confirms:

```bash
git checkout main && git merge --no-ff feature/received-messages
git tag v2.2.0 && git push && git push --tags
```
