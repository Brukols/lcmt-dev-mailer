# Frontend

## Form rendering

### Shortcode
```
[lcmt-form key="contact"]
```

### PHP
```php
echo \LcmtDevMailer\FormRenderer::render('contact');
```

Both methods:
1. Find the mail post by key
2. Load `{theme}/forms/{key}.php`
3. Wrap with `<form>` (or inject attributes into an existing `<form>` tag)
4. Enqueue `form-handler.js`

### Form file convention

The developer creates `{theme}/forms/{key}.php` with the form markup.

- If the file has no `<form>` tag, the plugin wraps it
- If the file already has a `<form>` tag, the plugin injects `id`, `data-lcmt-endpoint`, and `data-lcmt-nonce` into it
- Each input must have a `name` attribute matching the placeholder name
- Add `required` on inputs that correspond to `[field*]` placeholders

### Auto-generated template

The "Generate form template" button in the admin creates `forms/{key}.php` with:
- A `<label>` + `<input>` (or `<textarea>`) for each parsed field
- Correct `type` attributes from the placeholder syntax
- `required` attribute on required fields
- A submit button
- A `[data-lcmt-status]` element for status messages

## form-handler.js

Vanilla JS (no dependencies), auto-loaded when a form is rendered. Located at `assets/src/form-handler.js`.

### How it works

1. On DOMContentLoaded, discovers all elements with `[data-lcmt-endpoint]`
2. Binds a `submit` event listener to each form
3. On submit:
   - Collects all `[name]` inputs
   - Validates `required` inputs and `type="email"` inputs client-side
   - Makes sure the ALTCHA proof is still valid: when it is missing or expires within 30 s (the widget's own refresh timer stops while the computer sleeps), the widget solves a new challenge before sending
   - Sends `fetch POST` with JSON body to the endpoint
   - Handles success/error states
   - Resets the ALTCHA widget after every answer: the server spends a proof as soon as it reads it, even when it then refuses a field, so a corrected form needs a new one

### CSS classes on `<form>`

| Class                    | When                          |
|--------------------------|-------------------------------|
| `lcmt-form--submitting`  | Request in progress           |
| `lcmt-form--success`     | Email sent, form reset        |
| `lcmt-form--error`       | Validation or sending failed  |

### CSS class on inputs

| Class                | When                              |
|----------------------|-----------------------------------|
| `lcmt-field--error`  | Input failed validation           |

The class is removed on the next `input` event.

### Status element

Add `data-lcmt-status` to any element inside the form:

```html
<div data-lcmt-status></div>
```

The attribute value is set to `submitting`, `success`, or `error`. The text content is set to the server's message on error.

### Styling example

```css
.lcmt-form--submitting button[type="submit"] {
  opacity: 0.5;
  pointer-events: none;
}

.lcmt-field--error {
  border-color: red;
}

[data-lcmt-status="success"]::after {
  content: "Message sent!";
  color: green;
}

[data-lcmt-status="error"]::after {
  content: "Something went wrong.";
  color: red;
}
```

## Attribution

`assets/src/attribution.js` is enqueued on every public page (`Attribution::enqueue()`) and remembers where the visit started, so a form sent several pages later still knows the ad or search that brought the visitor. The logic lives in `assets/src/lib/attribution.js` (shared with `form-handler.js`, unit-tested).

- **Stored in** `sessionStorage`, key `lcmtMailerLanding` (gone when the tab closes): landing path, referrer **origin** (scheme + host, never its path or query), `utm_source` / `utm_medium` / `utm_campaign`, and the click id **name** (`gclid`, `fbclid`…), never its value.
- **A new visit** (campaign parameters, click id, or a link from another site) replaces the stored landing.
- **Consent:** with WP Consent API installed (`wp_has_consent`), the landing is only stored once the `statistics` category is allowed, including when the visitor accepts later (`wp_listen_for_consent_change`; until then it is held in memory). When the server reports WP Consent API as active (`lcmtMailerAttribution.consentApi`) but `wp_has_consent` is not defined yet, the script treats it as "not yet consented" and never falls back to the site setting. Without WP Consent API, the `lcmt_mailer_attribution_without_consent` option (Data retention → Advanced settings, default on) decides, exposed as `lcmtMailerAttribution.storeWithoutConsent`.
- **Without a stored landing** the current page stands in for it.

### `_context` object

`form-handler.js` adds a reserved `_context` key to the JSON body (do not name a form field `_context`):

```json
{ "page": "/contact", "landing": { "path": "/", "referrer": "https://www.google.com", "utm_source": "…", "click_id": "gclid" },
  "device": "desktop", "locale": "fr-FR", "seconds": 42 }
```

`seconds` is the time between the first interaction with the form and the send. The server trusts none of it: `SubmissionContext::fromRequest()` validates and truncates each value.

## REST API

### Endpoint
```
POST /wp-json/lcmt-mailer/v1/forms/{key}
```

### Request
```json
{
  "firstname": "John",
  "lastname": "Doe",
  "email": "john@example.com",
  "message": "Hello!"
}
```

Headers:
- `Content-Type: application/json`
- `X-WP-Nonce: {nonce}` (provided via `data-lcmt-nonce`)

### Responses

**200 — Success:**
```json
{ "success": true, "message": "Email sent successfully." }
```

**403 — Spam protection failed:** the selected captcha rejected the proof (missing, invalid, expired or already used).

**422 — Validation error:** a required field is empty, or a value does not match its type (`email`, `number`, `url`, `tel`; see `FieldValidator`). A field used in To or Reply-To must hold a single valid email address, whatever its declared type.
```json
{
  "success": false,
  "message": "Validation failed.",
  "errors": ["The field \"firstname\" is required."]
}
```

**404 — Unknown key:**
```json
{ "success": false, "message": "Unknown form." }
```

**500 — Send failure:**
```json
{ "success": false, "message": "Failed to send email." }
```
