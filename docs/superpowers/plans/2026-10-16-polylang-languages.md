# Polylang languages Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** A form submitted from a page in another language is answered in that language (messages, success message) and its mail is sent in that language when the mail has a translation, else in the default language; the plugin works the same with or without Polylang (or WPML).

**Architecture:** The plugin declares what is translatable the standard way, in a `wpml-config.xml` at its root, read by Polylang and WPML: the `mail` post type is translatable, its subject and content are fields to translate, its key, recipients and storage flag are copied, and the success-message option is an admin text. The front-end script sends the page language as the REST `lang` parameter, which Polylang ≥ 3.8 uses to set the request language; the endpoint switches the locale to it for its messages. The mail takes the subject and content of the mail post's translation in the submission language when one is published, through the existing `maybeTranslateForRecipient()` path, whose language now defaults to the submission language. A built-in placeholder `[submissionLanguage]` gives that language in the mail. The translation plugin (Polylang AI Translate) then translates mail posts like any other content, with no code naming this plugin; this plugin only declares its placeholders as protected tokens through that plugin's generic filter, which does nothing when it is absent.

**Tech Stack:** PHP 8.1, WordPress, vanilla JS bundled with esbuild (`yarn build`), PHPUnit, node tests (`yarn test:js`).

**Context:** Requested by the owner on 2026-10-16 and refined on 2026-10-18 for Twin 3D (French source, English translation): mails from English pages must be sent in English; if a mail has no translation, it is sent in French.

## Global Constraints

- Follow this repo's `CLAUDE.md`: no jQuery, vanilla JS rebuilt with `yarn build` after editing `assets/src/`, kebab-case files, PascalCase classes, namespace `LcmtDevMailer`, theme-specific logic through `lcmt_mailer_*` filters, no ACF dependency.
- Polylang and WPML are optional: every call is guarded (`function_exists`); without them, behaviour is exactly as today (same mail, same messages, no `lang` sent).
- Backward compatibility: the `lcmt_mailer_user_language` filter keeps its signature and still wins when it returns a value.
- Work on the branch `feature/polylang-languages` (owner, 2026-10-18), never on `main`; merging is the owner's call.
- Commits: Conventional Commits in English (`feat(i18n): …`), no gitmoji or emoji even though older history uses them, no Claude attribution. Do not push or tag without the owner's go-ahead (the release is published from the tag).
- No new runtime dependency.

## Polylang settings on the site

Nothing to change by hand: the `wpml-config.xml` makes Polylang treat `mail` as translatable (it appears ticked and locked in Languages → Settings → Custom post types) and copy the technical fields to new translations. The success message is translated once in Languages → Translations (group "lcmt-dev-mailer" or the plugin's name). Keep Languages → Settings → Synchronisation → "Custom fields" off (it is off on Twin 3D): when on, Polylang would copy the French subject and content over the English ones.

## Review Focus

- A mail post whose translation exists but is a draft: the mail falls back to the default language (only a published translation is used).
- A `lang` value that is not a site language (tampered request): Polylang falls back to the default language; the endpoint never switches to an arbitrary locale.
- A site without Polylang: no `lang` in the request, no locale switch, the mail is the default one, `[submissionLanguage]` is empty.
- A translated mail whose content lost a placeholder (bad translation): the field is then missing from the generated form/validation of that language — the translated mail's placeholders must equal the source's, otherwise the source mail is used and a warning is logged.
- `restore_current_locale()` always runs, even when the handler returns an error early.

---

### Task 1: `wpml-config.xml`

**Files:**
- Create: `wpml-config.xml`
- Test: `tests/WpmlConfigTest.php`

- [ ] **Step 1: Failing tests** in `WpmlConfigTest` (load the file with `simplexml_load_file`):
  - `test_mail_post_type_is_translatable`: `custom-types/custom-type[.="mail"]` has `translate="1"`.
  - `test_subject_and_content_are_translated`: `custom-fields/custom-field[@action="translate"]` contains `_lcmt_mail_subject` and `_lcmt_mail_content`.
  - `test_technical_fields_are_copied`: `[@action="copy"]` contains `_lcmt_mail_key`, `_lcmt_mail_to`, `_lcmt_mail_reply_to`, `_lcmt_mail_store_submissions`.
  - `test_success_message_is_an_admin_text`: `admin-texts/key[@name="lcmt_mailer_success_message"]` exists.
  - `test_meta_names_match_the_code`: every name above equals a constant of `MetaFields` (read the class constants), so a rename breaks the test.
- [ ] **Step 2: Run** the repo's phpunit command (`.claude/build.md`) with `--filter WpmlConfigTest`. Expected: FAIL, file missing.
- [ ] **Step 3: Create the file** with those entries (`<custom-types>`, `<custom-fields>`, `<admin-texts>`). Check `.gitattributes` does not export-ignore it.
- [ ] **Step 4: Run.** Expected: PASS.
- [ ] **Step 5: Commit** `feat(i18n): declare translatable content in wpml-config.xml`.

### Task 2: Submission language — request, locale, mail

**Files:**
- Create: `includes/request-language.php` (class `RequestLanguage`)
- Modify: `includes/form-renderer.php` (localized `lcmtMailerFront` data), `assets/src/form-handler.js` (REST URL), `includes/form-endpoint.php` (callback, `lang` arg), `includes/mailer.php` (`maybeTranslateForRecipient()`, built-in placeholders), `README.md`, `.claude/hooks.md`, `.claude/placeholder-syntax.md`
- Test: `tests/RequestLanguageTest.php`, `tests/MailLanguageTest.php`, `tests/js/form-handler-lang.test.js`

**Interfaces:**
- Produces: `RequestLanguage::locale(?string $requested, array $languages): ?string` — pure; `$languages` slug => locale; the locale of a known slug, else null.
- Produces: `RequestLanguage::switchFor(\WP_REST_Request $request): bool` — glue: with Polylang, when `lang` maps to a locale different from the current one, `switch_to_locale()` and return true.
- Produces: `RequestLanguage::current(): ?string` — the submission language slug: `pll_current_language()` when Polylang is active (set from `lang` by Polylang for REST requests), else null.
- Produces: `MailLanguage::pick(string $sourceSubject, string $sourceContent, ?array $translation): array{subject: string, content: string, translated: bool}` — pure; `$translation` is `{subject, content, published: bool}` or null; uses the translation only when published, non-empty, and its set of placeholder names (parsed with `FieldParser`) equals the source's; otherwise the source.

- [ ] **Step 1: Failing PHP tests:**
  - `RequestLanguageTest::test_known_language_gives_its_locale` (`locale('en', ['fr'=>'fr_FR','en'=>'en_GB'])` === `'en_GB'`), `test_unknown_or_empty_language_gives_null` (`'de'`, `''`, `null`).
  - `MailLanguageTest::test_published_translation_is_used`, `test_no_translation_falls_back_to_the_source`, `test_draft_translation_falls_back_to_the_source`, `test_translation_with_other_placeholders_falls_back_to_the_source` (source content `Nom : [fullname*]`, translation `Name: [name*]` → source, `translated` false), `test_empty_translated_subject_keeps_the_source_subject` (content translated, subject empty → subject from source, content from translation).
- [ ] **Step 2: Run them.** Expected: FAIL, classes missing.
- [ ] **Step 3: Implement** `RequestLanguage` and `MailLanguage` (new file `includes/mail-language.php`, registered like the other includes).
- [ ] **Step 4: Run them.** Expected: PASS.
- [ ] **Step 5: Endpoint.** Register `lang` (optional, `sanitize_key`); call `$switched = RequestLanguage::switchFor($request)` first and `restore_current_locale()` in a `finally` when switched.
- [ ] **Step 6: Mail.** In `maybeTranslateForRecipient()`: `$userLanguage = apply_filters('lcmt_mailer_user_language', RequestLanguage::current(), $to)` (default was `null`; the filter still overrides); resolve the translation with `pll_get_post($postId, $userLanguage)` and pass `{subject, content, published: get_post_status($id) === 'publish'}` to `MailLanguage::pick()`; log with `error_log` (no personal data, only post IDs) when a translation was refused for its placeholders. Add the built-in placeholder `submissionLanguage` (the language name from `pll_languages_list(['fields' => 'name'])` for the slug, or `''`), filled like `currentUserEmail` (only when the caller did not pass it).
- [ ] **Step 7: Front data.** `'lang' => function_exists('pll_current_language') ? (string) pll_current_language() : ''` in `lcmtMailerFront`.
- [ ] **Step 8: Failing JS test** `tests/js/form-handler-lang.test.js`: the request URL builder adds `lang=en` when `lcmtMailerFront.lang` is `'en'`, nothing when `''`, and keeps an existing query string (`?a=1` → `?a=1&lang=en`). Extract the builder into an exported function in `assets/src/` if needed (bundle entry unchanged).
- [ ] **Step 9: Run** `yarn test:js`. Expected: the new test FAILS. **Implement**, `yarn build`, run again. Expected: PASS.
- [ ] **Step 10: Make `[submissionLanguage]` easy to find** (owner, 2026-10-18): add it to the "Built-in placeholders (always available)" help under the mail content (`includes/meta-fields.php` around line 206) with a short explanation ("the language of the page the form was sent from, e.g. English; empty on a single-language site"); store the submission language with each received message (in the submission context, next to the existing context data) and show it as a "Language" column in the received messages list and in the message detail, only when the site has more than one language. Failing test first for the pure part (the context builder keeps a `language` entry; `SubmissionContextTest::test_records_the_submission_language`).
- [ ] **Step 10b: Docs.** README and `.claude/hooks.md`: `lcmt_mailer_user_language` now defaults to the submission language (Polylang/WPML), with the fallback rule; `.claude/placeholder-syntax.md`: `[submissionLanguage]`; README "Multilingual sites" section: what is declared in `wpml-config.xml`, translate the success message in Languages → Translations, translate mail posts like any post (by hand or with a translation plugin), keep placeholders identical, form templates use the theme's text domain.
- [ ] **Step 11: Run** the whole PHP suite and `yarn test:js`. Expected: PASS.
- [ ] **Step 12: Commit** `feat(i18n): answer and send mails in the submission language`.

### Task 3: Protected placeholders for translation plugins, default language

**Files:**
- Modify: `includes/mailer.php` (default language), a small hook registration file or the plugin bootstrap

- [ ] **Step 1:** `getPostByKey()` falls back to `pll_default_language()` when Polylang is active, instead of the literal `'fr'`; the `lcmt_mailer_default_language` filter still applies on top.
- [ ] **Step 2:** Register `add_filter('lcmt_pat/protected_patterns', fn(array $patterns) => [...$patterns, FieldParser::PATTERN])` (the parser's regex as a public constant; add the constant if the pattern is inline). The filter belongs to Polylang AI Translate (stage 6, Task 4) and does nothing when that plugin is absent; it keeps placeholders untouched when mail posts are translated by AI.
- [ ] **Step 3: Check on Twin 3D locally** (installed copy in `wp-content/plugins/lcmt-dev-mailer`, not versioned there): with the owner's go-ahead, back up that folder, copy the changed files over it, then:
  - `curl -s -X POST "https://twin-3d.test/wp-json/lcmt-mailer/v1/forms/contact?lang=en"` with an empty body → English validation message; `?lang=fr` → French; `?lang=xx` → default language.
  - With a temporary English translation of the "Contact" mail post (published, placeholders identical, subject "New contact request from [email]"), a valid English submission sends an English mail (read it in WP Mail Logging, which is installed); with the translation set to draft, the mail is French. Delete the temporary translation afterwards and the logged test mails.
  - Restore the backup unless the owner keeps the dev copy.
- [ ] **Step 4: Commit** `feat(i18n): take the default language from Polylang and protect placeholders from translation`.

### Task 4: Release

**Files:** `lcmt-dev-mailer.php` (Version), `package.json` (version), `release-notes/2.5.0.md`

- [ ] **Step 1:** Bump to `2.5.0`; write `release-notes/2.5.0.md` in English for site owners (multilingual sites: forms answer and mails are sent in the page language when the mail has a published translation, else in the default language; `[submissionLanguage]`; success message in Languages → Translations; nothing changes without Polylang/WPML).
- [ ] **Step 2: Commit** `chore(release): 2.5.0`.
- [ ] **Step 3: Stop.** Ask the owner before pushing and before tagging `v2.5.0`.
