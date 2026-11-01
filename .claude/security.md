# Security process

Same setup as the sites that use the plugin (ori, insho, twin-3d), 2026-11-01. Every tool is free on this private repository.

## What watches what
| Tool | Watches | When | Alert |
|------|---------|------|-------|
| Dependabot (`.github/dependabot.yml`) | yarn packages (ALTCHA, node-snackbar, esbuild), GitHub Actions | Weekly (Monday), 7-day cooldown on new releases | Security alert e-mail + update PRs |
| Semgrep (`.github/workflows/security.yml`, job `semgrep`) | Our code: `includes/`, `assets/src/`, `templates/`, `lcmt-dev-mailer.php`, `uninstall.php` (PHP, JS, secrets, XSS, security audit rule sets) | Every PR (new findings only, `--baseline-commit`), every push on `main`, Monday 06:00 UTC | Failed job e-mail, findings in the job log |
| Audit (same workflow, job `dependencies`) | Known vulnerabilities of the packages bundled into `assets/dist` (yarn `dependencies`) | Push on `main`, Monday | Failed job e-mail |
| Manual | `lib/plugin-update-checker` (vendored copy of YahnisElsts/plugin-update-checker, no package manager): check its releases before each plugin release | Before a release | — |
| Claude Security | A review of a branch before merging (`/claude-security`, "Scan changes") | On request | Report in `CLAUDE-SECURITY-<date>/` (git-ignored) |

## Rules
- **Bundled packages end up in `assets/dist`**, which is tracked: after a Dependabot PR on a `dependencies` package, rebuild (`nvm use 26 && yarn build`), run `yarn test:js`, and commit `assets/dist` with it.
- **esbuild is not audited by the blocking job**: it only runs on the computer and in CI. Dependabot updates it.
- **Semgrep image pinned by version and digest** (official image, Docker Hub org `semgrep`), the same as the sites. Update both together after the usual checks.
- **False positive**: `// nosemgrep: <rule id>` on the line, with the reason in a comment nearby. Never a global exclusion.
- **Every dependency PR is checked before merging**: origins and install scripts in the lockfile diff, age of every new version (transitive included, 7 days), build and tests (`phpunit`, `yarn test:js`), then merge (see ori's `.claude/security.md`, "Reviewing a Dependabot PR").
- **Secrets**: the Turnstile secret and the ALTCHA key are stored encrypted (`SecretBox`); never log or print them.
- **Every vulnerability is explained** to Amaury: what an attacker sends, the impact, why the code allowed it, the fix, and what would reopen it.

## Known limits
- **ALTCHA spends the challenge before the PBKDF2 step** (`Altcha::validatePayload()`), so a replayed proof costs no PBKDF2. The other side: a signed, fresh challenge sent with a made-up solution still costs one transient write (`USED_PREFIX` + nonce), which expires with the challenge (5 min) and is removed by WordPress's daily `delete_expired_transients`. Accepted: one write per challenge the attacker fetched.
- **The replay check is not atomic**: `get_transient()` then `set_transient()`. Parallel requests carrying the same valid proof could, rarely, both pass before the marker is written. Accepted for a contact form (each pass still needed a solved proof). Possible later fix: an atomic marker, `wp_cache_add()` with a persistent object cache, or an `INSERT IGNORE` into a table with a unique nonce.
