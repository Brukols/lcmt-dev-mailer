<?php

use LcmtDevMailer\SecretBox;
use LcmtDevMailer\UnreadableSecrets;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

class SecretBoxTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['lcmt_test_options'] = [];
        $GLOBALS['lcmt_test_mails']   = [];
    }

    public function test_round_trip(): void
    {
        $stored = SecretBox::encrypt('s3cret');

        $this->assertTrue(SecretBox::isEncrypted($stored));
        $this->assertStringStartsWith('lcmt1:', $stored);
        $this->assertSame('s3cret', SecretBox::decrypt($stored));
    }

    public function test_two_encryptions_differ(): void
    {
        $this->assertNotSame(SecretBox::encrypt('s3cret'), SecretBox::encrypt('s3cret'));
    }

    public function test_tampered_value_decrypts_to_null(): void
    {
        $raw = base64_decode(substr(SecretBox::encrypt('s3cret'), 6));
        $raw[strlen($raw) - 1] = $raw[strlen($raw) - 1] ^ "\x01";

        $this->assertNull(SecretBox::decrypt('lcmt1:' . base64_encode($raw)));
        $this->assertNull(SecretBox::decrypt('lcmt1:not base64 !!'));
        $this->assertNull(SecretBox::decrypt('lcmt1:'));
    }

    public function test_seal_encrypts_when_a_key_exists(): void
    {
        $sealed = SecretBox::seal('s3cret');

        $this->assertTrue(SecretBox::isEncrypted($sealed));
        $this->assertSame('s3cret', SecretBox::decrypt($sealed));
        $this->assertSame('', SecretBox::seal(''));
    }

    public function test_seal_keeps_plain_and_encrypt_throws_without_a_key(): void
    {
        // The bootstrap defines the salts, so check in a PHP process without them
        $script = 'define("ABSPATH", "/"); require ' . var_export(dirname(__DIR__) . '/includes/secret-box.php', true) . ';'
            . 'echo LcmtDevMailer\\SecretBox::seal("s3cret"), "|";'
            . 'try { LcmtDevMailer\\SecretBox::encrypt("s3cret"); } catch (\\LogicException $e) { echo "logic"; }';

        $output = shell_exec(escapeshellarg(PHP_BINARY) . ' -r ' . escapeshellarg($script) . ' 2>/dev/null');

        $this->assertSame('s3cret|logic', $output);
    }

    public function test_value_without_prefix_is_not_encrypted(): void
    {
        $this->assertFalse(SecretBox::isEncrypted('plain'));
        $this->assertNull(SecretBox::decrypt('plain'));
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_other_salts_cannot_decrypt(): void
    {
        $stored = SecretBox::encrypt('s3cret');

        // The constant wins over the salts, so defining it changes the key
        define('LCMT_MAILER_ENCRYPTION_KEY', 'another-key-source');

        $this->assertNull(SecretBox::decrypt($stored));
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_constant_key_wins_over_salts(): void
    {
        define('LCMT_MAILER_ENCRYPTION_KEY', 'another-key-source');
        $stored = SecretBox::encrypt('s3cret');

        $this->assertSame('s3cret', SecretBox::decrypt($stored));
        // Not the key the salts alone would give
        $this->assertNull(SecretBox::decrypt(self::withSaltsKey('s3cret')));
    }

    private static function withSaltsKey(string $plain): string
    {
        $nonce = str_repeat('n', SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $key   = hash_hkdf('sha256', AUTH_KEY . SECURE_AUTH_SALT, SODIUM_CRYPTO_SECRETBOX_KEYBYTES, 'lcmt-dev-mailer');

        return 'lcmt1:' . base64_encode($nonce . sodium_crypto_secretbox($plain, $nonce, $key));
    }

    public function test_plain_legacy_option_is_reencrypted_on_read(): void
    {
        $GLOBALS['lcmt_test_options']['my_opt'] = 'legacy';

        $this->assertSame('legacy', SecretBox::readOption('my_opt', 'My key'));
        $this->assertTrue(SecretBox::isEncrypted($GLOBALS['lcmt_test_options']['my_opt']));
        $this->assertSame('legacy', SecretBox::readOption('my_opt', 'My key'));
    }

    public function test_unreadable_option_returns_empty_and_reports(): void
    {
        $GLOBALS['lcmt_test_options']['my_opt'] = 'lcmt1:' . base64_encode(str_repeat('x', 60));

        $this->assertSame('', SecretBox::readOption('my_opt', 'My key'));
        $this->assertSame(['my_opt' => 'My key'], $GLOBALS['lcmt_test_options'][UnreadableSecrets::OPTION]);
        $this->assertCount(1, $GLOBALS['lcmt_test_mails']);
    }

    public function test_unreadable_option_is_not_reported_when_asked_not_to(): void
    {
        $GLOBALS['lcmt_test_options']['my_opt'] = 'lcmt1:' . base64_encode(str_repeat('x', 60));

        $this->assertSame('', SecretBox::readOption('my_opt', 'My key', false));
        $this->assertArrayNotHasKey(UnreadableSecrets::OPTION, $GLOBALS['lcmt_test_options']);
        $this->assertSame([], $GLOBALS['lcmt_test_mails']);
    }
}
