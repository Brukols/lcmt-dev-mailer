<?php

use LcmtDevMailer\Captcha;
use LcmtDevMailer\CaptchaSettings;
use LcmtDevMailer\Turnstile;

/**
 * Save a tab of the Spam protection page the way wp-admin/options.php does:
 * every option of the posted group is updated, null when the form did not
 * send it.
 */
function lcmt_cs_save(string $tab, array $post): void
{
    global $new_allowed_options;

    CaptchaSettings::registerSettings();

    $saved = $_POST;
    $_POST = $post;

    try {
        foreach ($new_allowed_options[CaptchaSettings::group($tab)] ?? [] as $option) {
            update_option($option, $_POST[$option] ?? null);
        }
    } finally {
        $_POST = $saved;
    }
}

function lcmt_cs_seed(): void
{
    update_option(Captcha::OPTION, 'altcha');
    update_option(Captcha::OPTION_UNAVAILABLE, Captcha::ACCEPT);
    update_option(Captcha::OPTION_KEEP_BLOCKED, '1');
    update_option(Turnstile::OPTION_SITE_KEY, '1x00000000000000000000AA');
    update_option(Turnstile::OPTION_SECRET_KEY, 'it-secret');
    update_option(Turnstile::OPTION_APPEARANCE, Turnstile::APPEARANCE_ALWAYS);
}

lcmt_it('captcha settings: saving the General tab keeps the Turnstile keys and appearance', function () {
    lcmt_cs_seed();
    $secret = get_option(Turnstile::OPTION_SECRET_KEY);

    lcmt_cs_save(CaptchaSettings::TAB_GENERAL, [
        Captcha::OPTION             => 'turnstile',
        Captcha::OPTION_UNAVAILABLE => Captcha::REFUSE,
        // keep-blocked unchecked: not sent
    ]);

    lcmt_assert_same('turnstile', get_option(Captcha::OPTION), 'site default saved');
    lcmt_assert_same(Captcha::REFUSE, get_option(Captcha::OPTION_UNAVAILABLE), 'unavailable saved');
    lcmt_assert_same('0', get_option(Captcha::OPTION_KEEP_BLOCKED), 'unchecked box saved');
    lcmt_assert_same('1x00000000000000000000AA', get_option(Turnstile::OPTION_SITE_KEY), 'site key kept');
    lcmt_assert_same($secret, get_option(Turnstile::OPTION_SECRET_KEY), 'secret kept');
    lcmt_assert_same(Turnstile::APPEARANCE_ALWAYS, get_option(Turnstile::OPTION_APPEARANCE), 'appearance kept');
});

lcmt_it('captcha settings: saving the Turnstile tab keeps the General options', function () {
    lcmt_cs_seed();
    $secret = get_option(Turnstile::OPTION_SECRET_KEY);

    lcmt_cs_save('turnstile', [
        Turnstile::OPTION_SITE_KEY   => '2x00000000000000000000AB',
        Turnstile::OPTION_SECRET_KEY => '',
        Turnstile::OPTION_APPEARANCE => Turnstile::APPEARANCE_INTERACTION,
    ]);

    lcmt_assert_same('2x00000000000000000000AB', get_option(Turnstile::OPTION_SITE_KEY), 'site key saved');
    lcmt_assert_same($secret, get_option(Turnstile::OPTION_SECRET_KEY), 'empty secret field keeps the secret');
    lcmt_assert_same(Turnstile::APPEARANCE_INTERACTION, get_option(Turnstile::OPTION_APPEARANCE), 'appearance saved');
    lcmt_assert_same('altcha', get_option(Captcha::OPTION), 'site default kept');
    lcmt_assert_same(Captcha::ACCEPT, get_option(Captcha::OPTION_UNAVAILABLE), 'unavailable kept');
    lcmt_assert_same('1', get_option(Captcha::OPTION_KEEP_BLOCKED), 'keep-blocked kept');
});

lcmt_it('captcha settings: the ALTCHA tab has no settings group to post', function () {
    global $new_allowed_options;

    CaptchaSettings::registerSettings();

    lcmt_assert_true(empty($new_allowed_options[CaptchaSettings::group('altcha')]), 'no altcha group');
});

lcmt_it('captcha settings: links open the tab of their provider', function () {
    lcmt_assert_true(str_contains(CaptchaSettings::tabUrl('turnstile'), '&tab=turnstile'), 'turnstile tab URL');
    lcmt_assert_same(CaptchaSettings::url(), CaptchaSettings::tabUrl(CaptchaSettings::TAB_GENERAL), 'general tab URL');
    lcmt_assert_same('turnstile', CaptchaSettings::tabForSecret(Turnstile::OPTION_SECRET_KEY), 'secret option tab');
    lcmt_assert_same(CaptchaSettings::TAB_GENERAL, CaptchaSettings::tabForSecret('unknown'), 'unknown option tab');
});

lcmt_it('captcha settings: inUseIds reads the published mail templates', function () {
    update_option(Captcha::OPTION, 'none');
    update_option(Turnstile::OPTION_SITE_KEY, '1x00000000000000000000AA');
    update_option(Turnstile::OPTION_SECRET_KEY, 'it-secret');

    foreach (get_posts(['post_type' => 'mail', 'post_status' => 'any', 'numberposts' => -1, 'fields' => 'ids', 'lang' => '']) as $id) {
        update_post_meta($id, '_lcmt_mail_captcha', 'none');
    }

    $published = wp_insert_post(['post_type' => 'mail', 'post_status' => 'publish', 'post_title' => 'it captcha']);
    $draft     = wp_insert_post(['post_type' => 'mail', 'post_status' => 'draft', 'post_title' => 'it captcha draft']);
    update_post_meta($draft, '_lcmt_mail_captcha', 'altcha');

    update_post_meta($published, '_lcmt_mail_captcha', 'none');
    lcmt_assert_same([], Captcha::inUseIds(), 'nothing in use');

    update_post_meta($published, '_lcmt_mail_captcha', 'turnstile');
    lcmt_assert_same(['turnstile'], Captcha::inUseIds(), 'published template counts, draft does not');
    lcmt_assert_same(Captcha::STATUS_ACTIVE, Captcha::status('turnstile', Captcha::inUseIds()), 'turnstile active');
    lcmt_assert_same(Captcha::STATUS_UNUSED, Captcha::status('altcha', Captcha::inUseIds()), 'altcha unused');
});
