<?php

namespace LcmtDevMailer;

if (!defined('ABSPATH')) {
    exit;
}

class Altcha
{
    private const ALGORITHM = 'SHA-256';
    private const MAX_NUMBER = 100000;
    private const EXPIRATION_SECONDS = 300; // 5 minutes

    public static function createChallenge(): array
    {
        $salt = bin2hex(random_bytes(12));
        $expires = time() + self::EXPIRATION_SECONDS;
        $saltWithExpiry = $salt . '?expires=' . $expires;

        $secretNumber = random_int(1, self::MAX_NUMBER);
        $challenge = hash('sha256', $saltWithExpiry . $secretNumber);
        $signature = hash_hmac('sha256', $challenge, self::getKey());

        return [
            'algorithm' => self::ALGORITHM,
            'challenge' => $challenge,
            'maxnumber' => self::MAX_NUMBER,
            'salt'      => $saltWithExpiry,
            'signature' => $signature,
        ];
    }

    public static function validatePayload(string $payload): bool
    {
        $decoded = base64_decode($payload, true);
        if ($decoded === false) return false;

        $data = json_decode($decoded, true);
        if (!is_array($data)) return false;

        $algorithm = $data['algorithm'] ?? null;
        $challenge = $data['challenge'] ?? null;
        $number    = $data['number'] ?? null;
        $salt      = $data['salt'] ?? null;
        $signature = $data['signature'] ?? null;

        if (!$algorithm || !$challenge || $number === null || !$salt || !$signature) return false;
        if ($algorithm !== self::ALGORITHM) return false;

        // Check expiration
        if (preg_match('/\?expires=(\d+)/', $salt, $matches)) {
            $expires = (int) $matches[1];
            if (time() > $expires) return false;
        }

        // Verify challenge hash
        $expectedChallenge = hash('sha256', $salt . $number);
        if (!hash_equals($expectedChallenge, $challenge)) return false;

        // Verify HMAC signature
        $expectedSignature = hash_hmac('sha256', $challenge, self::getKey());
        if (!hash_equals($expectedSignature, $signature)) return false;

        return true;
    }

    public static function isEnabled(): bool
    {
        return defined('ALTCHA_HMAC_KEY') && !empty(ALTCHA_HMAC_KEY);
    }

    /**
     * REST endpoint callback for GET /lcmt-mailer/v1/altcha/challenge
     */
    public static function handleChallenge(): \WP_REST_Response
    {
        if (!self::isEnabled()) {
            return new \WP_REST_Response(['error' => 'ALTCHA not configured'], 500);
        }

        $response = new \WP_REST_Response(self::createChallenge(), 200);
        $response->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
        $response->header('Pragma', 'no-cache');
        $response->header('Expires', '0');

        return $response;
    }

    public static function registerEndpoint(): void
    {
        register_rest_route('lcmt-mailer/v1', '/altcha/challenge', [
            'methods'             => 'GET',
            'callback'            => [self::class, 'handleChallenge'],
            'permission_callback' => '__return_true',
        ]);
    }

    private static function getKey(): string
    {
        return defined('ALTCHA_HMAC_KEY') ? ALTCHA_HMAC_KEY : '';
    }
}
