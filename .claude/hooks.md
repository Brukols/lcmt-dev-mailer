# Filters & Actions

## Filters

### `lcmt_mailer_default_language`
Default language slug for querying mail posts. Default: Polylang's default language (`pll_default_language()`), else `'fr'`.

```php
add_filter('lcmt_mailer_default_language', function () {
    return 'en';
});
```

Used in: `Mailer::getPostByKey()` — passed as `lang` parameter to `get_posts()`. When nothing is found and Polylang is active, a published mail post with that key and **no language** is used (posts saved before `mail` became translatable), so forms keep working until languages are set.

### `lcmt_pat/protected_patterns` (filter of Polylang AI Translate, listened to)
`FieldParser::protectedPatterns($patterns, $objectType, $subtype)` adds `FieldParser::PATTERN` only for `post` / `mail`, so placeholders of mail posts stay untouched when that plugin translates them; other content is left alone. Does nothing when that plugin is absent.

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
The language a mail is sent in, with Polylang. Default: the submission language (`RequestLanguage::current()`: `pll_current_language()`, which Polylang 3.8+ sets from the `lang` parameter the form script adds to the REST URL), `null` without Polylang. Return a language slug, or `null` to send the default-language mail. The translation of the mail post in that language is used only when it is published and its placeholders (names and types) equal the source's (`MailLanguage::pick()`); otherwise the source mail is sent, and a placeholder mismatch is logged with `error_log` (post IDs only).

```php
add_filter('lcmt_mailer_user_language', function ($language, $email) {
    $user = get_user_by('email', $email);
    return $user ? get_user_locale($user->ID) : null;
}, 10, 2);
```

Used in: `Mailer::maybeTranslateForRecipient()` — subject and content come from the translation picked by `MailLanguage::pick()`. A resend from the admin runs inside `RequestLanguage::using()` with the stored language of the message.

### `lcmt_mailer_confirmation_url`
The URL a visitor is sent to after a successful submission through the REST endpoint. Default: the permalink of the form's **Confirmation page** (`_lcmt_mail_confirmation_page`), in the submission language when Polylang has a published translation of it, else `''`. Return `''` to show the success message instead. The filter runs on every successful submission, so it can also add a redirect to a form that has no confirmation page.

The returned URL then goes through `wp_validate_redirect()`: a URL of another site (unless allowed with core's `allowed_redirect_hosts` filter) or a non-http(s) scheme is dropped and the success message is shown, so the endpoint can never redirect elsewhere.

```php
add_filter('lcmt_mailer_confirmation_url', function (string $url, string $key, \WP_Post $post, int $pageId) {
    return $url ? add_query_arg('form', $key, $url) : $url;
}, 10, 4);
```

Used in: `ConfirmationPage::url()`, called by `FormEndpoint::handle()`; the result is the `redirect` key of the 200 answer. On the page, `form-handler.js` fires `lcmt:success` before leaving (see [Frontend](frontend.md#success-event-and-confirmation-page)).

### `lcmt_mailer_captcha_providers`
Register a spam protection. Keys are provider ids, values are classes implementing `LcmtDevMailer\CaptchaProvider`.

```php
add_filter('lcmt_mailer_captcha_providers', function (array $providers) {
    $providers['my-captcha'] = MyCaptcha::class;
    return $providers;
});
```

A provider that loads its own front-end scripts also implements `LcmtDevMailer\CaptchaAssets` (`public static function enqueueAssets(): void`): `Captcha::widget()` calls it once per page, the first time a form protected by the provider renders, so pages without such a form load nothing. Its script registers a JS adapter (see [Frontend](frontend.md#spam-protection-adapters)).

Optionally, it implements `LcmtDevMailer\CaptchaBadge` (`icon(): string`, a dashicon class such as `dashicons-shield`, and `color(): string`, a hex color) for its badge in the **Protection** column of the email templates list. Without it, the column shows a neutral `dashicons-shield` in `#50575e`.

It can also implement `LcmtDevMailer\CaptchaPrivacy` (`privacyText(): string`, a translated and escaped `<h3>` heading and its paragraphs): `[lcmt-privacy-policy]` and the WordPress policy guide then include it while the provider is in use (ready, and the site default or the protection of a published email template).

`verify()` returns false when the proof is wrong. When the verification service itself gave no answer (network error, HTTP error, unreadable answer), throw `LcmtDevMailer\CaptchaUnavailable` instead: `Captcha::check()` reports it as unavailable, and the site setting decides whether the message is refused (default) or accepted. Either way a refused message is kept in Received messages → Spam with the reason `captcha_failed` or `captcha_unavailable`.

Used in: `Captcha::providers()`.

### `lcmt_mailer_blocked_hourly_limit`
How many messages blocked by the spam protection are kept in Received messages → Spam per hour (default `50`), so a flood of bots cannot fill the table. Once reached, further blocked messages are refused without being saved until the next hour. `0` keeps none.

```php
add_filter('lcmt_mailer_blocked_hourly_limit', function (int $limit) {
    return 200;
});
```

Used in: `SubmissionRecorder::canKeepBlocked()`.

### Post meta `_lcmt_mail_captcha`
Not a hook, but the setting themes and tools may read or write: the spam protection of a mail template (`MetaFields::FIELD_CAPTCHA`). `''` or missing follows the site default (Spam protection page), `none` disables the protection for this form, any provider id (`altcha`, `turnstile`, or one added with the filter above) selects it. An unknown value is treated as the site default. `Captcha::selectedIdFor($postId)` resolves it, `Captcha::activeFor($postId)` also checks that the provider is ready.

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

### `lcmt_mailer_trash_days`
Days a received message stays in the trash before the daily task deletes it for good. Default: WordPress's `EMPTY_TRASH_DAYS` (30 unless `wp-config.php` sets it). `0` (or less) disables the trash: deleting a message deletes it at once and the Trash view is not shown.

```php
add_filter('lcmt_mailer_trash_days', function () {
    return 60;
});
```

Used in: `Retention::trashDays()` (cron emptying, row and bulk actions, list views, detail).

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
