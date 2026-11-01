<?php

use LcmtDevMailer\CaptchaUnavailable;
use LcmtDevMailer\SecretBox;
use LcmtDevMailer\Turnstile;
use LcmtDevMailer\UnreadableSecrets;
use PHPUnit\Framework\TestCase;

class TurnstileTest extends TestCase
{
    private const HOST = 'example.test';

    protected function setUp(): void
    {
        $GLOBALS['lcmt_test_options']    = [];
        $GLOBALS['lcmt_test_transients'] = [];
        $GLOBALS['lcmt_test_mails']      = [];
        $_POST                           = [];
    }

    private static function response(array $body, int $code = 200): array
    {
        return ['response' => ['code' => $code], 'body' => json_encode($body)];
    }

    private static function ok(array $override = []): array
    {
        return self::response($override + [
            'success'  => true,
            'action'   => 'lcmt_form',
            'hostname' => self::HOST,
        ]);
    }

    public function test_success_with_matching_action_and_host_passes(): void
    {
        $this->assertTrue(Turnstile::parseVerification(self::ok(), self::HOST));
    }

    public function test_success_false_fails(): void
    {
        $response = self::response(['success' => false, 'error-codes' => ['invalid-input-response']]);

        $this->assertFalse(Turnstile::parseVerification($response, self::HOST));
    }

    public function test_wrong_action_fails(): void
    {
        $this->assertFalse(Turnstile::parseVerification(self::ok(['action' => 'other']), self::HOST));
    }

    public function test_wrong_hostname_fails(): void
    {
        $this->assertFalse(Turnstile::parseVerification(self::ok(['hostname' => 'evil.test']), self::HOST));
    }

    public function test_wp_error_throws_unavailable(): void
    {
        $this->expectException(CaptchaUnavailable::class);

        Turnstile::parseVerification(new WP_Error('http_request_failed', 'timeout'), self::HOST);
    }

    public function test_http_500_throws_unavailable(): void
    {
        $this->expectException(CaptchaUnavailable::class);

        Turnstile::parseVerification(self::response(['success' => false], 500), self::HOST);
    }

    public function test_internal_error_code_throws_unavailable(): void
    {
        $this->expectException(CaptchaUnavailable::class);

        Turnstile::parseVerification(self::response(['success' => false, 'error-codes' => ['internal-error']]), self::HOST);
    }

    public function test_non_json_body_throws_unavailable(): void
    {
        $this->expectException(CaptchaUnavailable::class);

        Turnstile::parseVerification(['response' => ['code' => 200], 'body' => '<html>'], self::HOST);
    }

    public function test_testing_key_success_passes_without_host_and_action(): void
    {
        $response = self::response([
            'success'     => true,
            'hostname'    => 'example.com',
            'error-codes' => [],
            'metadata'    => ['result_with_testing_key' => true],
        ]);

        $this->assertTrue(Turnstile::parseVerification($response, self::HOST));
    }

    public function test_testing_key_failure_fails(): void
    {
        $response = self::response([
            'success'     => false,
            'hostname'    => 'example.com',
            'error-codes' => ['invalid-input-response'],
            'metadata'    => ['result_with_testing_key' => true],
        ]);

        $this->assertFalse(Turnstile::parseVerification($response, self::HOST));
    }

    public function test_key_check_invalid_secret(): void
    {
        foreach (['invalid-input-secret', 'missing-input-secret'] as $code) {
            $this->assertSame('invalid', Turnstile::parseKeyCheck(self::response(['success' => false, 'error-codes' => [$code]])));
        }
    }

    public function test_key_check_connected(): void
    {
        $this->assertSame('connected', Turnstile::parseKeyCheck(self::response(['success' => false, 'error-codes' => ['invalid-input-response']])));
        $this->assertSame('connected', Turnstile::parseKeyCheck(self::response(['success' => true, 'error-codes' => []])));
    }

    public function test_key_check_unreachable(): void
    {
        $this->assertSame('unreachable', Turnstile::parseKeyCheck(new WP_Error('http_request_failed')));
        $this->assertSame('unreachable', Turnstile::parseKeyCheck(self::response([], 500)));
    }

    public function test_key_check_other_code_is_invalid(): void
    {
        $this->assertSame('invalid', Turnstile::parseKeyCheck(self::response(['success' => false, 'error-codes' => ['bad-request']])));
    }

    public function test_not_ready_without_secret(): void
    {
        $GLOBALS['lcmt_test_options'][Turnstile::OPTION_SITE_KEY] = 'site';

        $this->assertFalse(Turnstile::isReady());
        $this->assertSame('none', Turnstile::keySource('secret'));
    }

    public function test_ready_with_both_keys(): void
    {
        $GLOBALS['lcmt_test_options'][Turnstile::OPTION_SITE_KEY] = 'site';
        $GLOBALS['lcmt_test_options'][Turnstile::OPTION_SECRET_KEY] = SecretBox::encrypt('secret');

        $this->assertTrue(Turnstile::isReady());
        $this->assertSame('database', Turnstile::keySource('site'));
        $this->assertSame('database', Turnstile::keySource('secret'));
    }

    public function test_empty_secret_input_keeps_stored_secret(): void
    {
        $stored = SecretBox::encrypt('secret');
        $GLOBALS['lcmt_test_options'][Turnstile::OPTION_SECRET_KEY] = $stored;

        $this->assertSame($stored, Turnstile::sanitizeSecret(''));
        $this->assertSame($stored, Turnstile::sanitizeSecret(null));
    }

    public function test_new_secret_is_stored_encrypted(): void
    {
        $GLOBALS['lcmt_test_options'][UnreadableSecrets::OPTION] = [Turnstile::OPTION_SECRET_KEY => 'x'];

        $stored = Turnstile::sanitizeSecret("  new-secret \n");

        $this->assertStringStartsWith('lcmt1:', $stored);
        $this->assertSame('new-secret', SecretBox::decrypt($stored));
        $this->assertArrayNotHasKey(UnreadableSecrets::OPTION, $GLOBALS['lcmt_test_options']);
    }

    public function test_already_encrypted_value_is_not_encrypted_twice(): void
    {
        $stored = SecretBox::encrypt('secret');

        $this->assertSame($stored, Turnstile::sanitizeSecret($stored));
    }

    public function test_forget_checkbox_empties_the_secret(): void
    {
        $GLOBALS['lcmt_test_options'][Turnstile::OPTION_SECRET_KEY]  = SecretBox::encrypt('secret');
        $GLOBALS['lcmt_test_options'][UnreadableSecrets::OPTION]     = [Turnstile::OPTION_SECRET_KEY => 'x'];
        $_POST['lcmt_turnstile_forget_secret']                       = '1';

        $this->assertSame('', Turnstile::sanitizeSecret(''));
        $this->assertArrayNotHasKey(UnreadableSecrets::OPTION, $GLOBALS['lcmt_test_options']);
    }

    public function test_unreadable_secret_is_not_ready_and_reported(): void
    {
        $GLOBALS['lcmt_test_options'][Turnstile::OPTION_SITE_KEY]   = 'site';
        $GLOBALS['lcmt_test_options'][Turnstile::OPTION_SECRET_KEY] = 'lcmt1:' . base64_encode(str_repeat('x', 60));

        $this->assertSame('unreadable', Turnstile::keySource('secret'));
        // Looking at the source alone neither reports nor mails
        $this->assertSame([], $GLOBALS['lcmt_test_mails']);

        $this->assertFalse(Turnstile::isReady());
        $this->assertArrayHasKey(Turnstile::OPTION_SECRET_KEY, $GLOBALS['lcmt_test_options'][UnreadableSecrets::OPTION]);
        $this->assertCount(1, $GLOBALS['lcmt_test_mails']);
    }

    public function test_constant_line_uses_placeholder_not_the_key(): void
    {
        $GLOBALS['lcmt_test_options'][Turnstile::OPTION_SECRET_KEY] = SecretBox::encrypt('real-secret');

        $this->assertSame("define( 'LCMT_MAILER_TURNSTILE_SECRET_KEY', 'your-secret-key' );", Turnstile::constantLine(Turnstile::CONST_SECRET_KEY));
        $this->assertSame("define( 'LCMT_MAILER_TURNSTILE_SITE_KEY', 'your-site-key' );", Turnstile::constantLine(Turnstile::CONST_SITE_KEY));
    }

    public function test_widget_markup(): void
    {
        $GLOBALS['lcmt_test_options'][Turnstile::OPTION_SITE_KEY] = 'site"key';

        $this->assertSame(
            '<div data-lcmt-captcha="turnstile" data-sitekey="site&quot;key" data-action="lcmt_form" data-appearance="interaction-only"></div>',
            Turnstile::widget()
        );
    }

    public function test_appearance_falls_back_to_default(): void
    {
        $this->assertSame('always', Turnstile::sanitizeAppearance('always'));
        $this->assertSame('interaction-only', Turnstile::sanitizeAppearance('weird'));
    }
}
