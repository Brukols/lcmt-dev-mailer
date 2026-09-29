# Build

## Setup

```bash
nvm use 20
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
| `src/form-handler.js`           | `dist/form-handler.js`           | Frontend | Auto form submit, validation, fetch|
| `src/attribution.js`            | `dist/attribution.js`            | Frontend | Remembers the landing page of the visit |

`src/lib/` holds shared modules (`lib/attribution.js`): imported by entry points, not emitted on their own since the build only globs `src/*.js`.

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

**PHP unit tests** (`tests/*.php`) run without WordPress: `tests/bootstrap.php` stubs the few functions the tested classes call. They cover `FieldParser`, `FieldValidator`, `Altcha`, `SubmissionData`, `SubmissionContext`, `ChannelClassifier`, `SubmissionCsv` and `Retention` (pure logic). Needs PHPUnit 11 (`brew install phpunit`).

```bash
phpunit
```

**JS tests** (`tests/js/`) use the Node test runner, bundled with esbuild (no extra dependency): `attribution.test.js` (the shared module) and `attribution-entry.test.js` (the entry point, with a stubbed browser from `env.js`).

```bash
yarn test:js
```

**Integration tests** (`tests/integration/`) run inside WordPress with wp-cli, against a local site with the plugin active. Sync the plugin into the site's `wp-content/plugins/lcmt-dev-mailer/` (rsync, excluding `.git`, `node_modules`, `docs`, `tests`), then from the site directory:

```bash
wp eval-file /path/to/lcmt-dev-mailer/tests/integration/run.php
```

`run.php` loads every `*-test.php` file: repository, recorder, attribution, submissions page, resend and CSV export, retention, privacy tools, failure notice and stats page. Each test runs in a transaction that is rolled back, so nothing is left in the database (never run DDL in a test: it commits implicitly). Shared helpers live in `tests/integration/helpers.php` (they also keep any email from leaving the site: `pre_wp_mail` is filtered). Use a local or staging site, never production.

Layout of the admin screens and real browser behavior are still checked by hand.

## Releasing

Sites update the plugin from Dashboard → Updates: `includes/updater.php` (Plugin Update Checker, vendored in `lib/plugin-update-checker/`) reads the GitHub releases of `Brukols/lcmt-dev-mailer` and installs the `lcmt-dev-mailer.zip` attached to the latest one.

1. Bump the version in the plugin header (`lcmt-dev-mailer.php`) **and** `package.json`.
2. Run `phpunit` and `yarn build`, and commit `assets/dist/` if it changed.
3. Tag and push: `git tag v2.1.0 && git push && git push --tags`.

`.github/workflows/release.yml` then checks that the tag matches both versions and that `assets/dist/` is up to date, builds the zip with `git archive` (files marked `export-ignore` in `.gitattributes` are left out) and publishes the release. Sites see the update within 12 hours, or at once with "Check for updates" on the Plugins screen.

