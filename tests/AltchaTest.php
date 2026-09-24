<?php

use LcmtDevMailer\Altcha;
use PHPUnit\Framework\TestCase;

class AltchaTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['lcmt_test_options']    = [];
        $GLOBALS['lcmt_test_transients'] = [];
    }

    /**
     * Do what the widget does in the browser: find the number, encode the proof.
     */
    private function solve(array $challenge): string
    {
        for ($number = 0; $number <= $challenge['maxnumber']; $number++) {
            if (hash('sha256', $challenge['salt'] . $number) === $challenge['challenge']) {
                return $this->encode($challenge, $number);
            }
        }

        $this->fail('The challenge has no solution.');
    }

    private function encode(array $challenge, int $number): string
    {
        return base64_encode(json_encode([
            'algorithm' => $challenge['algorithm'],
            'challenge' => $challenge['challenge'],
            'number'    => $number,
            'salt'      => $challenge['salt'],
            'signature' => $challenge['signature'],
        ]));
    }

    /**
     * A challenge signed with the site key, as the server would have issued it.
     */
    private function signedChallenge(string $salt, int $number): array
    {
        Altcha::createChallenge(); // make sure the key exists

        $challenge = hash('sha256', $salt . $number);

        return [
            'algorithm' => 'SHA-256',
            'challenge' => $challenge,
            'maxnumber' => 100000,
            'salt'      => $salt,
            'signature' => hash_hmac('sha256', $challenge, $GLOBALS['lcmt_test_options'][Altcha::OPTION_KEY]),
        ];
    }

    public function testAcceptsASolvedChallenge(): void
    {
        $this->assertTrue(Altcha::validatePayload($this->solve(Altcha::createChallenge())));
    }

    public function testAcceptsAProofOnlyOnce(): void
    {
        $payload = $this->solve(Altcha::createChallenge());

        $this->assertTrue(Altcha::validatePayload($payload));
        $this->assertFalse(Altcha::validatePayload($payload));
    }

    public function testRejectsAnExpiredChallenge(): void
    {
        $challenge = $this->signedChallenge('abc?expires=' . (time() - 1), 42);

        $this->assertFalse(Altcha::validatePayload($this->encode($challenge, 42)));
    }

    public function testRejectsAChallengeWithoutExpiry(): void
    {
        $challenge = $this->signedChallenge('abc', 42);

        $this->assertFalse(Altcha::validatePayload($this->encode($challenge, 42)));
    }

    public function testRejectsAWrongNumber(): void
    {
        $challenge = Altcha::createChallenge();
        $payload   = json_decode(base64_decode($this->solve($challenge)), true);

        $this->assertFalse(Altcha::validatePayload($this->encode($challenge, $payload['number'] + 1)));
    }

    public function testRejectsAChallengeSignedWithAnotherKey(): void
    {
        $challenge = Altcha::createChallenge();
        $challenge['signature'] = hash_hmac('sha256', $challenge['challenge'], 'another-key');

        $this->assertFalse(Altcha::validatePayload($this->solve($challenge)));
    }

    public function testRejectsAProofOnceTheKeyChanges(): void
    {
        $payload = $this->solve(Altcha::createChallenge());

        update_option(Altcha::OPTION_KEY, 'a-new-key');

        $this->assertFalse(Altcha::validatePayload($payload));
    }

    public function testRejectsMalformedPayloads(): void
    {
        $this->assertFalse(Altcha::validatePayload('not base64 !'));
        $this->assertFalse(Altcha::validatePayload(base64_encode('not json')));
        $this->assertFalse(Altcha::validatePayload(base64_encode('{"algorithm":"SHA-256"}')));
    }

    public function testKeepsTheGeneratedKey(): void
    {
        Altcha::createChallenge();
        $key = $GLOBALS['lcmt_test_options'][Altcha::OPTION_KEY];

        Altcha::createChallenge();

        $this->assertSame(64, strlen($key));
        $this->assertSame($key, $GLOBALS['lcmt_test_options'][Altcha::OPTION_KEY]);
    }
}
