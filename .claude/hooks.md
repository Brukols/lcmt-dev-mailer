# Filters & Actions

## Filters

### `lcmt_mailer_default_language`
Default language slug for querying mail posts. Default: `'fr'`.

```php
add_filter('lcmt_mailer_default_language', function () {
    return 'en';
});
```

Used in: `Mailer::getPostByKey()` — passed as `lang` parameter to `get_posts()`.

### `lcmt_mailer_from_email`
Override the "from" email address. Default: empty (uses WordPress default).

```php
add_filter('lcmt_mailer_from_email', function () {
    return 'noreply@example.com';
});
```

Used in: `Mailer::buildForm()` — stored in the form array but currently the From header is built from `get_bloginfo('name')` + site domain. This filter value is available in the form array for custom use.

### `lcmt_mailer_headers`
Modify email headers before `wp_mail()` is called.

```php
add_filter('lcmt_mailer_headers', function (array $headers, array $form) {
    $headers[] = 'Cc: copy@example.com';
    return $headers;
}, 10, 2);
```

Used in: `Mailer::sendWithBaseTemplate()`.

### `lcmt_mailer_user_language`
Resolve a recipient's preferred language for Polylang translation. Return a language slug or `null` to skip.

```php
add_filter('lcmt_mailer_user_language', function ($language, $email) {
    $user = get_user_by('email', $email);
    return $user ? get_user_locale($user->ID) : null;
}, 10, 2);
```

Used in: `Mailer::maybeTranslateForRecipient()` — if a translated mail post exists in Polylang, subject and content are swapped.

### `lcmt_mailer_captcha_providers`
Register a spam protection. Keys are provider ids, values are classes implementing `LcmtDevMailer\CaptchaProvider`.

```php
add_filter('lcmt_mailer_captcha_providers', function (array $providers) {
    $providers['my-captcha'] = MyCaptcha::class;
    return $providers;
});
```

Used in: `Captcha::providers()`.

### `lcmt_mailer_submission_channel`
Change the channel a received message is counted under. Receives the classified channel (one of `ChannelClassifier::CHANNELS`) and the cleaned context columns.

```php
add_filter('lcmt_mailer_submission_channel', function (string $channel, array $context) {
    return $context['utm_source'] === 'partner' ? 'referral' : $channel;
}, 10, 2);
```

Used in: `SubmissionRecorder::record()`.

### `lcmt_mailer_submissions_capability`
Capability required to see received messages and statistics. Default: `manage_options`.

```php
add_filter('lcmt_mailer_submissions_capability', function () {
    return 'edit_pages';
});
```

Used in: `SubmissionsPage::capability()` (also the failure banner and Statistics).

### `lcmt_mailer_submission_actions`
Add or remove the action buttons of a message detail. Keys are actions, values labels; a custom action needs its own handler.

```php
add_filter('lcmt_mailer_submission_actions', function (array $buttons, array $submission) {
    unset($buttons['spam']);
    return $buttons;
}, 10, 2);
```

Used in: `SubmissionsPage::renderDetail()`.

## Actions

### `wp_mail_failed` (core, listened to)
`SubmissionRecorder::captureMailError()` keeps the error message of the last failed `wp_mail()` during a form send, stored in `mail_error`.

### `wp_consent_api_registered_{plugin}` (core filter of WP Consent API)
The plugin returns `true` to declare it follows the consent categories (attribution is stored only on `statistics` consent).

### `lcmt_mailer_before_send`
Fired before the email is sent from the REST endpoint.

```php
add_action('lcmt_mailer_before_send', function (string $key, array $placeholders, \WP_Post $post) {
    // Rate limiting, logging, spam check, etc.
}, 10, 3);
```

Used in: `FormEndpoint::handle()`.

### `lcmt_mailer_after_send`
Fired after a successful send from the REST endpoint. Not fired when the email failed, even when the visitor gets a 200 because the message was saved.

```php
add_action('lcmt_mailer_after_send', function (string $key, array $placeholders, \WP_Post $post) {
    // Analytics, notifications, CRM sync, etc.
}, 10, 3);
```

Used in: `FormEndpoint::handle()`.

### `lcmt_mailer_before_content`
Fired inside the default `mail-base.php` template, before the main content area. Use this to inject a logo header without overriding the entire template.

```php
add_action('lcmt_mailer_before_content', function (array $args) {
    echo '<div class="header-logo"><img src="..." /></div>';
}, 10, 1);
```

Used in: `templates/mail-base.php`.
