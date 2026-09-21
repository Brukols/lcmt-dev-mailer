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
