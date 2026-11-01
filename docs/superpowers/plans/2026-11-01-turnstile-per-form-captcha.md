# Turnstile and per-form spam protection — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add Cloudflare Turnstile as a second spam protection, let each mail template pick its own protection, load each protection's script only on pages whose form uses it, and keep blocked submissions in the Spam view instead of dropping them.

**Architecture:** PHP keeps the `CaptchaProvider` interface; `Captcha` now resolves the provider per mail post (template setting, else site default). Two optional interfaces stay backward compatible with providers added through `lcmt_mailer_captcha_providers`: `CaptchaAssets` (scripts to enqueue with the widget) and the `CaptchaUnavailable` exception (the provider's service did not answer). In the browser, `form-handler.js` no longer knows any provider: it talks to a registry of adapters that share one contract, and each adapter ships as its own entry script (`captcha-altcha.js`, `captcha-turnstile.js`) enqueued only with a form that uses it.

**Tech Stack:** PHP 8.1+ / WordPress 6.0+ (the plugin's floors; `wp_prime_option_caches` is already guarded for < 6.4, the script `strategy` arg is ignored before 6.3), libsodium (`sodium_crypto_secretbox`, or WordPress's bundled `sodium_compat` when the extension is missing), vanilla JS built by esbuild (es2018), PHPUnit without WordPress, node:test, wp-cli integration tests.

**Spec:** the "Decisions" section below (agreed with the owner on 2026-10-30 and 2026-11-01; no separate spec file).

## Decisions

1. **Turnstile is added; ALTCHA stays** as a choice. ALTCHA lets bots through and will be improved in a later plan (out of scope here).
2. **Per-template choice.** Each mail template gets a "Spam protection" select: *Site default (…)*, *None*, then every provider. Empty meta = site default. The Spam protection page keeps the site default.
3. **Verification service down → refuse by default**, configurable on the Spam protection page (`refuse` / `accept`).
4. **Blocked submissions are kept in Received messages → Spam**, never emailed, with the reason (`captcha_failed` or `captcha_unavailable`). A "service unavailable" one is most likely a real visitor: the owner marks it *Not spam* and resends it with the existing actions. Kept only when the fields validate, capped per hour, and can be turned off.
5. **Keys: wp-config.php constant first, else the settings page**, with an explanation and the line to copy into wp-config.php, like the Anthropic key in lcmt-polylang-ai-translate (`includes/Core/KeySource.php` there: source + connection status + `define()` line with a placeholder).
6. **JS: an explicit adapter contract** ("interface"), one script per provider, loaded only when needed; Cloudflare's `api.js` is fetched on the visitor's first interaction with the form, not at page load.
7. **Keys stored in the database are encrypted** (Turnstile secret and the ALTCHA HMAC key): libsodium secretbox, key derived from `AUTH_KEY` + `SECURE_AUTH_SALT`, or from an `LCMT_MAILER_ENCRYPTION_KEY` constant when set. Protects database backups, exports, copies and SQL reads, not a reader of `wp-config.php`; the wp-config.php constant stays the recommended place. An existing plain ALTCHA key is encrypted on first read.
8. **Unreadable key** (WordPress salts changed, database copied to another site): the Turnstile protection counts as not set up (forms fall back to no protection from it), an admin notice asks to enter the key again, and **one email** goes to the site's admin address (`admin_email`) until the key is saved again. An unreadable ALTCHA key is simply regenerated (it is auto-generated; no email).
9. **Spam is deleted after 30 days** (setting on the Data retention page, `0` = keep with the normal retention): every row with status `spam`, blocked or marked by hand, deleted for good (no trash), by the existing daily purge.

## Global Constraints

- No jQuery, no new npm dependency. Cloudflare's script is loaded from `https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit&onload=lcmtTurnstileReady` (Cloudflare forbids self-hosting or proxying it). Siteverify: `POST https://challenges.cloudflare.com/turnstile/v0/siteverify`.
- Slug, text domain and prefixes stay `lcmt-dev-mailer` / `lcmt_mailer_` / `LcmtDevMailer`; files kebab-case; classes PascalCase; code comments in English.
- Every option read on public pages is non-autoloaded and added to the `wp_prime_option_caches()` list in `lcmt-dev-mailer.php`.
- Never print a stored secret back into a page: the field stays empty, its row says where the key comes from.
- Run `yarn build` after editing `assets/src/` and commit `assets/dist/`.
- Commit messages: Conventional Commits, in English, no Claude attribution.
- Before the release: the check without any language plugin (see `.claude/build.md`).

## Review Focus

1. **Cached pages.** A full-page cache serves yesterday's HTML: an ALTCHA form must keep working with a widget rendered before the update (it has no `data-lcmt-captcha`). → Task 5 test `finds a legacy altcha-widget without data-lcmt-captcha`.
2. **Provider script blocked or slow** (ad blocker on challenges.cloudflare.com, slow 3G): submit must end in the "Security verification failed" alert within the timeout, never a stuck loader. → Task 5 test `rejects after the timeout when no adapter registers`; Task 6 test `rejects when api.js never loads`.
3. **Token expired while the tab slept** (Turnstile tokens live 300 s): submit must fetch a fresh one, not send the stale one. → Task 6 test `waits for a new token when the current one expired`.
4. **Bot flood of blocked submissions** filling the table. → Task 4 integration test `stops keeping blocked submissions past the hourly limit`.
5. **Second submit after a server refusal** (422 on a field): the token was spent; the next submit must use a new one. → Task 6 test `reset clears the token so the next submit waits for a new one`.

---

### Task 1: Per-template provider resolution (PHP)

**Files:**
- Modify: `includes/captcha-provider.php` (add `CaptchaAssets` interface and `CaptchaUnavailable` exception below `CaptchaProvider`)
- Modify: `includes/captcha.php`
- Modify: `includes/meta-fields.php` (constant, select in the meta box, save)
- Modify: `includes/form-renderer.php:51`, `includes/form-endpoint.php:69`
- Modify: `wpml-config.xml` (`<custom-field action="copy">_lcmt_mail_captcha</custom-field>`)
- Modify: `tests/bootstrap.php` (stub `get_post_meta` over `$GLOBALS['lcmt_test_post_meta'][$id][$key]`; require captcha files)
- Test: `tests/CaptchaTest.php`

**Interfaces:**
- Produces:
  - `interface CaptchaAssets { public static function enqueueAssets(): void; }` — called once per page, the first time `Captcha::widget()` renders this provider.
  - `class CaptchaUnavailable extends \RuntimeException` — thrown by `verify()` when the provider's service could not give an answer.
  - `MetaFields::FIELD_CAPTCHA = '_lcmt_mail_captcha'`
  - `Captcha::INHERIT = ''`; `Captcha::PASSED = 'passed'`, `Captcha::FAILED = 'failed'`, `Captcha::UNAVAILABLE = 'unavailable'`
  - `Captcha::selectedIdFor(int $postId): string` — meta when it names `none` or a known provider, else `selectedId()`
  - `Captcha::activeFor(int $postId): ?string` (class-string, ready providers only)
  - `Captcha::widget(int $postId): string`
  - `Captcha::check(int $postId, array $body): string` — one of the three outcomes; `PASSED` when no active provider
  - `Captcha::sanitizeForMail($value): string` — `''`, `none` or a known id; anything else `''`
  - `Captcha::registerRoutes()` registers the routes of **every ready provider** (two forms may use two protections)
- `selectedId()`, `sanitize()`, `providers()` keep their behavior; `verify(array $body): bool` is removed (only the endpoint called it).

- [ ] **Step 1: Write `tests/CaptchaTest.php`** with a fake provider class registered through the filter stub (`apply_filters` stub returns its second argument unless `$GLOBALS['lcmt_test_filters'][$hook]` is set):
  - `test_template_without_choice_uses_site_default` — option `altcha`, no meta → `selectedIdFor(7) === 'altcha'`
  - `test_template_choice_wins_over_site_default` — option `altcha`, meta `fake` → `'fake'`
  - `test_template_none_turns_protection_off` — meta `none` → `activeFor(7) === null` and `check(7, []) === 'passed'`
  - `test_unknown_template_choice_falls_back_to_site_default` — meta `removed-plugin` → `selectedIdFor(7) === 'altcha'`
  - `test_check_reports_unavailable_when_provider_throws` — fake `verify` throws `CaptchaUnavailable` → `'unavailable'`
  - `test_check_reports_failed_on_false` / `test_check_reports_passed_on_true`
  - `test_sanitize_for_mail` — `''`→`''`, `'none'`→`'none'`, `'fake'`→`'fake'`, `'<x>'`→`''`
- [ ] **Step 2: Run** `phpunit --filter CaptchaTest` — Expected: FAIL (methods not defined).
- [ ] **Step 3: Implement** the interfaces above. In `MetaFields`, a `<select name="lcmt_mail_captcha">` under the confirmation page row: first option `''` labelled `sprintf(__('Site default (%s)', 'lcmt-dev-mailer'), <label of Captcha::selectedId()>)` (`None` when the default is none), then `None`, then each provider; a provider whose `isReady()` is false gets ` — ` + `__('not set up', 'lcmt-dev-mailer')` and a link to `CaptchaSettings::url()` in the description. Save through `sanitizeForMail`, `delete_post_meta` when `''`.
- [ ] **Step 4: Wire callers:** `FormRenderer::render()` → `Captcha::widget($mailPost->ID)`; `FormEndpoint` keeps the 403 for anything but `PASSED` for now (Task 4 changes the flow).
- [ ] **Step 5: Run** `phpunit` — Expected: all pass, `AltchaTest` included.
- [ ] **Step 6: Commit** `feat(captcha): let each mail template choose its spam protection`

### Task 2: Encrypted secrets in the database

**Files:**
- Create: `includes/secret-box.php` (`SecretBox`), `includes/unreadable-secrets.php` (`UnreadableSecrets`)
- Modify: `includes/altcha.php` (`getKey()` and `handleRegenerateKey()` go through `SecretBox`)
- Modify: `lcmt-dev-mailer.php` (requires; `admin_notices` → `UnreadableSecrets::notice`)
- Test: `tests/SecretBoxTest.php`, `tests/UnreadableSecretsTest.php` (bootstrap: define `AUTH_KEY`, `SECURE_AUTH_SALT`; stub `wp_mail` into `$GLOBALS['lcmt_test_mails']`, `get_bloginfo`, `delete_option`)

**Interfaces:**
- Produces:
  - `SecretBox::PREFIX = 'lcmt1:'`; `SecretBox::CONST_KEY = 'LCMT_MAILER_ENCRYPTION_KEY'`
  - `SecretBox::encrypt(string $plain): string` → `'lcmt1:' . base64_encode($nonce . $cipher)`, 24-byte random nonce, `sodium_crypto_secretbox`
  - `SecretBox::decrypt(string $stored): ?string` — `null` when the prefix is missing, the base64 or the box is invalid (never throws)
  - `SecretBox::isEncrypted(string $stored): bool`
  - `SecretBox::canEncrypt(): bool` — false when neither the constant nor real salts exist (unset, empty, or WordPress's `put your unique phrase here`); then values are stored plain and the settings page says why
  - key: `hash_hkdf('sha256', <constant, or AUTH_KEY . SECURE_AUTH_SALT>, SODIUM_CRYPTO_SECRETBOX_KEYBYTES, 'lcmt-dev-mailer')`
  - when `function_exists('sodium_crypto_secretbox')` is false, `require_once ABSPATH . WPINC . '/sodium_compat/autoload.php'` once (bundled with WordPress since 5.2)
  - `SecretBox::readOption(string $option, string $label, bool $report = true): string` — plain legacy value → re-saved encrypted, returned; encrypted → decrypted; unreadable → `UnreadableSecrets::report($option, $label)` when `$report`, returns `''`
  - `SecretBox::writeOption(string $option, string $plain): void` — `update_option(…, false)` encrypted, then `UnreadableSecrets::clear($option)`
  - `UnreadableSecrets::OPTION = 'lcmt_mailer_unreadable_secrets'` (array option name ⇒ label, non-autoloaded), `report(string $option, string $label): void`, `clear(string $option): void`, `notice(): void`
  - `report()` adds the option to the list and, only when it was not listed yet, sends one plain-text `wp_mail` to `get_option('admin_email')`: subject `sprintf(__('[%s] A spam protection key can no longer be read', 'lcmt-dev-mailer'), get_bloginfo('name'))`, body naming the key's label, the likely cause (WordPress security keys changed or database copied from another site), the consequence (forms using it are not protected by it) and `CaptchaSettings::url()`. The "not listed yet" check uses `add_option('lcmt_mailer_unreadable_mail_' . $option, time(), '', false)` as a lock so concurrent requests send one email; `clear()` deletes the lock.
  - `notice()`: for `manage_options` users, an error notice on admin pages listing the unreadable keys with a link to the Spam protection page.
- ALTCHA: `getKey()` reads with `readOption(OPTION_KEY, …, report: false)`; `''` (unset or unreadable) → generates and writes a new key with the current `add_option` race guard (stored encrypted).

- [ ] **Step 1: Write the tests:**
  - `test_round_trip`, `test_two_encryptions_differ` (random nonce), `test_tampered_value_decrypts_to_null`, `test_value_without_prefix_is_not_encrypted`
  - `test_other_salts_cannot_decrypt` (encrypt, change the key source through the constant, decrypt → null)
  - `test_constant_key_wins_over_salts`
  - `test_plain_legacy_option_is_reencrypted_on_read`
  - `test_unreadable_option_returns_empty_and_reports`
  - `test_report_sends_one_mail_to_admin_email_however_many_times_called`
  - `test_writing_the_option_again_clears_the_report_and_the_lock`
  - `test_altcha_unreadable_key_is_regenerated_without_mail`
- [ ] **Step 2: Run** `phpunit --filter 'SecretBox|UnreadableSecrets|Altcha'` — Expected: FAIL.
- [ ] **Step 3: Implement** the interfaces above.
- [ ] **Step 4: Check the sodium_compat fallback** on the local WordPress: `wp eval 'require ABSPATH . WPINC . "/sodium_compat/autoload.php"; var_dump(ParagonIE_Sodium_Compat::crypto_secretbox_open(ParagonIE_Sodium_Compat::crypto_secretbox("x", str_repeat("n", 24), str_repeat("k", 32)), str_repeat("n", 24), str_repeat("k", 32)));'` — Expected: `string(1) "x"`. Then decrypt, with that polyfill, a value `SecretBox::encrypt()` produced with the extension (same `wp eval`) — Expected: the plain value, proving both paths read each other's output.
- [ ] **Step 5: Run** `phpunit` — Expected: all pass.
- [ ] **Step 6: Commit** `feat(security): encrypt the spam protection keys stored in the database`

### Task 3: Turnstile provider (PHP)

**Files:**
- Create: `includes/turnstile.php`
- Modify: `includes/captcha.php` (add `Turnstile::id() => Turnstile::class` to the default providers)
- Modify: `includes/captcha-settings.php` (register the new options; unavailable policy row, see Task 4)
- Modify: `lcmt-dev-mailer.php` (require, primed options list)
- Test: `tests/TurnstileTest.php`

**Interfaces:**
- Consumes: `CaptchaProvider`, `CaptchaAssets`, `CaptchaUnavailable` (Task 1); `SecretBox::readOption()` / `writeOption()` / `canEncrypt()`, `UnreadableSecrets` (Task 2). The secret is read with `readOption(OPTION_SECRET_KEY, __('Cloudflare Turnstile secret key', 'lcmt-dev-mailer'))`, so an unreadable one makes `isReady()` false, raises the notice and sends the one email. The site key is public and stored plain.
- Produces: `class Turnstile implements CaptchaProvider, CaptchaAssets` with
  - `id() = 'turnstile'`, `label() = 'Cloudflare Turnstile'`
  - `CONST_SITE_KEY = 'LCMT_MAILER_TURNSTILE_SITE_KEY'`, `CONST_SECRET_KEY = 'LCMT_MAILER_TURNSTILE_SECRET_KEY'`
  - `OPTION_SITE_KEY = 'lcmt_mailer_turnstile_site_key'`, `OPTION_SECRET_KEY = 'lcmt_mailer_turnstile_secret_key'`, `OPTION_APPEARANCE = 'lcmt_mailer_turnstile_appearance'` (`interaction-only` default, or `always`), all non-autoloaded
  - `FIELD = 'turnstile'` (body key of the token), `ACTION = 'lcmt_form'`, `TIMEOUT_SECONDS = 5`, `STATUS_TRANSIENT = 'lcmt_mailer_turnstile_status'`
  - `keySource(string $which): string` — `$which` is `'site'|'secret'`; returns `'constant'|'database'|'none'`
  - `siteKey(): string`, `isReady(): bool` (both keys non-empty)
  - `widget(): string` → `<div data-lcmt-captcha="turnstile" data-sitekey="…" data-action="lcmt_form" data-appearance="…"></div>`
  - `enqueueAssets(): void` → `wp_enqueue_script('lcmt-captcha-turnstile', …/assets/dist/captcha-turnstile.js, [], version, ['in_footer' => true, 'strategy' => 'defer'])`
  - `verify(array $body): bool` → posts `secret`, `response`, `remoteip` (`$_SERVER['REMOTE_ADDR']`) with `wp_remote_post(…, ['timeout' => 5])`, then `parseVerification()`
  - `parseVerification($response, string $expectedHost): bool` — pure, takes a `wp_remote_post` result: `WP_Error`, a non-200 code, a body that is not JSON, or `error-codes` containing `internal-error` → throws `CaptchaUnavailable`; otherwise true only when `success === true`, `action === 'lcmt_form'` and `hostname === $expectedHost` (host of `home_url()`)
  - `sanitizeSecret($value): string` — empty input keeps the stored secret; a new value is stored through `SecretBox::writeOption()`; a checked `lcmt_turnstile_forget_secret` deletes it
  - `keySource('secret')` also returns `'unreadable'` when the stored value cannot be decrypted; the settings row then says "The saved key can no longer be read (WordPress security keys changed or database copied from another site). Enter it again." When `SecretBox::canEncrypt()` is false it adds "Stored without encryption: this site has no WordPress security keys."
  - `checkKeys(): string` — siteverify with `response = 'lcmt-key-check'`: `error-codes` containing `invalid-input-secret` → `'invalid'`, containing `invalid-input-response` → `'connected'`, `CaptchaUnavailable` → `'unreachable'`; result cached 1 day in the transient, refreshed when the settings are saved
  - `constantLine(string $constant): string` → `define( 'LCMT_MAILER_TURNSTILE_SECRET_KEY', 'your-secret-key' );` (site key line uses `'your-site-key'`)

- [ ] **Step 1: Write `tests/TurnstileTest.php`** (add `WP_Error` and `wp_remote_retrieve_*` / `home_url` stubs to the bootstrap):
  - `test_success_with_matching_action_and_host_passes`
  - `test_success_false_fails` (`error-codes: ['invalid-input-response']`)
  - `test_wrong_action_fails`, `test_wrong_hostname_fails`
  - `test_wp_error_throws_unavailable`, `test_http_500_throws_unavailable`, `test_internal_error_code_throws_unavailable`, `test_non_json_body_throws_unavailable`
  - `test_not_ready_without_secret`
  - `test_empty_secret_input_keeps_stored_secret`
  - `test_new_secret_is_stored_encrypted` (option value starts with `lcmt1:`)
  - `test_unreadable_secret_is_not_ready_and_reported`
  - `test_constant_line_uses_placeholder_not_the_key`
- [ ] **Step 2: Run** `phpunit --filter TurnstileTest` — Expected: FAIL (class not found).
- [ ] **Step 3: Implement `includes/turnstile.php`** with the interface above.
- [ ] **Step 4: `renderSettings()`** prints rows carrying `data-captcha-provider="turnstile"`:
  - an intro paragraph: create a widget in the Cloudflare dashboard (Turnstile → Add widget), list the site's hostnames (production and staging), copy both keys here; link to `https://dash.cloudflare.com/?to=/:account/turnstile`.
  - **Site key**: text input, or "Set by the `LCMT_MAILER_TURNSTILE_SITE_KEY` constant in `wp-config.php`." when set there.
  - **Secret key**: `type="password"` input, always empty, `autocomplete="off"`; under it "Saved in the database. Leave empty to keep it." plus the forget checkbox, or the constant sentence. When the source is `database`: a notice "A key in the database ends up in backups and copies of the database. Prefer adding this line to wp-config.php, then empty the field here:" with `constantLine()` and a Copy button (reuse `assets/src/lib/copy-button.js`, as `admin-privacy-docs.js` does).
  - **Status** notice from `checkKeys()`: success "Cloudflare accepts the keys.", error "Cloudflare refuses the secret key.", error "Cloudflare could not be reached.", warning "Add both keys to protect forms with Turnstile." when not ready.
  - **Appearance** select: "Only when Cloudflare needs the visitor (recommended)" = `interaction-only`, "Always visible" = `always`.
  - a description for testing: Cloudflare's test keys (site `1x00000000000000000000AA` / secret `1x0000000000000000000000000000000AA` always pass; `2x00000000000000000000AB` / `2x0000000000000000000000000000000AA` always fail).
- [ ] **Step 5: Run** `phpunit` — Expected: all pass.
- [ ] **Step 6: Commit** `feat(captcha): add Cloudflare Turnstile as a spam protection`

### Task 4: Keep blocked submissions in Spam, delete spam after 30 days

**Files:**
- Modify: `includes/submission-settings.php` (`OPTION_SPAM_DAYS = 'lcmt_mailer_spam_retention_days'`, `spamRetentionDays(): int` = `Retention::clampDays(get_option(…, 30), 0, 30)`; field "Delete spam after … days" on the Data retention page, description "Deleted for good, without going through the trash. 0 keeps spam as long as other messages.")
- Modify: `includes/retention.php` (`run()` deletes spam first; result gains `'spam' => int`)
- Modify: `includes/submission-repository.php` (`deleteSpamBefore(string $cutoff, int $limit): int` — `status = 'spam' AND created_at < %s`, trashed rows included)
- Modify: `includes/submission-schema.php` (`VERSION = '5'`, column `spam_reason varchar(30) NOT NULL DEFAULT ''` after `status`)
- Modify: `includes/submission-recorder.php` (`record()` gains `string $spamReason = ''`; new `canKeepBlocked(): bool`)
- Modify: `includes/form-endpoint.php` (flow below)
- Modify: `includes/captcha-settings.php` (two settings)
- Modify: `includes/submissions-page.php` (reason line in the detail view)
- Test: `tests/integration/blocked-submission-test.php`

**Interfaces:**
- Consumes: `Captcha::check()` and its outcomes (Task 1).
- Produces:
  - `SubmissionRecorder::REASON_FAILED = 'captcha_failed'`, `REASON_UNAVAILABLE = 'captcha_unavailable'`
  - `Captcha::OPTION_UNAVAILABLE = 'lcmt_mailer_captcha_unavailable'` (`refuse` default | `accept`); `Captcha::OPTION_KEEP_BLOCKED = 'lcmt_mailer_keep_blocked'` (`'1'` default)
  - filter `lcmt_mailer_blocked_hourly_limit` (int, default `50`), counter transient `lcmt_mailer_blocked_` + `gmdate('YmdH')`

Endpoint flow: `$outcome = Captcha::check($post->ID, $body)`; `UNAVAILABLE` with policy `accept` counts as `PASSED`. Fields are validated as today whatever the outcome; invalid fields on a blocked submission → the same 403 as today, nothing kept. Valid fields on a blocked submission → `record(…, $reason)` only when `MetaFields::storesSubmissions()`, `OPTION_KEEP_BLOCKED` is on and `canKeepBlocked()` (increments the counter, false past the limit); no `lcmt_mailer_before_send`, no mail; same 403 and message as today, so a bot learns nothing.

- [ ] **Step 1: Write the integration tests** (harness rolls back; no DDL in tests — the schema upgrade runs before):
  - `keeps a blocked submission as spam with its reason, unsent` — status `spam`, `spam_reason` `captcha_failed`, `mail_sent` 0
  - `stops keeping blocked submissions past the hourly limit` — filter limit 2, three blocked calls → 2 rows
  - `keeps nothing when the setting is off`
  - `a blocked submission raises no failure notice and no unread bubble` — `FailureNotice` count and `count(['status' => 'new'])` unchanged
  - `the purge deletes spam older than 30 days and keeps newer spam and old non-spam` — rows at 31 and 29 days with status `spam`, one at 31 days with status `read` → only the first is gone
  - `a spam retention of 0 leaves spam to the normal retention`
- [ ] **Step 2: Run** `wp eval-file …/tests/integration/run.php` — Expected: the six new tests FAIL.
- [ ] **Step 3: Implement** schema, recorder, endpoint flow and settings ("If the verification service does not answer": *Refuse the message (recommended)* / *Accept it*; "Keep blocked messages in Received messages → Spam" checkbox, description "Never emailed. Mark one as not spam, then resend it, if a real visitor was blocked.").
- [ ] **Step 4: Detail view:** under the status, for a non-empty `spam_reason`: "Blocked by the spam protection." or "Blocked because the verification service did not answer: probably a real visitor."
- [ ] **Step 5: Run** integration tests and `phpunit` — Expected: all pass.
- [ ] **Step 6: Commit** `feat(submissions): keep messages blocked by the spam protection in Spam` (the privacy policy text in `includes/privacy.php` mentions the 30-day spam deletion when it lists retention periods)

### Task 5: JS adapter contract and registry; ALTCHA moved out of form-handler

**Files:**
- Create: `assets/src/lib/captcha/registry.js`, `assets/src/lib/captcha/altcha-proof.js`, `assets/src/captcha-altcha.js`
- Modify: `assets/src/form-handler.js` (remove `import 'altcha'` and the whole "Spam protection (ALTCHA)" section and the challenge-URL cache-bust in `bindForm`)
- Modify: `includes/altcha.php` (`widget()` adds `data-lcmt-captcha="altcha"`; implements `CaptchaAssets`, enqueuing `lcmt-captcha-altcha` → `assets/dist/captcha-altcha.js`, footer, `defer`)
- Test: `tests/js/captcha-registry.test.js`, `tests/js/altcha-proof.test.js`

**Interfaces:**
- Produces, in `lib/captcha/registry.js`:
  ```js
  /**
   * @typedef {Object} CaptchaAdapter   The contract every protection implements.
   * @property {string} id              Same id as the PHP provider.
   * @property {string} field           Body key the server reads the token from.
   * @property {(widget: Element) => Promise<string>} token  A token the server will accept now.
   * @property {(widget: Element) => void} reset             Called after every server answer.
   */
  export function defineAdapter(adapter)        // throws TypeError naming the missing member; returns a frozen copy
  export function registerAdapter(adapter, root = window)   // stores in root.lcmtMailerCaptchas[id], dispatches 'lcmt:captcha-ready' (detail.id) on root.document
  export function captchaToken(form, root = window, timeoutMs = 30000)  // → Promise<{field, token} | null>
  export function resetCaptcha(form, root = window)
  ```
  The widget is `form.querySelector('[data-lcmt-captcha]')`, or a legacy `altcha-widget` (id `altcha`). No widget → `null`. Adapter not registered yet → wait for `lcmt:captcha-ready` until `timeoutMs`, then reject.
- `lib/captcha/altcha-proof.js`: `export function expiresSoon(payload, now = Date.now(), marginMs = 30000): boolean` (current `captchaExpiresSoon`).
- `captcha-altcha.js`: imports `altcha`, cache-busts each widget's `challengeurl` on load, registers `{ id: 'altcha', field: 'altcha', token, reset }` (token = current proof unless `expiresSoon`, else today's `solveCaptcha`).
- `form-handler.js` submit: `captchaToken(form, window).then(c => { if (c) data[c.field] = c.token; … })`, `resetCaptcha(form, window)` where it calls `resetCaptcha(form)` today. `form.noValidate = true` stays.

- [ ] **Step 1: Write the JS tests:**
  - registry: `defineAdapter throws on a missing token`, `resolves null without a widget`, `uses the adapter named by data-lcmt-captcha`, `finds a legacy altcha-widget without data-lcmt-captcha`, `waits for an adapter registered after submit`, `rejects after the timeout when no adapter registers`, `reset calls the adapter of the form's widget only`
  - altcha-proof: `a proof expiring in 10 s expires soon`, `a proof expiring in 5 min does not`, `an unreadable proof expires soon`
- [ ] **Step 2: Run** `yarn test:js` — Expected: new tests FAIL.
- [ ] **Step 3: Implement** the files above.
- [ ] **Step 4: Run** `yarn test:js && yarn build` — Expected: tests pass; `assets/dist/form-handler.js` drops from ~77 KB to ~12 KB, `assets/dist/captcha-altcha.js` holds ALTCHA.
- [ ] **Step 5: Manual check** on a local site with ALTCHA: submit, field error then resubmit, submit after 6 minutes idle.
- [ ] **Step 6: Commit** `refactor(forms): load each spam protection through its own adapter script`

### Task 6: Turnstile adapter (JS)

**Files:**
- Create: `assets/src/lib/captcha/turnstile-widget.js`, `assets/src/captcha-turnstile.js`
- Test: `tests/js/turnstile-widget.test.js`

**Interfaces:**
- Consumes: `defineAdapter`, `registerAdapter` (Task 5); widget markup from Task 3.
- Produces, in `lib/captcha/turnstile-widget.js`:
  - `export const API_URL = 'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit&onload=lcmtTurnstileReady'`
  - `export function createTurnstileAdapter({ loadApi, timeoutMs = 30000 })` → a `CaptchaAdapter` with `id: 'turnstile'`, `field: 'turnstile'`. `loadApi(): Promise<TurnstileApi>` is injected (tests pass a fake; the entry passes a loader that appends the script once and resolves on `window.lcmtTurnstileReady`).
  - `export function bindLazyRender(form, widget, adapter)` — on the form's first `focusin`, loads the API and renders; `token()` renders too if the visitor never focused a field.
  - Render options: `sitekey`, `action`, `appearance` from the `data-*` attributes, `'response-field': false` (no hidden input picked up as a form field), `'refresh-expired': 'auto'`, `language: document.documentElement.lang`, plus `callback` / `expired-callback` / `error-callback` keeping the current token.
  - `token(widget)`: resolves the current token when `turnstile.isExpired(id)` is false; otherwise `turnstile.reset(id)` and waits for `callback`; `error-callback` or the timeout rejects.
  - `reset(widget)`: `turnstile.reset(id)` and forgets the token.
- `captcha-turnstile.js`: `registerAdapter(adapter)` and `bindLazyRender` for every `[data-lcmt-captcha="turnstile"]` on `DOMContentLoaded`.

- [ ] **Step 1: Write the tests** with a fake `turnstile` object (render/reset/isExpired, test drives the callbacks):
  - `does not load the API before the visitor enters the form`
  - `renders once on the first focusin, with response-field false and the data-* options`
  - `resolves the current token when it is still valid`
  - `waits for a new token when the current one expired`
  - `reset clears the token so the next submit waits for a new one`
  - `rejects on error-callback`
  - `rejects when api.js never loads` (loadApi never resolves, timeout 50 ms)
- [ ] **Step 2: Run** `yarn test:js` — Expected: FAIL.
- [ ] **Step 3: Implement**, then `yarn build`.
- [ ] **Step 4: Manual check** with Cloudflare's test keys (pass pair and fail pair): success, refusal kept in Spam with its reason, a page with both an ALTCHA and a Turnstile form loads both scripts and each form works, a page with no Turnstile form never requests challenges.cloudflare.com (Network tab).
- [ ] **Step 5: Commit** `feat(forms): protect forms with Cloudflare Turnstile in the browser`

### Task 7: Docs, translations, release 2.7.0

**Files:**
- Modify: `.claude/architecture.md` (new files, `Captcha` per mail, `spam_reason`), `.claude/frontend.md` (adapter contract, lazy loading, step list of submit), `.claude/hooks.md` (`CaptchaAssets`, `CaptchaUnavailable`, `lcmt_mailer_blocked_hourly_limit`, the per-mail meta), `.claude/build.md` (new dist entries), `README.md`
- Modify: `languages/lcmt-dev-mailer.pot`, `languages/lcmt-dev-mailer-fr_FR.po` (+ `.mo`)
- Create: `release-notes/2.7.0.md`; bump header and `package.json` to `2.7.0`

- [ ] **Step 1:** Update the docs and translate every new string to French.
- [ ] **Step 2:** `phpunit && yarn test:js && yarn build`, integration tests, and the check without any language plugin — Expected: all pass, no fatal error.
- [ ] **Step 3: Commit** `docs: document Turnstile, per-form protection and blocked messages` then `chore(release): 2.7.0`; tag only when the owner asks.

## After the release (Twin 3D, not in this repo)

Create the Turnstile widget in Cloudflare for `twin-3d.com` and the staging host, add both constants to `wp-config.php` on each server, choose Turnstile on the contact template, and allow `challenges.cloudflare.com` (script-src, frame-src) if a Content-Security-Policy header is ever added (none today).

## Later (separate plan)

Improve ALTCHA (higher difficulty / obfuscation, honeypot field, minimum fill time from `form_seconds`), since bots get through today.
