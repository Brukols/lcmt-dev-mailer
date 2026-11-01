<?php

use LcmtDevMailer\Altcha;
use LcmtDevMailer\SecretBox;
use LcmtDevMailer\UnreadableSecrets;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

/**
 * Counts the key derivations, to prove which payloads never reach the
 * costly PBKDF2 step.
 */
class SpyAltcha extends Altcha
{
    public static int $derivations = 0;

    public static function deriveKey(array $parameters, int $counter): string
    {
        self::$derivations++;

        return parent::deriveKey($parameters, $counter);
    }
}

class AltchaTest extends TestCase
{
    /** Low difficulty, so the tests solve challenges in a few milliseconds. */
    private const EASY = ['cost' => 1, 'keyPrefix' => '0'];

    protected function setUp(): void
    {
        $GLOBALS['lcmt_test_options']    = [];
        $GLOBALS['lcmt_test_transients'] = [];
    }

    /**
     * The challenge made and solved by ALTCHA's own library
     * (tests/fixtures/make-altcha-fixture.mjs).
     */
    private static function fixture(): array
    {
        return json_decode(file_get_contents(__DIR__ . '/fixtures/altcha-v3.json'), true);
    }

    private static function encode(mixed $data): string
    {
        return base64_encode(json_encode($data));
    }

    private static function sign(array $parameters, string $key): string
    {
        return hash_hmac('sha256', Altcha::canonicalJson($parameters), $key);
    }

    /** The site key, as generated and stored by the plugin. */
    private static function siteKey(): string
    {
        Altcha::createChallenge(self::EASY); // make sure the key exists

        return SecretBox::decrypt($GLOBALS['lcmt_test_options'][Altcha::OPTION_KEY]);
    }

    /**
     * Do what the widget does in the browser: find a counter whose derived
     * key starts with the prefix, encode the proof.
     */
    private function solve(array $challenge): string
    {
        $prefix = $challenge['parameters']['keyPrefix'];

        for ($counter = 0; $counter < 100000; $counter++) {
            $key = bin2hex(Altcha::deriveKey($challenge['parameters'], $counter));
            if (str_starts_with($key, $prefix)) {
                return self::encode([
                    'challenge' => $challenge,
                    'solution'  => ['counter' => $counter, 'derivedKey' => $key, 'time' => 12.3],
                ]);
            }
        }

        $this->fail('The challenge has no solution.');
    }

    // Cross-language fixture -------------------------------------------------

    public function test_canonical_json_matches_the_altcha_library(): void
    {
        $fixture = self::fixture();

        $this->assertSame(
            $fixture['challenge']['signature'],
            self::sign($fixture['challenge']['parameters'], $fixture['secret'])
        );
    }

    public function test_canonical_json_sorts_keys_and_leaves_out_nulls(): void
    {
        $this->assertSame(
            '{"a":{"x":1,"y":"PBKDF2/SHA-256"},"b":[2,1],"é":"è"}',
            Altcha::canonicalJson(['é' => 'è', 'n' => null, 'b' => [2, 1], 'a' => ['y' => 'PBKDF2/SHA-256', 'x' => 1]])
        );
    }

    public function test_derives_the_same_key_as_the_altcha_library(): void
    {
        $fixture = self::fixture();

        $this->assertSame(
            $fixture['solution']['derivedKey'],
            bin2hex(Altcha::deriveKey($fixture['challenge']['parameters'], $fixture['solution']['counter']))
        );
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_accepts_the_library_payload_once(): void
    {
        define('ALTCHA_HMAC_KEY', self::fixture()['secret']);

        $payload = self::fixture()['payload'];

        $this->assertTrue(Altcha::validatePayload($payload));
        $this->assertFalse(Altcha::validatePayload($payload), 'spent');
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_the_challenge_is_spent_whatever_the_solution_time(): void
    {
        define('ALTCHA_HMAC_KEY', self::fixture()['secret']);

        $fixture = self::fixture();
        $this->assertTrue(Altcha::validatePayload($fixture['payload']));

        $again = ['challenge' => $fixture['challenge'], 'solution' => ['time' => 99] + $fixture['solution']];
        $this->assertFalse(Altcha::validatePayload(self::encode($again)));
    }

    public static function bypasses(): array
    {
        $names = [
            'the widget test payload', 'another counter', 'derivedKey uppercase', 'derivedKey odd-length',
            'derivedKey not hex', 'derivedKey missing', 'cost lowered and solved', 'keyPrefix changed',
            'keyPrefix emptied', 'expired, re-signed', 'signature missing', 'signature empty',
            'signature uppercase', 'a v1 payload signed with the key', 'counter -1', 'counter 1.5',
            'counter "1"', 'counter "7"', 'counter 2^32', 'counter 2^32 + 7', 'solution missing',
            'a real key missing the prefix',
            'not base64', 'not JSON',
        ];

        return array_combine($names, array_map(fn ($name) => [$name], $names));
    }

    /**
     * Every way a bot could try to pass without the work, starting from the
     * library's valid payload. With the right key, so only the tampering fails.
     */
    #[DataProvider('bypasses')]
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_rejects_a_bypass(string $bypass): void
    {
        $fixture = self::fixture();
        define('ALTCHA_HMAC_KEY', $fixture['secret']);

        $secret = $fixture['secret'];
        $data = ['challenge' => $fixture['challenge'], 'solution' => $fixture['solution']];
        $parameters = &$data['challenge']['parameters'];
        $solution = &$data['solution'];

        switch ($bypass) {
            case 'the widget test payload':
                $data = ['challenge' => null, 'solution' => null, 'test' => true];
                break;
            case 'another counter':
                $solution['counter']++;
                break;
            case 'derivedKey uppercase':
                $solution['derivedKey'] = strtoupper($solution['derivedKey']);
                break;
            case 'derivedKey odd-length':
                $solution['derivedKey'] = substr($solution['derivedKey'], 0, -1);
                break;
            case 'derivedKey not hex':
                $solution['derivedKey'] = 'g' . substr($solution['derivedKey'], 1);
                break;
            case 'derivedKey missing':
                unset($solution['derivedKey']);
                break;
            case 'cost lowered and solved':
                // A real cheap solution for cost 1: only the signature stops it.
                $parameters['cost'] = 1;
                for ($counter = 0; ; $counter++) {
                    $key = bin2hex(hash_pbkdf2('sha256', hex2bin($parameters['nonce']) . pack('N', $counter), hex2bin($parameters['salt']), 1, 32, true));
                    if ($key[0] === '0') break;
                }
                $solution = ['counter' => $counter, 'derivedKey' => $key, 'time' => 1];
                break;
            case 'keyPrefix changed':
                // Still matched by the submitted key: only the signature stops it.
                $parameters['keyPrefix'] = substr($solution['derivedKey'], 0, 2);
                break;
            case 'keyPrefix emptied':
                $parameters['keyPrefix'] = '';
                break;
            case 'expired, re-signed':
                $parameters['expiresAt'] = time() - 1;
                $data['challenge']['signature'] = self::sign($parameters, $secret);
                break;
            case 'signature missing':
                unset($data['challenge']['signature']);
                break;
            case 'signature empty':
                $data['challenge']['signature'] = '';
                break;
            case 'signature uppercase':
                $data['challenge']['signature'] = strtoupper($data['challenge']['signature']);
                break;
            case 'a v1 payload signed with the key':
                $salt = 'abc?expires=' . (time() + 300);
                $challenge = hash('sha256', $salt . 42);
                $data = ['algorithm' => 'SHA-256', 'challenge' => $challenge, 'number' => 42, 'salt' => $salt, 'signature' => hash_hmac('sha256', $challenge, $secret)];
                break;
            case 'counter -1':
                $solution['counter'] = -1;
                break;
            case 'counter 1.5':
                $solution['counter'] = 1.5;
                break;
            case 'counter "1"':
                $solution['counter'] = '1';
                break;
            case 'counter "7"':
                $solution['counter'] = (string) $solution['counter'];
                break;
            case 'counter 2^32':
                $solution['counter'] = 2 ** 32;
                break;
            case 'counter 2^32 + 7':
                // Packs to the same four bytes as the real counter if not range-checked.
                $solution['counter'] += 2 ** 32;
                break;
            case 'a real key missing the prefix':
                // The key for counter 0 is genuine but does not start with "0".
                $key = bin2hex(hash_pbkdf2('sha256', hex2bin($parameters['nonce']) . pack('N', 0), hex2bin($parameters['salt']), $parameters['cost'], 32, true));
                $this->assertStringStartsNotWith($parameters['keyPrefix'], $key);
                $solution = ['counter' => 0, 'derivedKey' => $key, 'time' => 1];
                break;
            case 'solution missing':
                unset($data['solution']);
                break;
            case 'not base64':
                $this->assertFalse(Altcha::validatePayload('not base64 !'));
                return;
            case 'not JSON':
                $this->assertFalse(Altcha::validatePayload(base64_encode('not json')));
                return;
            default:
                $this->fail("Unknown bypass: $bypass");
        }
        unset($parameters, $solution);

        $this->assertFalse(Altcha::validatePayload(self::encode($data)));
        // The untouched payload still passes: the rejection came from the change.
        $this->assertTrue(Altcha::validatePayload($fixture['payload']));
    }

    // Issued challenges ------------------------------------------------------

    public function test_issues_a_signed_pbkdf2_challenge(): void
    {
        $challenge = Altcha::createChallenge(self::EASY);
        $parameters = $challenge['parameters'];

        $this->assertSame(['parameters', 'signature'], array_keys($challenge));
        $this->assertSame(['algorithm', 'cost', 'expiresAt', 'keyLength', 'keyPrefix', 'nonce', 'salt'], array_keys($parameters));
        $this->assertSame('PBKDF2/SHA-256', $parameters['algorithm']);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $parameters['nonce']);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $parameters['salt']);
        $this->assertNotSame($parameters['nonce'], $parameters['salt']);
        $this->assertSame(1, $parameters['cost']);
        $this->assertSame(32, $parameters['keyLength']);
        $this->assertSame('0', $parameters['keyPrefix']);
        $this->assertIsInt($parameters['expiresAt']);
        $this->assertEqualsWithDelta(time() + 300, $parameters['expiresAt'], 2);
        $this->assertSame(self::sign($parameters, self::siteKey()), $challenge['signature']);
    }

    public function test_issues_a_default_difficulty(): void
    {
        $parameters = Altcha::createChallenge()['parameters'];

        $this->assertIsInt($parameters['cost']);
        $this->assertGreaterThan(1, $parameters['cost']);
        $this->assertMatchesRegularExpression('/^[0-9a-f]+$/', $parameters['keyPrefix']);
    }

    public function test_issues_a_new_nonce_each_time(): void
    {
        $this->assertNotSame(
            Altcha::createChallenge(self::EASY)['parameters']['nonce'],
            Altcha::createChallenge(self::EASY)['parameters']['nonce']
        );
    }

    public function test_accepts_a_solved_challenge_once(): void
    {
        $challenge = Altcha::createChallenge(self::EASY);
        $payload = $this->solve($challenge);

        $this->assertTrue(Altcha::validatePayload($payload));
        $this->assertArrayHasKey('lcmt_altcha_used_' . $challenge['parameters']['nonce'], $GLOBALS['lcmt_test_transients']);
        $this->assertFalse(Altcha::validatePayload($payload), 'spent');
    }

    public function test_rejects_a_challenge_signed_with_another_key(): void
    {
        $challenge = Altcha::createChallenge(self::EASY);
        $challenge['signature'] = self::sign($challenge['parameters'], 'another-key');

        $this->assertFalse(Altcha::validatePayload($this->solve($challenge)));
    }

    public function test_rejects_a_proof_once_the_key_changes(): void
    {
        $payload = $this->solve(Altcha::createChallenge(self::EASY));

        update_option(Altcha::OPTION_KEY, 'a-new-key');

        $this->assertFalse(Altcha::validatePayload($payload));
    }

    public function test_rejects_another_algorithm_even_signed(): void
    {
        $challenge = Altcha::createChallenge(self::EASY);
        $challenge['parameters']['algorithm'] = 'SHA-256';
        $challenge['signature'] = self::sign($challenge['parameters'], self::siteKey());

        $this->assertFalse(Altcha::validatePayload($this->solve($challenge)));
    }

    public function test_bad_challenges_never_reach_the_key_derivation(): void
    {
        $key = self::siteKey();

        $badSignature = Altcha::createChallenge(self::EASY);
        $badSignature['signature'] = str_repeat('0', 64);

        $expired = Altcha::createChallenge(self::EASY);
        $expired['parameters']['expiresAt'] = time() - 1;
        $expired['signature'] = self::sign($expired['parameters'], $key);

        $spent = $this->solve(Altcha::createChallenge(self::EASY));
        $this->assertTrue(Altcha::validatePayload($spent));

        $malformed = json_decode(base64_decode($this->solve(Altcha::createChallenge(self::EASY))), true);
        $uppercase = $malformed;
        $uppercase['solution']['derivedKey'] = strtoupper($uppercase['solution']['derivedKey']);
        $wrapped = $malformed;
        $wrapped['solution']['counter'] += 2 ** 32;

        $payloads = [
            'bad signature' => $this->solve($badSignature),
            'expired'       => $this->solve($expired),
            'spent'         => $spent,
            'uppercase key' => self::encode($uppercase),
            'wrapped count' => self::encode($wrapped),
        ];
        foreach ($payloads as $name => $payload) {
            SpyAltcha::$derivations = 0;
            $this->assertFalse(SpyAltcha::validatePayload($payload), $name);
            $this->assertSame(0, SpyAltcha::$derivations, $name);
        }

        // A valid payload does go through it, once.
        SpyAltcha::$derivations = 0;
        $this->assertTrue(SpyAltcha::validatePayload($this->solve(Altcha::createChallenge(self::EASY))));
        $this->assertSame(1, SpyAltcha::$derivations);
    }

    // Key --------------------------------------------------------------------

    public function testKeepsTheGeneratedKey(): void
    {
        Altcha::createChallenge(self::EASY);
        $key = $GLOBALS['lcmt_test_options'][Altcha::OPTION_KEY];

        Altcha::createChallenge(self::EASY);

        $this->assertSame(64, strlen(SecretBox::decrypt($key)));
        $this->assertSame($key, $GLOBALS['lcmt_test_options'][Altcha::OPTION_KEY]);
    }

    public function test_altcha_unreadable_key_is_regenerated_without_mail(): void
    {
        $GLOBALS['lcmt_test_mails'] = [];
        $GLOBALS['lcmt_test_options'][Altcha::OPTION_KEY] = 'lcmt1:' . base64_encode(str_repeat('x', 60));

        $challenge = Altcha::createChallenge(self::EASY);

        $stored = $GLOBALS['lcmt_test_options'][Altcha::OPTION_KEY];
        $this->assertTrue(SecretBox::isEncrypted($stored));
        $this->assertNotNull(SecretBox::decrypt($stored));
        $this->assertSame(self::sign($challenge['parameters'], SecretBox::decrypt($stored)), $challenge['signature']);
        $this->assertSame([], $GLOBALS['lcmt_test_mails']);
        $this->assertArrayNotHasKey(UnreadableSecrets::OPTION, $GLOBALS['lcmt_test_options']);
    }

    public function test_altcha_key_is_stored_encrypted(): void
    {
        Altcha::createChallenge(self::EASY);

        $this->assertTrue(SecretBox::isEncrypted($GLOBALS['lcmt_test_options'][Altcha::OPTION_KEY]));
    }

    // Markup -----------------------------------------------------------------

    public function test_widget_names_its_adapter(): void
    {
        $widget = Altcha::widget();

        $this->assertStringStartsWith('<altcha-widget data-lcmt-captcha="altcha" ', $widget);
        $url = 'https://example.test/wp-json/lcmt-mailer/v1/altcha/challenge';
        $this->assertStringContainsString('challenge="' . $url . '"', $widget);
        // Kept for pages cached with the 2.7 script, harmless to the v3 widget.
        $this->assertStringContainsString('challengeurl="' . $url . '"', $widget);
        $this->assertStringContainsString('auto="onfocus"', $widget);
        $this->assertStringNotContainsString('style="display:none', $widget);

        preg_match('/ configuration="([^"]*)"/', $widget, $m);
        $this->assertNotEmpty($m, 'configuration attribute');
        $config = json_decode(html_entity_decode($m[1], ENT_QUOTES), true);
        $this->assertTrue($config['hideFooter']);
        $this->assertSame('invisible', $config['display']);
        $this->assertFalse($config['humanInteractionSignature']);
    }

    public function test_altcha_loads_its_own_script(): void
    {
        $this->assertTrue(is_subclass_of(Altcha::class, \LcmtDevMailer\CaptchaAssets::class));
    }
}
