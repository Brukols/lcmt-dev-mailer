<?php

namespace LcmtDevMailer;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ALTCHA proof-of-work challenge.
 *
 * The HMAC key comes from the ALTCHA_HMAC_KEY constant when wp-config.php
 * defines it, otherwise from an option generated on first use.
 */
class Altcha implements CaptchaProvider
{
    public const OPTION_KEY = 'lcmt_mailer_altcha_key';
    public const REGENERATE_ACTION = 'lcmt_mailer_regenerate_altcha_key';

    private const ALGORITHM = 'SHA-256';
    private const MAX_NUMBER = 100000;
    private const EXPIRATION_SECONDS = 300; // 5 minutes
    private const USED_PREFIX = 'lcmt_altcha_used_';

    public static function id(): string
    {
        return 'altcha';
    }

    public static function label(): string
    {
        return 'ALTCHA';
    }

    public static function isReady(): bool
    {
        return self::getKey() !== '';
    }

    public static function registerRoutes(): void
    {
        register_rest_route('lcmt-mailer/v1', '/altcha/challenge', [
            'methods'             => 'GET',
            'callback'            => [self::class, 'handleChallenge'],
            'permission_callback' => '__return_true',
        ]);
    }

    public static function widget(): string
    {
        $challengeUrl = home_url('/wp-json/lcmt-mailer/v1/altcha/challenge');

        return '<altcha-widget challengeurl="' . esc_attr($challengeUrl) . '" auto="onfocus" hidefooter style="display:none;"></altcha-widget>';
    }

    public static function verify(array $body): bool
    {
        $payload = $body['altcha'] ?? '';

        return is_string($payload) && $payload !== '' && self::validatePayload($payload);
    }

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

    /**
     * Check a solved challenge, and spend it so it cannot be sent again.
     */
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

        if (!is_string($algorithm) || !is_string($challenge) || !is_scalar($number) || !is_string($salt) || !is_string($signature)) return false;
        if ($algorithm !== self::ALGORITHM) return false;

        // Every challenge this class issues carries its expiry in the salt
        if (!preg_match('/\?expires=(\d+)/', $salt, $matches)) return false;

        $expires = (int) $matches[1];
        if (time() > $expires) return false;

        // Verify challenge hash
        $expectedChallenge = hash('sha256', $salt . $number);
        if (!hash_equals($expectedChallenge, $challenge)) return false;

        // Verify HMAC signature
        $expectedSignature = hash_hmac('sha256', $challenge, self::getKey());
        if (!hash_equals($expectedSignature, $signature)) return false;

        // A solved challenge is good for one submission only. The marker
        // lives until the challenge expires, after which the expiry check
        // above rejects it anyway.
        $marker = self::USED_PREFIX . $challenge;
        if (get_transient($marker)) return false;

        set_transient($marker, 1, max(1, $expires - time()));

        return true;
    }

    /**
     * REST endpoint callback for GET /lcmt-mailer/v1/altcha/challenge
     */
    public static function handleChallenge(): \WP_REST_Response
    {
        $response = new \WP_REST_Response(self::createChallenge(), 200);
        $response->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
        $response->header('Pragma', 'no-cache');
        $response->header('Expires', '0');

        return $response;
    }

    /**
     * Whether wp-config.php sets the key, which then cannot be changed here.
     */
    public static function isKeyFromConfig(): bool
    {
        return defined('ALTCHA_HMAC_KEY') && ALTCHA_HMAC_KEY !== '';
    }

    public static function renderSettings(): void
    {
        $regenerated = ($_GET['lcmt_altcha_key'] ?? '') === 'regenerated';
        ?>
        <tr data-captcha-provider="<?= esc_attr(self::id()) ?>">
            <th scope="row"><?php esc_html_e('ALTCHA key', 'lcmt-dev-mailer'); ?></th>
            <td>
                <?php if (self::isKeyFromConfig()): ?>
                    <p>
                        <?php
                        printf(
                            /* translators: %1$s: constant name, %2$s: file name */
                            esc_html__('Set by the %1$s constant in %2$s.', 'lcmt-dev-mailer'),
                            '<code>ALTCHA_HMAC_KEY</code>',
                            '<code>wp-config.php</code>'
                        );
                        ?>
                    </p>
                <?php else: ?>
                    <p><?php esc_html_e('Generated automatically and stored in the database. Nothing to configure.', 'lcmt-dev-mailer'); ?></p>
                    <p>
                        <a class="button"
                           href="<?= esc_url(wp_nonce_url(admin_url('admin-post.php?action=' . self::REGENERATE_ACTION), self::REGENERATE_ACTION)) ?>"
                           onclick="return confirm(<?= esc_attr(wp_json_encode(__('Generate a new key? A visitor who opened a form in the last five minutes will have to submit it again.', 'lcmt-dev-mailer'))) ?>);">
                            <?php esc_html_e('Generate a new key', 'lcmt-dev-mailer'); ?>
                        </a>
                        <?php if ($regenerated): ?>
                            <span class="description"><?php esc_html_e('New key generated.', 'lcmt-dev-mailer'); ?></span>
                        <?php endif; ?>
                    </p>
                    <p class="description">
                        <?php esc_html_e('Only needed if the key may have leaked, from a copied database for instance.', 'lcmt-dev-mailer'); ?>
                    </p>
                <?php endif; ?>
            </td>
        </tr>
        <?php
    }

    /**
     * admin-post handler behind the "Generate a new key" button.
     */
    public static function handleRegenerateKey(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Insufficient permissions', 'lcmt-dev-mailer'), 403);
        }

        check_admin_referer(self::REGENERATE_ACTION);

        if (!self::isKeyFromConfig()) {
            update_option(self::OPTION_KEY, self::generateKey(), false);
        }

        wp_safe_redirect(CaptchaSettings::url(['lcmt_altcha_key' => 'regenerated']));
        exit;
    }

    private static function getKey(): string
    {
        if (self::isKeyFromConfig()) {
            return (string) ALTCHA_HMAC_KEY;
        }

        $key = (string) get_option(self::OPTION_KEY, '');

        if ($key === '') {
            $key = self::generateKey();

            // add_option only writes when no other request stored a key
            // first; read back so every request signs with the same one.
            add_option(self::OPTION_KEY, $key, '', false);
            $key = (string) get_option(self::OPTION_KEY, $key);
        }

        return $key;
    }

    private static function generateKey(): string
    {
        return bin2hex(random_bytes(32));
    }
}
