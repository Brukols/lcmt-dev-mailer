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
   - Sends `fetch POST` with JSON body to the endpoint
   - Handles success/error states

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

**422 — Validation error:**
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
