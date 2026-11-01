<?php

use LcmtDevMailer\Captcha;
use LcmtDevMailer\CaptchaProvider;
use LcmtDevMailer\CaptchaUnavailable;
use PHPUnit\Framework\TestCase;

class FakeCaptchaProvider implements CaptchaProvider
{
    /** @var bool|\Throwable */
    public static $result = true;

    public static function id(): string
    {
        return 'fake';
    }

    public static function label(): string
    {
        return 'Fake';
    }

    public static function isReady(): bool
    {
        return true;
    }

    public static function registerRoutes(): void {}

    public static function widget(): string
    {
        return '<fake-widget>';
    }

    public static function verify(array $body): bool
    {
        if (self::$result instanceof \Throwable) {
            throw self::$result;
        }

        return self::$result;
    }

    public static function renderSettings(): void {}
}

class CaptchaTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['lcmt_test_options']   = [Captcha::OPTION => 'altcha'];
        $GLOBALS['lcmt_test_post_meta'] = [];
        $GLOBALS['lcmt_test_filters']   = [
            'lcmt_mailer_captcha_providers' => [
                'altcha' => \LcmtDevMailer\Altcha::class,
                'fake'   => FakeCaptchaProvider::class,
            ],
        ];
        FakeCaptchaProvider::$result = true;
    }

    private function setMeta(string $value): void
    {
        $GLOBALS['lcmt_test_post_meta'][7]['_lcmt_mail_captcha'] = $value;
    }

    public function test_template_without_choice_uses_site_default(): void
    {
        $this->assertSame('altcha', Captcha::selectedIdFor(7));
    }

    public function test_template_choice_wins_over_site_default(): void
    {
        $this->setMeta('fake');

        $this->assertSame('fake', Captcha::selectedIdFor(7));
    }

    public function test_template_none_turns_protection_off(): void
    {
        $this->setMeta('none');

        $this->assertNull(Captcha::activeFor(7));
        $this->assertSame(Captcha::PASSED, Captcha::check(7, []));
    }

    public function test_unknown_template_choice_falls_back_to_site_default(): void
    {
        $this->setMeta('removed-plugin');

        $this->assertSame('altcha', Captcha::selectedIdFor(7));
    }

    public function test_check_reports_unavailable_when_provider_throws(): void
    {
        $this->setMeta('fake');
        FakeCaptchaProvider::$result = new CaptchaUnavailable('down');

        $this->assertSame(Captcha::UNAVAILABLE, Captcha::check(7, []));
    }

    public function test_check_reports_failed_on_false(): void
    {
        $this->setMeta('fake');
        FakeCaptchaProvider::$result = false;

        $this->assertSame(Captcha::FAILED, Captcha::check(7, []));
    }

    public function test_check_reports_passed_on_true(): void
    {
        $this->setMeta('fake');

        $this->assertSame(Captcha::PASSED, Captcha::check(7, []));
    }

    public function test_sanitize_for_mail(): void
    {
        $this->assertSame('', Captcha::sanitizeForMail(''));
        $this->assertSame('none', Captcha::sanitizeForMail('none'));
        $this->assertSame('fake', Captcha::sanitizeForMail('fake'));
        $this->assertSame('', Captcha::sanitizeForMail('<x>'));
    }
}
