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

## Releasing

Sites update the plugin from Dashboard → Updates: `includes/updater.php` (Plugin Update Checker, vendored in `lib/plugin-update-checker/`) reads the GitHub releases of `Brukols/lcmt-dev-mailer` and installs the `lcmt-dev-mailer.zip` attached to the latest one.

1. Bump the version in the plugin header (`lcmt-dev-mailer.php`) **and** `package.json`.
2. Run `yarn build` and commit `assets/dist/` if it changed.
3. Tag and push: `git tag v2.1.0 && git push && git push --tags`.

`.github/workflows/release.yml` then checks that the tag matches both versions and that `assets/dist/` is up to date, builds the zip with `git archive` (files marked `export-ignore` in `.gitattributes` are left out) and publishes the release. Sites see the update within 12 hours, or at once with "Check for updates" on the Plugins screen.

