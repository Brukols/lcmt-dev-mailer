# LCMT Mailer

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

A value passed under the same name to `Mailer::sendByKey()` wins. "Send the email again" on a received message passes them empty: the admin resending it is not the visitor.

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
- `403` — Spam protection check failed
- `422` — Validation failed (missing required field, or a value that does not match its type)

A `tel` value holds 6 to 20 digits, an optional leading `+`, and any spaces, dots, dashes, slashes or brackets: `+33 6 12 34 56 78`, `06.12.34.56.78` and `+33 (0)6 12 34 56 78` all pass.
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

## Spam protection

Pick the protection in **Email templates → Spam protection**. ALTCHA is selected by default and needs no setup: its HMAC key is generated on first use and stored in the `lcmt_mailer_altcha_key` option. A **Generate a new key** button replaces it if it may have leaked.

A key defined in `wp-config.php` takes precedence over the stored one:

```php
define('ALTCHA_HMAC_KEY', 'a-long-random-string');
```

Each solved challenge is accepted once, then refused until it expires (5 minutes). The widget solves a new one when it expires, before sending when the visitor comes back from a sleeping computer, and after each answer from the server, so a visitor can take as long as they need and send the form again after fixing a field.

To add another protection, implement `LcmtDevMailer\CaptchaProvider` and register it:

```php
add_filter('lcmt_mailer_captcha_providers', function (array $providers) {
    $providers['my-captcha'] = MyCaptcha::class;
    return $providers;
});
```

---

## Received messages

Every message sent through a form is saved on your site before the email goes out, so nothing is lost when an email fails.

- **Where:** **Email templates → Received messages** (unread count in the menu). Read a message, mark it processed, unread or spam, delete it, filter by form, status, source or failed email, and export the list as CSV. **Statistics** shows where messages come from (month, source, campaign, form, page, device).
- **Per form:** each mail template has a **Save the messages sent through this form** checkbox, on by default. Untick it for forms that must not be stored.
- **Failed emails:** if an email cannot be sent, the message stays saved, an admin banner and a dashboard widget show the reason, and **Send the email again** resends it.
- **Retention:** **Email templates → Data retention**. By default, personal data is kept 1095 days (3 years, the longest the CNIL accepts for prospects), then **anonymized**: what the visitor typed is erased, while the date, form, page and source stay for statistics, kept forever unless you set a period. You can delete whole messages instead. A daily task applies it; **Purge now** runs it at once. State the period in your privacy policy: `[lcmt-retention-days]` prints it, and the plugin adds text to the WordPress privacy policy guide.
- **Origin of the visit:** the landing page, campaign parameters (UTM), the name of an ad click id (never its value) and the host of the referring site are remembered in the visitor's browser for the visit only (sessionStorage). With a consent tool compatible with **WP Consent API**, this only happens once the visitor accepts the *statistics* category. Without one, it is done by default, which needs consent under the ePrivacy rules: untick the option in Data retention → Advanced settings to only record the page the form was sent from. IP addresses are never stored.
- **Privacy tools:** messages are included in Tools → Export Personal Data and Erase Personal Data (erasing anonymizes). They are found by an **exact** email value in a form field: an address that only appears inside a free-text message is not matched, so search for it in Received messages if you need to handle such a request.
- **Uninstalling:** deactivating or updating the plugin keeps the messages. **Deleting the plugin deletes the messages** and its settings.

For developers: hooks `lcmt_mailer_submission_channel`, `lcmt_mailer_submissions_capability` and `lcmt_mailer_submission_actions` are described in [.claude/hooks.md](.claude/hooks.md).

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

## Updates

Sites pick up new versions from Dashboard → Updates, like any wordpress.org plugin: the plugin checks the GitHub releases of this repository. See [.claude/build.md](.claude/build.md#releasing) to publish one.

## Build

The plugin uses esbuild to minify JS assets.

```bash
nvm use 20
yarn install
yarn build    # one-time build
yarn watch    # watch mode
```

Source files are in `assets/src/`, output in `assets/dist/`.
