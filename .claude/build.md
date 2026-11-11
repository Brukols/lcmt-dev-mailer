# Build

## Setup

```bash
nvm use 26
yarn install
```

## Commands

| Command      | Description                                      |
|--------------|--------------------------------------------------|
| `yarn build` | One-time build — minifies all `assets/src/*.js`  |
| `yarn watch` | Watch mode — rebuilds on file change             |

## Pipeline

- **Tool:** esbuild
- **Source:** `assets/src/*.js`
- **Output:** `assets/dist/*.js`
- **Target:** ES2018
- **Options:** `--bundle --minify`

## JS files

| Source                          | Output                           | Context  | Description                        |
|---------------------------------|----------------------------------|----------|------------------------------------|
| `src/admin-test-mail.js`        | `dist/admin-test-mail.js`        | Admin    | Test email sender in metabox       |
| `src/admin-generate-template.js`| `dist/admin-generate-template.js`| Admin    | Generate form template button      |
| `src/admin-uninstall.js`        | `dist/admin-uninstall.js`        | Admin    | Plugins screen: on deactivation, asks whether a later deletion deletes the messages |
| `src/admin-stats.js`            | `dist/admin-stats.js`            | Admin    | Statistics page: Chart \| Table toggle of each box |
| `src/admin-privacy-docs.js`     | `dist/admin-privacy-docs.js`     | Admin    | Data retention page: Copy buttons of the Privacy policy section |
| `src/form-handler.js`           | `dist/form-handler.js`           | Frontend | Auto form submit, validation, fetch, goes through the adapter registry |
| `src/captcha-altcha.js`         | `dist/captcha-altcha.js`         | Frontend | ALTCHA adapter (bundles `altcha` 3.5, about 125 KB with its inlined workers), only on pages with a form it protects |
| `src/captcha-turnstile.js`      | `dist/captcha-turnstile.js`      | Frontend | Turnstile adapter, loads Cloudflare's `api.js` on the first focus in the form (an `always` widget: when it scrolls into view) |
| `src/attribution.js`            | `dist/attribution.js`            | Frontend | Remembers the landing page of the visit |

`src/lib/` holds shared modules (`lib/attribution.js`, `lib/field-value.js`, `lib/field-errors.js`, `lib/file-field.js`, `lib/uninstall-prompt.js`, `lib/deactivate-dialog.js`, `lib/stats-view.js`, `lib/request-url.js`, `lib/form-success.js`, `lib/captcha/registry.js`, `lib/captcha/script-loader.js`, `lib/captcha/altcha-proof.js`, `lib/captcha/turnstile-widget.js`): imported by entry points, not emitted on their own since the build only globs `src/*.js`.

## Rules

- All JS must be vanilla — no jQuery, no frameworks
- All JS source lives in `assets/src/`
- Always run `yarn build` after editing source files
- Admin scripts use `wp_localize_script` with `lcmtMailerAdmin` for config/i18n
- Frontend script has zero config — discovers forms via `[data-lcmt-endpoint]`
- Dist files are committed to the repo (no build step required on deploy)

## Adding a new JS file

1. Create `assets/src/my-script.js`
2. Run `yarn build` — esbuild picks up all `*.js` in `src/` automatically
3. Enqueue in PHP:
   ```php
   wp_enqueue_script(
       'lcmt-my-script',
       LCMT_MAILER_URL . 'assets/dist/my-script.js',
       [],
       LCMT_MAILER_VERSION,
       true
   );
   ```

## Tests

Three suites.

**PHP unit tests** (`tests/*.php`) run without WordPress: `tests/bootstrap.php` stubs the few functions the tested classes call. They cover `FieldParser` (file type, TypeScript `File`), `FieldValidator`, `FileField` (format map, size cap, names, upload errors, `_context` decoding), `AdminMailSender::buildFormTemplate()` (`FormTemplateTest`), `SubmissionData` (file names), `RequestLanguage`, `MailLanguage`, `ConfirmationPage` (`normalizeId()`, `pick()`), `wpml-config.xml` (`WpmlConfigTest`: entries and meta names equal to `MetaFields` constants), `Altcha`, `Captcha`, `Turnstile`, `SecretBox`, `UnreadableSecrets`, `SubmissionData`, `SubmissionContext`, `UserAgent` (real user agent strings and precedence cases), `ChannelClassifier`, `SubmissionCsv` and `Retention` (pure logic, including `periodParts()`). Needs PHPUnit 11 (`brew install phpunit`).

```bash
phpunit
```

**JS tests** (`tests/js/`) use the Node test runner, bundled with esbuild (no extra dependency): `attribution.test.js` (the shared module), `attribution-entry.test.js` (the entry point, with a stubbed browser from `env.js`) `uninstall-prompt.test.js` (open dialog → request → link followed once flow of the Deactivate link: cancel, checkbox value sent, double submit, failed request, with fake links, prompt and `fetch`), `deactivate-dialog.test.js` (the `<dialog>`: pre-checked state, help line, Cancel, backdrop, Escape, busy state, with fake elements) `copy-button.test.js` (Clipboard API, fallback, "Copied" label and its reset) `stats-view.test.js` (Chart | Table state and `localStorage` persistence, storage that throws) `form-handler-lang.test.js` (the REST URL with the page language as `lang`) `form-success.test.js` (the `lcmt:success` event, then redirect or message; unsafe `redirect` values ignored), `field-value.test.js` (checkbox, radio, file), `field-errors.test.js` (labels, legend and radio group to mark, the clearing event), `file-field.test.js` (limits from the form, extension and size check, JSON or multipart body, chosen file name), `captcha-registry.test.js` (adapter contract, registration, waiting for an adapter, on-demand loader), `altcha-proof.test.js` and `turnstile-widget.test.js` (lazy `api.js`, tokens, reset, failures, with fake widgets).

```bash
yarn test:js
```

**Integration tests** (`tests/integration/`) run inside WordPress with wp-cli, against a local site with the plugin active. Sync the plugin into the site's `wp-content/plugins/lcmt-dev-mailer/` (rsync, excluding `.git`, `node_modules`, `docs`, `tests`), then from the site directory:

```bash
wp eval-file /path/to/lcmt-dev-mailer/tests/integration/run.php
```

`run.php` loads every `*-test.php` file: repository, blocked submissions kept as spam (`blocked-submission-test.php`), recorder, attribution, submissions page, resend and CSV export, retention, privacy tools, failure notice, stats page (charts and toggle markup), user agent and the deactivation dialog. Each test runs in a transaction that is rolled back, so nothing is left in the database (never run DDL in a test: it commits implicitly). Shared helpers live in `tests/integration/helpers.php` (they also keep any email from leaving the site: `pre_wp_mail` is filtered). Use a local or staging site, never production.

**File uploads** need a real HTTP request (`is_uploaded_file()` is false in `wp eval-file`), so they have their own script, run against the same local site:

```bash
tests/integration/file-upload-test.sh <site-dir> <site-url> ["<wp command>"]
# e.g. tests/integration/file-upload-test.sh ~/Sites/demo https://demo.test "/opt/homebrew/opt/php@8.3/bin/php /opt/homebrew/bin/wp"
```

It creates a temporary template (spam protection *None*, messages not saved, JPG/PNG/PDF up to 1 MB, a required file) and a temporary mu-plugin whose `pre_wp_mail` filter records the attachments and stops every email, then posts with curl: allowed PDF and PNG (attached under their name, 0600 in a 0700 folder, deleted after sending), a format not allowed, text renamed `.pdf`, a PNG renamed `.jpg`, a JPEG named `.php`, a file over the size, a missing required file, `field[]` with two files, a file name sent as text, a JSON body, and a failed send (500, copy deleted). Copies are looked for in the **web server's** temp dir (recorded by the capture), which can differ from WP-CLI's. Everything it created, and the mail-log rows of the run, is removed on exit. The wp command must be a real executable, not a shell alias.

Layout of the admin screens and real browser behavior are still checked by hand.

## Releasing

Sites update the plugin from Dashboard → Updates: `includes/updater.php` (Plugin Update Checker, vendored in `lib/plugin-update-checker/`) reads the GitHub releases of `Brukols/lcmt-dev-mailer` and installs the `lcmt-dev-mailer.zip` attached to the latest one.

1. Bump the version in the plugin header (`lcmt-dev-mailer.php`) **and** `package.json`.
2. **Write `release-notes/X.Y.Z.md` in English** (owner's instruction, 2026-10-08): what changed since the last release, written for the people running the plugin rather than as a commit list, with an "Updating" section. It becomes the GitHub release body and is left out of the zip.
3. Run `phpunit` and `yarn build`, and commit `assets/dist/` if it changed.
4. Tag and push: `git tag v2.1.0 && git push && git push --tags`.

`.github/workflows/release.yml` then checks that the tag matches both versions and that `assets/dist/` is up to date, builds the zip with `git archive` (files marked `export-ignore` in `.gitattributes` are left out) and publishes the release, described by `release-notes/X.Y.Z.md` (falls back to notes generated from the commits). Sites see the update within 12 hours, or at once with "Check for updates" on the Plugins screen.

