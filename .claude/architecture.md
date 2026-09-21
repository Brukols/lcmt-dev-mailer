# Architecture

## File structure

```
lcmt-dev-mailer/
├── lcmt-dev-mailer.php          # Bootstrap: defines constants, requires files, registers hooks
├── includes/
│   ├── post-type.php            # PostType — registers the `mail` CPT
│   ├── meta-fields.php          # MetaFields — native WP meta box (key, to, subject, content) + syntax reference panel
│   ├── field-parser.php         # FieldParser — parses [field* type] placeholders from content
│   ├── template-loader.php      # TemplateLoader — loads mail-base.php with theme override support
│   ├── mailer.php               # Mailer — core email engine (lookup by key, placeholder replacement, wp_mail)
│   ├── form-renderer.php        # FormRenderer — renders forms via shortcode or PHP, enqueues frontend JS
│   ├── form-endpoint.php        # FormEndpoint — REST API endpoint with auto-validation and sending
│   └── admin-mail-sender.php    # AdminMailSender — admin sidebar (usage info, field table, test email, generate template)
├── templates/
│   └── mail-base.php            # Default HTML email template (overridable in theme)
├── assets/
│   ├── src/                     # JS source files (vanilla, no jQuery)
│   │   ├── admin-test-mail.js
│   │   ├── admin-generate-template.js
│   │   └── form-handler.js
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
- Built-in placeholders: `[currentUserLink]`, `[currentUserEmail]`

### FormRenderer
- `render($key)` / shortcode `[lcmt-form key="..."]`
- Loads `{theme}/forms/{key}.php`
- Wraps with `<form>` + `data-lcmt-endpoint` + `data-lcmt-nonce` (or injects into existing `<form>` tag)
- Enqueues `form-handler.js` on first render

### FormEndpoint
- Registers `POST /wp-json/lcmt-mailer/v1/forms/{key}`
- Auto-validates required fields from parsed content
- Sends email via `Mailer::sendByKey()`
- Fires `lcmt_mailer_before_send` and `lcmt_mailer_after_send` actions

### AdminMailSender
Admin sidebar metabox with:
- Usage info: shortcode, PHP snippet, REST endpoint
- Field reference table (name, type, required)
- Form file status with "Generate form template" button
- TypeScript interface preview
- Test email sender

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

2c. Direct PHP call (no form):
    Mailer::sendByKey('contact', ['firstname' => 'John', ...])
    → same flow from buildForm() onwards
```
