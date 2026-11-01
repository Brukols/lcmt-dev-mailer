<?php

use LcmtDevMailer\Altcha;
use LcmtDevMailer\Captcha;
use LcmtDevMailer\Privacy;
use LcmtDevMailer\SecretBox;
use LcmtDevMailer\Turnstile;
use PHPUnit\Framework\TestCase;

/**
 * Which protections are in use, and the privacy policy part they bring.
 */
class CaptchaInUseTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['lcmt_test_options']    = [Captcha::OPTION => 'altcha', Altcha::OPTION_KEY => SecretBox::encrypt('hmac')];
        $GLOBALS['lcmt_test_transients'] = [];
        $GLOBALS['lcmt_test_post_meta']  = [];
        $GLOBALS['lcmt_test_filters']    = [];
    }

    private function choose(int $postId, string $value): void
    {
        $GLOBALS['lcmt_test_post_meta'][$postId]['_lcmt_mail_captcha'] = $value;
    }

    private function setUpTurnstile(): void
    {
        $GLOBALS['lcmt_test_options'][Turnstile::OPTION_SITE_KEY]   = 'site';
        $GLOBALS['lcmt_test_options'][Turnstile::OPTION_SECRET_KEY] = SecretBox::encrypt('secret');
    }

    public function test_nothing_in_use_brings_no_part(): void
    {
        $GLOBALS['lcmt_test_options'][Captcha::OPTION] = 'none';
        $this->choose(1, '');

        $this->assertSame([], Captcha::inUseIds([1]));
        $this->assertSame('', Privacy::captchaPolicy([]));
    }

    public function test_altcha_site_default_only(): void
    {
        $this->choose(1, '');

        $this->assertSame(['altcha'], Captcha::inUseIds([1]));

        $text = Privacy::captchaPolicy(['altcha']);
        $this->assertStringContainsString('Form protection (ALTCHA)', $text);
        $this->assertStringNotContainsString('Turnstile', $text);
    }

    public function test_turnstile_on_one_template_and_altcha_default_in_provider_order(): void
    {
        $this->setUpTurnstile();
        $this->choose(1, 'turnstile');
        $this->choose(2, '');

        $this->assertSame(['altcha', 'turnstile'], Captcha::inUseIds([1, 2]));

        // Provider order, whatever the order they were found in
        $GLOBALS['lcmt_test_options'][Captcha::OPTION] = 'turnstile';
        $this->choose(2, 'altcha');
        $this->assertSame(['altcha', 'turnstile'], Captcha::inUseIds([1, 2]));

        $text = Privacy::captchaPolicy(['turnstile', 'altcha']);
        $this->assertLessThan(strpos($text, 'Form protection (Cloudflare Turnstile)'), strpos($text, 'Form protection (ALTCHA)'));
        $this->assertStringContainsString('<a href="https://www.cloudflare.com/turnstile-privacy-policy/">', $text);
        $this->assertStringContainsString('<a href="https://www.cloudflare.com/privacypolicy/">', $text);
    }

    public function test_a_provider_not_ready_is_not_in_use(): void
    {
        // Chosen on a template, not set up: the template falls back to ALTCHA
        $this->choose(1, 'turnstile');
        $this->assertSame(['altcha'], Captcha::inUseIds([1]));

        // Site default, not set up, and the only template has none
        $GLOBALS['lcmt_test_options'][Captcha::OPTION] = 'turnstile';
        $this->choose(1, 'none');
        $this->assertSame([], Captcha::inUseIds([1]));
    }

    public function test_a_provider_without_privacy_text_brings_nothing(): void
    {
        $GLOBALS['lcmt_test_filters']['lcmt_mailer_captcha_providers'] = ['plain' => PlainBadgeProvider::class];

        $this->assertSame('', Privacy::captchaPolicy(['plain']));
    }
}
