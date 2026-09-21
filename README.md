# LCMT Dev Mailer

Developer-oriented WordPress mail engine. Create email templates in the admin, get auto-generated REST endpoints, form rendering, validation, and TypeScript types.

---

## Quick start

1. Activate the plugin
2. Go to **Mails > Add New**
3. Set a **Key** (e.g. `contact`)
4. Fill in **To**, **Subject** and **Content** using placeholders
5. Click **Generate form template** in the sidebar
6. Use the shortcode or PHP call in your page

---

## Placeholder syntax

Placeholders define the form fields. Write them in the **To**, **Subject** or **Content** fields.

```
[name]            Optional field, type defaults to text
[name*]           Required field, type defaults to text
[name type]       Optional field with explicit type
[name* type]      Required field with explicit type
```

### Supported types

| Type       | HTML output              | Alias  |
|------------|--------------------------|--------|
| `text`     | `<input type="text">`    |        |
| `email`    | `<input type="email">`   |        |
| `tel`      | `<input type="tel">`     | `phone`|
| `url`      | `<input type="url">`     |        |
| `number`   | `<input type="number">`  |        |
| `password` | `<input type="password">`|        |
| `date`     | `<input type="date">`    |        |
| `textarea` | `<textarea>`             |        |
| `hidden`   | `<input type="hidden">`  |        |

### Example

**Subject:**
```
New contact from [firstname*] [lastname*]
```

**Content:**
```
Email: [email* email]
Phone: [phone tel]
Company: [company]

Message:
[message* textarea]
```

This defines 5 fields: `firstname` (required, text), `lastname` (required, text), `email` (required, email), `phone` (optional, tel), `company` (optional, text), `message` (required, textarea).

---

## Built-in placeholders

These are always available and don't need to be in the content:

| Placeholder          | Value                                    |
|----------------------|------------------------------------------|
| `[currentUserLink]`  | Admin link to the current logged-in user |
| `[currentUserEmail]` | Email of the current logged-in user      |

---

## Usage

### Shortcode

```
[lcmt-form key="contact"]
```

### PHP

```php
echo \LcmtDevMailer\FormRenderer::render('contact');
```

### REST API

```
POST /wp-json/lcmt-mailer/v1/forms/{key}
Content-Type: application/json

{
  "firstname": "John",
  "lastname": "Doe",
  "email": "john@example.com",
  "message": "Hello!"
}
```

**Responses:**

- `200` — Email sent successfully
- `422` — Validation failed (missing required fields)
- `404` — Unknown form key
- `500` — Email sending failed

### Direct PHP call (without form)

```php
\LcmtDevMailer\Mailer::sendByKey('contact', [
    'firstname' => 'John',
    'lastname'  => 'Doe',
    'email'     => 'john@example.com',
    'message'   => 'Hello!',
]);
```

---

## Form template

The form file lives in your theme at:

```
theme/forms/{key}.php
```

Write your HTML **without** the `<form>` tag — the plugin wraps it automatically with the correct endpoint and nonce.

You can also include a `<form>` tag yourself — the plugin will inject its `data-lcmt-endpoint` and `data-lcmt-nonce` attributes into it.

### Auto-generated template

Click **Generate form template** in the admin sidebar to create the file with pre-built labels and inputs matching your placeholders.

### Required attributes

Each input **must** have a `name` attribute matching the placeholder name:

```html
<input type="text" name="firstname" required />
<input type="email" name="email" required />
<textarea name="message" required></textarea>
```

Add `required` on inputs that match `[field*]` placeholders for client-side validation.

### Status element

Add an element with `data-lcmt-status` to display status messages:

```html
<div data-lcmt-status></div>
```

The attribute value will be set to `submitting`, `success`, or `error`.

### CSS classes

The plugin adds these classes to the `<form>` during submission:

| Class                    | When                          |
|--------------------------|-------------------------------|
| `lcmt-form--submitting`  | Request in progress           |
| `lcmt-form--success`     | Email sent successfully       |
| `lcmt-form--error`       | Validation or sending failed  |
| `lcmt-field--error`      | Added to invalid input fields |

---

## Filters

### `lcmt_mailer_default_language`

Default language for querying mail posts (default: `fr`).

```php
add_filter('lcmt_mailer_default_language', function () {
    return 'en';
});
```

### `lcmt_mailer_from_email`

Override the "from" email address.

```php
add_filter('lcmt_mailer_from_email', function () {
    return 'noreply@example.com';
});
```

### `lcmt_mailer_headers`

Modify email headers before sending.

```php
add_filter('lcmt_mailer_headers', function (array $headers, array $form) {
    $headers[] = 'Cc: copy@example.com';
    return $headers;
}, 10, 2);
```

### `lcmt_mailer_user_language`

Resolve a recipient's preferred language for Polylang translation.

```php
add_filter('lcmt_mailer_user_language', function ($language, $email) {
    // Return a language slug like 'en', 'fr', or null to skip
    return get_user_preferred_language($email);
}, 10, 2);
```

---

## Actions

### `lcmt_mailer_before_send`

Fired before the email is sent from the REST endpoint.

```php
add_action('lcmt_mailer_before_send', function (string $key, array $placeholders, \WP_Post $post) {
    // Log, validate, rate-limit, etc.
}, 10, 3);
```

### `lcmt_mailer_after_send`

Fired after a successful send from the REST endpoint.

```php
add_action('lcmt_mailer_after_send', function (string $key, array $placeholders, \WP_Post $post) {
    // Notify, track, etc.
}, 10, 3);
```

---

## Email template override

The HTML email wrapper can be overridden in your theme:

```
theme/lcmt-dev-mailer/mail-base.php
```

Available variables: `$args['subject']` and `$args['content']`.

The plugin also fires the `lcmt_mailer_before_content` action inside the default template to inject a logo header.

---

## Build

The plugin uses esbuild to minify JS assets.

```bash
nvm use 20
yarn install
yarn build    # one-time build
yarn watch    # watch mode
```

Source files are in `assets/src/`, output in `assets/dist/`.
