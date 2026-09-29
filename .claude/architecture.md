# Architecture

## File structure

```
lcmt-dev-mailer/
├── lcmt-dev-mailer.php          # Bootstrap: defines constants, requires files, registers hooks
├── includes/
│   ├── post-type.php            # PostType — registers the `mail` CPT
│   ├── meta-fields.php          # MetaFields — native WP meta box (key, to, subject, content) + syntax reference panel
│   ├── field-parser.php         # FieldParser — parses [field* type] placeholders from content
│   ├── field-validator.php      # FieldValidator — sanitizes submitted values and checks them by type
│   ├── template-loader.php      # TemplateLoader — loads mail-base.php with theme override support
│   ├── mailer.php               # Mailer — core email engine (lookup by key, placeholder replacement, wp_mail)
│   ├── form-renderer.php        # FormRenderer — renders forms via shortcode or PHP, enqueues frontend JS
│   ├── form-endpoint.php        # FormEndpoint — REST API endpoint with auto-validation and sending
│   ├── admin-mail-sender.php    # AdminMailSender — admin sidebar (usage info, field table, test email, generate template)
│   ├── settings.php             # Settings — Email templates → Settings page (sender, logo, alert colors)
│   ├── captcha-settings.php     # CaptchaSettings — Email templates → Spam protection page (provider select + provider rows)
│   ├── captcha-provider.php     # CaptchaProvider — interface every spam protection implements
│   ├── captcha.php              # Captcha — resolves the selected provider and routes verify/widget/routes to it
│   ├── altcha.php               # Altcha — ALTCHA provider (challenge route, one-time proofs, auto-generated key)
│   ├── submission-data.php      # SubmissionData — shapes typed values for storage (snapshot) and back
│   ├── submission-context.php   # SubmissionContext — cleans the `_context` object sent by the browser
│   ├── channel-classifier.php   # ChannelClassifier — sorts a visit into a channel (Google Ads, organic search…)
│   ├── submission-schema.php    # SubmissionSchema — creates/upgrades the submissions table
│   ├── submission-repository.php# SubmissionRepository — every query on the submissions table
│   ├── submission-recorder.php  # SubmissionRecorder — saves a submission, then records the send result
│   ├── attribution.php          # Attribution — enqueues attribution.js on public pages
│   ├── submissions-page.php     # SubmissionsPage — Received messages screen (list, detail, actions, CSV)
│   ├── submissions-list-table.php # SubmissionsListTable — WP_List_Table of the messages
│   ├── submission-csv.php       # SubmissionCsv — turns submissions into spreadsheet rows
│   ├── submission-settings.php  # SubmissionSettings — Data retention settings page and "Purge now"
│   ├── retention.php            # Retention — daily purge (anonymize or delete)
│   ├── privacy.php              # Privacy — WordPress export/erase tools, policy text, [lcmt-retention-days]
│   ├── failure-notice.php       # FailureNotice — admin banner and dashboard widget for unsent emails
│   ├── stats-page.php           # StatsPage — Statistics screen
│   └── updater.php              # Updater — Plugin Update Checker wired to the GitHub releases
├── uninstall.php                # Drops the table and options when the plugin is deleted
├── tests/                       # unit/ JS / integration tests (see Build)
├── lib/
│   └── plugin-update-checker/   # Vendored YahnisElsts/plugin-update-checker v5.7 (do not edit)
├── templates/
│   └── mail-base.php            # Default HTML email template (overridable in theme)
├── assets/
│   ├── src/                     # JS source files (vanilla, no jQuery)
│   │   ├── admin-test-mail.js
│   │   ├── admin-generate-template.js
│   │   ├── attribution.js       # Remembers the landing page of the visit
│   │   ├── form-handler.js
│   │   └── lib/attribution.js   # Shared module (not built on its own)
│   └── dist/                    # Minified output (esbuild)
└── package.json                 # Build config (esbuild)
```

## Classes and responsibilities

### PostType
Registers the `mail` custom post type. Slug stored as `PostType::SLUG`.

### MetaFields
Native WordPress meta box with 4 fields:
- `_lcmt_mail_key` — unique kebab-case identifier
- `_lcmt_mail_to` — recipient(s), supports placeholders
- `_lcmt_mail_subject` — email subject, supports placeholders
- `_lcmt_mail_content` — email body (WYSIWYG), supports placeholders with type syntax

Falls back to old ACF meta keys (`to`, `subject`, `content`) for backwards compatibility.

Renders a collapsible syntax reference panel below the content editor.

### FieldParser
Parses `[name]`, `[name*]`, `[name type]`, `[name* type]` from strings.
- Returns array of `{name, required, type}`
- Merges duplicates: required wins, explicit type wins over default `text`
- Also generates TypeScript interfaces via `toTypeScript()`

### TemplateLoader
Loads the HTML email wrapper template.
- Theme override: `{theme}/lcmt-dev-mailer/mail-base.php`
- Fallback: `templates/mail-base.php`

### Mailer
Core email sending engine.
- `sendByKey($key, $placeholders)` — main public API
- `getPostByKey($key)` — queries mail CPT by `_lcmt_mail_key` meta
- `buildForm($post, $placeholders)` — replaces placeholders in to/subject/content
- Normalizes placeholders: bare keys get both `[field]` and `[field*]` forms
- Polylang translation support via `lcmt_mailer_user_language` filter
- Built-in placeholders: `[currentUserLink]`, `[currentUserEmail]`, filled only when the caller did not pass them (the resend passes them empty)

### FormRenderer
- `render($key)` / shortcode `[lcmt-form key="..."]`
- Loads `{theme}/forms/{key}.php`
- Wraps with `<form>` + `data-lcmt-endpoint` + `data-lcmt-nonce` (or injects into existing `<form>` tag)
- Enqueues `form-handler.js` on first render

### FormEndpoint
- Registers `POST /wp-json/lcmt-mailer/v1/forms/{key}`
- Auto-validates required fields from parsed content, and values by type through `FieldValidator`
- Sends email via `Mailer::sendByKey()`
- Fires `lcmt_mailer_before_send` and `lcmt_mailer_after_send` actions

### FieldValidator
Sanitizes a submitted value (`sanitize_text_field`, or `sanitize_textarea_field` for textareas) and checks it by type:
- `email` — `is_email()`
- `number` — `is_numeric()`
- `url` — valid URL with an `http` or `https` scheme
- `tel` — digits, an optional leading `+`, and spaces (non-breaking included), dots, dashes, slashes or brackets; 6 to 20 digits
- other types — not checked

### AdminMailSender
Admin sidebar metabox with:
- Usage info: shortcode, PHP snippet, REST endpoint
- Field reference table (name, type, required)
- Form file status with "Generate form template" button
- TypeScript interface preview
- Test email sender

### Captcha / CaptchaProvider
- The `lcmt_mailer_captcha` option holds the selected provider id, or `none`. Defaults to `altcha` until saved.
- Providers come from the `lcmt_mailer_captcha_providers` filter (`id => class`), each implementing `CaptchaProvider`.
- `Captcha::verify()` is called by FormEndpoint, `Captcha::widget()` by FormRenderer, `Captcha::registerRoutes()` on `rest_api_init`. A provider that is selected but not `isReady()` protects nothing.
- Each provider prints its own settings rows, tagged `data-captcha-provider="{id}"` so the Spam protection page (`CaptchaSettings`) only shows the selected one.

### Altcha
- HMAC key: `ALTCHA_HMAC_KEY` constant if defined, otherwise the `lcmt_mailer_altcha_key` option, generated on first use (not autoloaded).
- "Generate a new key" posts to `admin-post.php?action=lcmt_mailer_regenerate_altcha_key` (nonce + `manage_options`).
- A valid proof is spent: its challenge is stored in a `lcmt_altcha_used_{challenge}` transient until it expires, so it cannot be replayed.

### SubmissionData
Pure helpers around the stored `fields` JSON: `snapshot()` freezes each submitted value with its field name and type (password fields are never stored), `toPlaceholders()` rebuilds the placeholders for a resend, `containsEmail()` matches an exact value, `summary()` builds the line shown in lists. `PERSONAL_COLUMNS` (`fields`, `mail_error`) lists what anonymization clears.

### SubmissionContext
`fromRequest($raw, $siteHost)` turns the untrusted `_context` object into column values. Every value is checked against its expected shape and cut to its column size, anything else becomes empty. Only paths and hosts are kept, never query strings; the referrer is reduced to its host (without `www.`), and empty when it is the site itself. Click ids are kept by name (`gclid`, `fbclid`…), never their value.

### ChannelClassifier
`classify($context)` returns one of `CHANNELS`: `google_ads`, `paid_social`, `paid_other`, `email`, `social`, `organic_search`, `campaign`, `referral`, `direct`. Paid signals (click id, paid medium) win, then explicit mediums, then the referrer host. `label()` gives the translated name. The result goes through the `lcmt_mailer_submission_channel` filter.

### SubmissionSchema
Table `{prefix}lcmt_mailer_submissions`, created with `dbDelta`. `maybeUpgrade()` runs on `plugins_loaded` and compares the `lcmt_mailer_db_version` option with `VERSION`, because updates from GitHub never run the activation hook. Bump `VERSION` and edit the `CREATE TABLE` to change the schema.

| Column | Content | Cleared by anonymization |
|--------|---------|--------------------------|
| `id`, `form_key`, `mail_post_id`, `status` (`new`, `read`, `processed`, `spam`), `mail_sent` | Identity and state | no |
| `created_at` (UTC) | Date and time sent | truncated to the day (`DATE(created_at)`, midnight UTC) |
| `fields` | JSON snapshot of `{name, type, value}` | yes |
| `mail_error` | wp_mail error text (may quote an address) | yes |
| `page_path`, `page_id`, `landing_path`, `referrer_host`, `channel`, `utm_source`, `utm_medium`, `utm_campaign`, `click_id_type`, `device`, `locale` | Context, for statistics | no |
| `form_seconds` | Time spent on the form | yes (set to NULL) |
| `anonymized_at` | Set when anonymized | (set) |

### SubmissionRepository
All SQL on the table: `insert`, `update`, `find`, `search`/`count` (filters: form, status, failed, channel, search), `setStatus`, `delete`, `anonymize`, `anonymizeBefore`, `deleteBefore`, `deleteAnonymizedBefore`, `findContaining` (privacy tools), `countUnread`, `unresolvedFailures`/`countUnresolvedFailures`, and the stats queries `countBy`, `countByMonth`, `totals`. Use it instead of touching `$wpdb` elsewhere. `anonymize()` and `anonymizeBefore()` share one SET clause (`anonymizeSet()`): the personal columns become NULL, `created_at` keeps only its day and `form_seconds` is dropped, so an anonymized row cannot be matched back to a visit in another log. Retention cutoffs are unaffected: truncation only makes a row look older. The admin shows an anonymized message's date without a time (`SubmissionsPage::formatDate()`).

### SubmissionRecorder
- `record()` saves a submission when the mail template stores submissions (`MetaFields::storesSubmissions()`, on by default) and returns its id, or 0.
- `send($id, $key, $placeholders)` calls `Mailer::sendByKey()` and stores `mail_sent` / `mail_error`.
- `captureMailError()` listens to `wp_mail_failed` to keep the reason of a failure.

### Attribution
Enqueues `assets/dist/attribution.js` on public pages at `wp_enqueue_scripts` priority `Attribution::PRIORITY` (100, after the consent tools), with a `wp-consent-api` dependency whenever WP Consent API is active (`class_exists('WP_CONSENT_API')` or `function_exists('wp_has_consent')`, like WooCommerce) or its handle is registered. It passes `lcmtMailerAttribution.storeWithoutConsent` from the `lcmt_mailer_attribution_without_consent` option (default on) and `lcmtMailerAttribution.consentApi` (whether WP Consent API is active). See Frontend → Attribution.

### SubmissionsPage / SubmissionsListTable
Email templates → Received messages, capability from `SubmissionsPage::capability()` (`manage_options`, filter `lcmt_mailer_submissions_capability`). List with filters, unread bubble in the menu, detail view (opening a message marks it read via `markOpenedAsRead()`), single and bulk actions (resend, processed, unread, spam, delete), CSV export (`handleExport()` streams through `writeCsv()`). `listQueryArgs()` keeps the current filters after an action.

### SubmissionCsv
`table($rows)` builds the header and rows: context columns then one column per field name found. `cell()` prefixes values a spreadsheet would run as formulas (`= + - @`, tab, CR) with a quote (CSV injection).

### SubmissionSettings / Retention
`SubmissionSettings` renders Email templates → Data retention (days before anonymization, `anonymize` or `delete`, days to keep anonymized statistics, attribution without consent, "Purge now"). `Retention::run()` is the daily cron `lcmt_mailer_purge_submissions` (scheduled on `init` since activation hooks do not run on updates, cleared on deactivation), works in batches of 500 and never purges with a period below 1 day (invalid values fall back to the default). Defaults: 1095 days, then anonymize; anonymized statistics kept forever (0).

### Privacy
Registers an exporter and an eraser in Tools → Export/Erase Personal Data, adds text to the privacy policy guide (retention period and what happens after it, only the day kept once anonymized, the recorded context including device, browser language and time on the form, and the landing/campaign kept in the browser for the tab session: after statistics consent with WP Consent API, or when the without-consent option is on), and the `[lcmt-retention-days]` shortcode. Matching is on an **exact** field value equal to the email; an address only written inside a free-text message is not found. The export lists, for each message, the context shown on the detail screen (`SubmissionsPage::contextItems()`: date, form, page, landing page, source, referring site, campaign, ad click, device, browser language, time on the form, only when known), the email status and error, then the field values. Erasing anonymizes (statistics are kept).

### FailureNotice
Admin banner and dashboard widget listing unsent emails (`mail_sent = 0`, not anonymized, not processed/spam) with the last error. `dismiss()` stores the current time in `lcmt_mailer_failures_dismissed_at`; only newer failures bring the banner back.

### StatsPage
Email templates → Statistics: totals, failures, average time on the form, and counts by month, channel, campaign, form, page, landing page, referrer and device for 30 days, 90 days, 12 months or everything. Anonymized messages count, spam does not.

### Uninstall
`uninstall.php` (run only by "Delete") drops the table and removes the options and cron hook. Deactivating or updating keeps the data.

### Permissions
Every capability of the `mail` post type maps to `manage_options`, and the admin AJAX actions check it: only administrators can see, edit or test mails.

## Data flow

```
1. Admin creates mail post with key="contact" and content with placeholders

2a. Shortcode/PHP render:
    FormRenderer::render('contact')
    → loads theme/forms/contact.php
    → wraps with <form data-lcmt-endpoint="...">
    → enqueues form-handler.js

2b. Form submission (handled by form-handler.js):
    fetch POST → /wp-json/lcmt-mailer/v1/forms/contact
    → FormEndpoint::handle()
    → FieldParser::parse() → validates required fields
    → Mailer::sendByKey('contact', $data)
    → Mailer::buildForm() → replaces placeholders
    → TemplateLoader::render() → wraps in HTML email
    → wp_mail()

2b'. Received messages, inside FormEndpoint::handle():
    validates fields
    → SubmissionRecorder::record()      inserts the row (mail_sent = 0) from the `_context` object
    → do_action('lcmt_mailer_before_send')
    → SubmissionRecorder::send()        Mailer::sendByKey(), then updates mail_sent / mail_error
    → do_action('lcmt_mailer_after_send') (only when sent)
    A failed email leaves the message saved and shown in the failure banner.

2c. Direct PHP call (no form):
    Mailer::sendByKey('contact', ['firstname' => 'John', ...])
    → same flow from buildForm() onwards
```
