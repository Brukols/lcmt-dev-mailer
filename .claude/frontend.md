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
3. Place the spam protection widget (see "Spam protection widget" below), then append the loader overlay
4. Wrap with `<form>` (or inject attributes into an existing `<form>` tag)
5. Enqueue `form-handler.js`

### Form file convention

The developer creates `{theme}/forms/{key}.php` with the form markup.

- If the file has no `<form>` tag, the plugin wraps it
- If the file already has a `<form>` tag, the plugin injects `id`, `data-lcmt-endpoint`, and `data-lcmt-nonce` into it
- Each input must have a `name` attribute matching the placeholder name
- Add `required` on inputs that correspond to `[field*]` placeholders

### Spam protection widget

`FormRenderer::placeWidget($html, $widget)` (pure, tested) puts the markup of the template's protection (`Captcha::widget()`) in the form file's HTML:

1. In place of the first `FormRenderer::CAPTCHA_SLOT` (`<!--lcmt-captcha-->`), printed by `<?php lcmt_mailer_captcha(); ?>` where the form file wants it; further slots are removed.
2. Otherwise right before the first submit control: `<button>` without a `type` or with `type="submit"`, or `<input type="submit">` (any case, single, double or no quotes). `type="button"` / `"reset"` are skipped.
3. Otherwise at the end of the markup.

With no active protection the widget is empty and the slots are removed. Calling `lcmt_mailer_captcha()` is only needed to put the widget somewhere other than before the button; guard it with `function_exists()` in a theme that may run without the plugin or with a version older than 2.7.0. ALTCHA is always invisible (it solves its proof in the background), and Turnstile with the default appearance ("Only when Cloudflare needs the visitor") shows nothing unless Cloudflare asks for an interaction: the slot usually stays empty on screen. The widget keeps its `z-index` above the loader only if no wrapper between it and the `<form>` creates a stacking context of its own.

### Auto-generated template

The "Generate form template" button in the admin creates `forms/{key}.php` with:
- A `<label>` + `<input>` (or `<textarea>`) for each parsed field
- Correct `type` attributes from the placeholder syntax
- `required` attribute on required fields
- A submit button
- A `[data-lcmt-status]` element for status messages

## form-handler.js

Vanilla JS (no jQuery), auto-loaded when a form is rendered. Located at `assets/src/form-handler.js`.

### How it works

1. On DOMContentLoaded, discovers all elements with `[data-lcmt-endpoint]`
2. Binds a `submit` event listener to each form
3. On submit:
   - Collects all `[name]` inputs
   - Validates `required` inputs and `type="email"` inputs client-side
   - Asks the form's spam protection for a token (see "Spam protection adapters" below); the ALTCHA adapter keeps the current proof unless it is missing or expires within 30 s (the widget's own refresh timer stops while the computer sleeps), else solves a new challenge before sending
   - Sends `fetch POST` with JSON body to the endpoint; on a multilingual site (`lcmtMailerFront.lang` set from `pll_current_language()`), `lib/request-url.js` adds `?lang=<slug>` so the answer and the mail use the page language
   - Handles success/error states: on success, fires `lcmt:success` on the form, then goes to the form's confirmation page when the answer has a `redirect` URL, else shows the success message and resets the form (see below)
   - Resets the spam protection after every answer: the server spends a token as soon as it reads it, even when it then refuses a field, so a corrected form needs a new one

### Spam protection adapters

Each protection has its own script, `assets/src/captcha-{id}.js` (built to `assets/dist/`), enqueued by its PHP provider's `enqueueAssets()` (`CaptchaAssets`) only on pages with a form it guards. `form-handler.js` knows no provider: it goes through `lib/captcha/registry.js`.

- **Contract** (`CaptchaAdapter`): `id` (same as the PHP provider), `field` (body key the server reads the token from), `token(widget) → Promise<string>`, `reset(widget)` (called after every server answer). `defineAdapter()` checks it (TypeError naming the missing member) and returns a frozen copy.
- **Registration:** `registerAdapter(adapter)` stores it in `window.lcmtMailerCaptchas[id]` and fires `lcmt:captcha-ready` (`detail.id`) on `document`, so the separate bundles share it.
- **Widget:** the form's `[data-lcmt-captcha="{id}"]` element, or a legacy `<altcha-widget>` without the attribute (id `altcha`).
- **On submit**, `captchaToken(form, window, timeoutMs, loadAdapter)` waits up to 30 s for an adapter not registered yet. A full-page cache can serve HTML rendered before the adapters, without the `<script>` of `captcha-{id}.js`: `loadAdapter(id)` (`lib/captcha/script-loader.js`) is then called once per id and appends `<script src="{dist}/captcha-{id}.js" defer>`, `{dist}` being the folder of `form-handler.js` (`document.currentScript`).
- **Limit of the on-demand loader:** it finds the folder of `form-handler.js` through `document.currentScript`. Script optimisers that combine, delay or defer scripts (WP Rocket, Autoptimize, LiteSpeed…) can make `document.currentScript` null; the loader then has no base URL and does nothing, so a legacy cached page (HTML printed before 2.7.0, without `captcha-{id}.js`) waits for the 30 s timeout and the visitor sees the spam protection error. Pages rendered by 2.7.0 or later enqueue the adapter script themselves and are not affected; purge the page cache after updating.
- **ALTCHA** (`captcha-altcha.js`): bundles `altcha`, copies the `challengeurl` of a widget cached with the 2.7 markup into `challenge` (the v3 attribute) and cache-busts it on load, empties the widget's `waitAlert` string for every language, and registers `{ id: 'altcha', field: 'altcha' }` on the next task (the widget mounts a microtask after its element is defined).

- **Turnstile** (`captcha-turnstile.js`, `lib/captcha/turnstile-widget.js`): registers `{ id: 'turnstile', field: 'turnstile' }`. The widget (`[data-lcmt-captcha="turnstile"]`, with `data-sitekey`, `data-action`, `data-appearance`) is rendered explicitly. Cloudflare's `api.js` (`?render=explicit&onload=lcmtTurnstileReady`) is **lazy-loaded on the visitor's first `focusin` in the form** (or at submit if no field was focused), once per page, so a page that only shows a form never contacts Cloudflare. Exception: a widget with `data-appearance="always"` renders as soon as it comes within 200px of the viewport (`IntersectionObserver`, or right away without it), so it does not appear mid-typing and push the submit button; the Turnstile privacy text says "when a form is displayed or when you start filling it in" for that reason. The language is `<html lang>` lower-cased (`fr-fr`), the form Turnstile expects. The token is kept in memory (no hidden input) and sent as `turnstile`; `reset(widget)` asks for a new one. If `api.js` fails to load (blocked, offline) the next submit tries again.
- **Adding an adapter:** build `assets/src/captcha-{id}.js`, call `registerAdapter(defineAdapter({ id, field, token, reset }))`, and enqueue it from the PHP provider's `enqueueAssets()`.

### Success event and confirmation page

On a successful answer, `lib/form-success.js` (`handleSuccess()`, unit-tested) first dispatches a **`lcmt:success`** `CustomEvent` on the `<form>`: it bubbles, is cancelable, and its `detail` is the JSON answer (`{ success, message, redirect? }`). It fires before the form is reset, so listeners can still read the fields.

Then:
- **With a `redirect` URL** (the form has a Confirmation page, or the `lcmt_mailer_confirmation_url` filter set one): the form is reset and the browser goes there with `window.location.assign()`; no success message. The form stays busy (`lcmt-form--submitting`, loader) while the page changes, and is freed on `pageshow` if the browser restores the page from its back/forward cache. Only `http(s)://…` URLs and site paths (`/…`, not `//…`) are followed; the server already allows only URLs of the site.
- **Without one**, or when a listener called `preventDefault()`: the success message and form reset, exactly as before.

```js
document.addEventListener('lcmt:success', function (e) {
  // e.target is the form, e.detail the answer of the endpoint.
  window.dataLayer && window.dataLayer.push({ event: 'form_sent', form: e.target.id });

  // Stay on the page (message + reset) instead of going to the confirmation page:
  // e.preventDefault();
});
```

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

## Admin scripts

- **`admin-stats.js`** (Statistics page only): `lib/stats-view.js` restores each box's Chart | Table choice from `localStorage` (`lcmt-stats-view:<box id>`, wrapped in try/catch: without storage the toggle works and forgets), reveals the toggle buttons (`[data-lcmt-toggle]`, hidden by default) and switches the `[data-lcmt-view="chart|table"]` panels on a click on `[data-lcmt-set-view]`, keeping `aria-pressed` in step.
- **`admin-uninstall.js`** (plugins.php): `lib/uninstall-prompt.js` runs the Deactivate flow, `lib/deactivate-dialog.js` drives the `<dialog>` printed by `Uninstaller::printDialog()` (`[data-lcmt-delete]`, `[data-lcmt-help]` with `data-help-on` / `data-help-off`, `[data-lcmt-cancel]`, `[data-lcmt-confirm]` with `data-busy-label`).
- **`admin-privacy-docs.js`** (Data retention page only): `lib/copy-button.js` wires every `[data-lcmt-copy]` button. The text comes from the attribute, is copied with `navigator.clipboard.writeText()` and, when that is missing or rejects, with a hidden textarea and `execCommand('copy')`. On success the button shows its `data-copied` label for 1.5 s and the `[data-lcmt-copy-status]` polite live region announces it; a failed copy changes nothing.
- **Chart colors:** one hue, WordPress admin blue `#2271b1` (over 3:1 on white) for marks; text stays in the normal ink colors; grid lines and axes are light gray (`#dcdcde`). No legend for a single series: the box title names it.

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

When the form has a confirmation page (or `lcmt_mailer_confirmation_url` returns a URL), the answer also carries its URL, already checked to be on the site:
```json
{ "success": true, "message": "Email sent successfully.", "redirect": "https://example.com/thank-you/" }
```

**403 — Spam protection failed:** the form's captcha rejected the proof (missing, invalid, expired or already used), or its verification service could not be reached while the site refuses in that case. When the fields are valid, the message is kept in Received messages → Spam with its reason (see [Architecture](architecture.md)).

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

**200 after a failed send:** when the message was saved in Received messages, a failed email still answers `200` with the normal success message, so the visitor does not send it twice; the admin sees it in the failure banner.

**500 — Send failure (message not saved):**
```json
{ "success": false, "message": "Failed to send email." }
```
