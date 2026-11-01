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
        \LcmtDevMailer\Captcha::forgetReady();
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

    private function breakTurnstileSecret(): void
    {
        $GLOBALS['lcmt_test_options'][Turnstile::OPTION_SITE_KEY]   = 'site';
        $GLOBALS['lcmt_test_options'][Turnstile::OPTION_SECRET_KEY] = 'lcmt1:' . base64_encode(str_repeat('x', 60));
    }

    public function test_an_unreadable_key_is_read_from_the_stored_value_without_side_effects(): void
    {
        $this->breakTurnstileSecret();

        $this->assertSame(Captcha::STATUS_UNREADABLE, Captcha::status('turnstile', Captcha::inUseIds([])));
        $this->assertSame(['lcmt_mailer_turnstile_secret_key' => 'Cloudflare Turnstile secret key'], Captcha::unreadableSecrets(Turnstile::class));

        // Nothing recorded, nothing mailed
        $this->assertArrayNotHasKey(UnreadableSecrets::OPTION, $GLOBALS['lcmt_test_options']);
        $this->assertSame([], $GLOBALS['lcmt_test_mails']);
    }

    public function test_a_listed_key_that_reads_fine_is_not_unreadable(): void
    {
        $GLOBALS['lcmt_test_options'][Turnstile::OPTION_SITE_KEY]   = 'site';
        $GLOBALS['lcmt_test_options'][Turnstile::OPTION_SECRET_KEY] = SecretBox::encrypt('secret');
        $GLOBALS['lcmt_test_options'][UnreadableSecrets::OPTION]    = [Turnstile::OPTION_SECRET_KEY => 'Turnstile secret'];

        $this->assertSame(Captcha::STATUS_ACTIVE, Captcha::status('turnstile', ['turnstile']));
    }

    public function test_a_real_use_reports_the_unreadable_key_once(): void
    {
        $this->breakTurnstileSecret();
        $GLOBALS['lcmt_test_post_meta'][5]['_lcmt_mail_captcha'] = 'turnstile';

        Captcha::useSecrets(5);
        Captcha::useSecrets(5);

        $this->assertArrayHasKey(Turnstile::OPTION_SECRET_KEY, $GLOBALS['lcmt_test_options'][UnreadableSecrets::OPTION]);
        $this->assertCount(1, $GLOBALS['lcmt_test_mails']);
    }

    public function test_a_real_use_clears_the_report_once_the_key_reads_fine(): void
    {
        $GLOBALS['lcmt_test_options'][Turnstile::OPTION_SECRET_KEY] = SecretBox::encrypt('secret');
        $GLOBALS['lcmt_test_options'][UnreadableSecrets::OPTION]    = [Turnstile::OPTION_SECRET_KEY => 'Turnstile secret'];
        $GLOBALS['lcmt_test_options'][Captcha::OPTION]              = 'turnstile';

        Captcha::useSecrets(5);

        $this->assertArrayNotHasKey(UnreadableSecrets::OPTION, $GLOBALS['lcmt_test_options']);
    }

    public function test_a_template_using_another_provider_does_not_report(): void
    {
        $this->breakTurnstileSecret();
        $GLOBALS['lcmt_test_post_meta'][5]['_lcmt_mail_captcha'] = 'altcha';

        Captcha::useSecrets(5);

        $this->assertSame([], $GLOBALS['lcmt_test_mails']);
    }

    public function test_a_provider_without_secrets_is_never_unreadable(): void
    {
        $GLOBALS['lcmt_test_filters']['lcmt_mailer_captcha_providers'] = ['plain' => PlainBadgeProvider::class];
        $GLOBALS['lcmt_test_options'][UnreadableSecrets::OPTION]       = ['whatever' => 'x'];

        $this->assertSame(Captcha::STATUS_UNUSED, Captcha::status('plain', []));
    }
}
