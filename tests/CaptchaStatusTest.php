<?php

use LcmtDevMailer\Altcha;
use LcmtDevMailer\Captcha;
use LcmtDevMailer\SecretBox;
use LcmtDevMailer\Turnstile;
use LcmtDevMailer\UnreadableSecrets;
use PHPUnit\Framework\TestCase;

/**
 * Status badge of each provider tab on the Spam protection page.
 */
class CaptchaStatusTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['lcmt_test_options']    = ['admin_email' => 'admin@example.test', Captcha::OPTION => 'altcha', Altcha::OPTION_KEY => SecretBox::encrypt('hmac')];
        $GLOBALS['lcmt_test_transients'] = [];
        $GLOBALS['lcmt_test_post_meta']  = [];
        $GLOBALS['lcmt_test_filters']    = [];
        $GLOBALS['lcmt_test_mails']      = [];
    }

    public function test_ready_and_in_use_is_active(): void
    {
        $this->assertSame(Captcha::STATUS_ACTIVE, Captcha::status('altcha', ['altcha']));
    }

    public function test_ready_but_used_by_nothing(): void
    {
        $GLOBALS['lcmt_test_options'][Turnstile::OPTION_SITE_KEY]   = 'site';
        $GLOBALS['lcmt_test_options'][Turnstile::OPTION_SECRET_KEY] = SecretBox::encrypt('secret');

        $this->assertSame(Captcha::STATUS_UNUSED, Captcha::status('turnstile', ['altcha']));
    }

    public function test_not_ready_is_not_set_up(): void
    {
        $GLOBALS['lcmt_test_options'][Turnstile::OPTION_SITE_KEY] = 'site';

        $this->assertSame(Captcha::STATUS_NOT_SET_UP, Captcha::status('turnstile', []));
        $this->assertSame(Captcha::STATUS_NOT_SET_UP, Captcha::status('removed-plugin', []));
    }

    public function test_a_key_listed_as_unreadable(): void
    {
        $GLOBALS['lcmt_test_options'][Turnstile::OPTION_SITE_KEY]   = 'site';
        $GLOBALS['lcmt_test_options'][Turnstile::OPTION_SECRET_KEY] = SecretBox::encrypt('secret');
        $GLOBALS['lcmt_test_options'][UnreadableSecrets::OPTION]    = [Turnstile::OPTION_SECRET_KEY => 'Turnstile secret'];

        $this->assertSame(Captcha::STATUS_UNREADABLE, Captcha::status('turnstile', ['turnstile']));
    }

    public function test_a_key_found_unreadable_while_checking(): void
    {
        $GLOBALS['lcmt_test_options'][Turnstile::OPTION_SITE_KEY]   = 'site';
        $GLOBALS['lcmt_test_options'][Turnstile::OPTION_SECRET_KEY] = 'lcmt1:' . base64_encode(str_repeat('x', 60));

        $this->assertSame(Captcha::STATUS_UNREADABLE, Captcha::status('turnstile', []));
    }

    public function test_a_provider_without_secrets_is_never_unreadable(): void
    {
        $GLOBALS['lcmt_test_filters']['lcmt_mailer_captcha_providers'] = ['plain' => PlainBadgeProvider::class];
        $GLOBALS['lcmt_test_options'][UnreadableSecrets::OPTION]       = ['whatever' => 'x'];

        $this->assertSame(Captcha::STATUS_UNUSED, Captcha::status('plain', []));
    }
}
