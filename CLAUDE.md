# LCMT Dev Mailer — Claude Instructions

Developer-oriented WordPress plugin for managing email templates with auto-generated forms, REST endpoints, validation, and TypeScript types.

## Quick reference

- **Entry point:** `lcmt-dev-mailer.php`
- **Namespace:** `LcmtDevMailer`
- **Build:** `nvm use 20 && yarn build` (esbuild)
- **JS source:** `assets/src/` — **JS dist:** `assets/dist/`
- **Theme override dir:** `{theme}/lcmt-dev-mailer/`
- **Theme form files:** `{theme}/forms/{key}.php`

## Detailed instructions

See the `.claude/` folder for topic-specific docs:

- [Architecture](.claude/architecture.md) — File structure, classes, and data flow
- [Placeholder syntax](.claude/placeholder-syntax.md) — How placeholders work in mail content
- [Filters & actions](.claude/hooks.md) — All available WordPress hooks
- [Frontend](.claude/frontend.md) — Form handler JS, CSS classes, and rendering
- [Build](.claude/build.md) — JS build pipeline and asset management

## Key rules

- No jQuery — all JS must be vanilla, minified via esbuild
- No ACF dependency — the plugin uses native WordPress meta boxes and `get_post_meta`
- File names in kebab-case
- PHP classes in PascalCase, namespace `LcmtDevMailer`
- Always rebuild JS after editing `assets/src/`: `yarn build`
- The plugin must remain self-contained and reusable across projects
- Theme-specific logic goes through filters (`lcmt_mailer_*`), never hardcoded in the plugin
