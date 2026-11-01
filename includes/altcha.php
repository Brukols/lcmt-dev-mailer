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
class Altcha implements CaptchaProvider, CaptchaAssets, CaptchaBadge, CaptchaPrivacy
{
    public const OPTION_KEY = 'lcmt_mailer_altcha_key';
    public const REGENERATE_ACTION = 'lcmt_mailer_regenerate_altcha_key';

    // ALTCHA v2 protocol: the browser looks for a counter whose PBKDF2 key,
    // derived from the nonce and the counter, starts with keyPrefix.
    private const ALGORITHM = 'PBKDF2/SHA-256';
    private const KEY_LENGTH = 32;
    private const MAX_COUNTER = 0xFFFFFFFF; // the counter is packed as a uint32
    // Until the difficulty setting comes: PBKDF2 iterations per try, and a
    // two-hex-digit prefix (1 try in 256 on average).
    private const DEFAULT_DIFFICULTY = ['cost' => 5000, 'keyPrefix' => '00'];
    private const PARAMETER_KEYS = ['algorithm', 'cost', 'expiresAt', 'keyLength', 'keyPrefix', 'nonce', 'salt'];
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

    public static function icon(): string
    {
        return 'dashicons-shield-alt';
    }

    public static function color(): string
    {
        return '#2271b1';
    }

    /**
     * The proof is solved in the browser on the first focus in the form
     * (auto="onfocus") against our own challenge route; the bundled widget
     * calls no outside service and sets no cookie.
     */
    public static function privacyText(): string
    {
        return '<h3>' . esc_html(sprintf(
            /* translators: %s: spam protection name */
            __('Form protection (%s)', 'lcmt-dev-mailer'),
            self::label()
        )) . '</h3>'
            . '<p>' . esc_html__('To protect our forms against automated submissions (spam, bots), your browser solves a small calculation when you start filling in a form, then our site checks the result. This check is done entirely by our site: no data is sent to a third-party service and no cookie is set.', 'lcmt-dev-mailer') . '</p>'
            . '<p>' . esc_html__('This processing is based on our legitimate interest in securing our site and protecting it against abuse (Article 6(1)(f) of the GDPR).', 'lcmt-dev-mailer') . '</p>';
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

        // `challenge` is the v3 widget's attribute; `challengeurl` stays for
        // pages cached with the 2.7 script (harmless to v3, which ignores it).
        // The widget is invisible and records no pointer or keyboard samples;
        // its wait alert is emptied by captcha-altcha.js.
        $configuration = wp_json_encode([
            'hideFooter'                => true,
            'display'                   => 'invisible',
            'humanInteractionSignature' => false,
        ]);

        return '<altcha-widget data-lcmt-captcha="altcha" challenge="' . esc_attr($challengeUrl) . '" challengeurl="' . esc_attr($challengeUrl) . '" auto="onfocus" configuration="' . esc_attr($configuration) . '"></altcha-widget>';
    }

    public static function enqueueAssets(): void
    {
        wp_enqueue_script(
            'lcmt-captcha-altcha',
            LCMT_MAILER_URL . 'assets/dist/captcha-altcha.js',
            [],
            lcmt_mailer_asset_version('assets/dist/captcha-altcha.js'),
            ['in_footer' => true, 'strategy' => 'defer']
        );
    }

    public static function verify(array $body): bool
    {
        $payload = $body['altcha'] ?? '';

        return is_string($payload) && $payload !== '' && self::validatePayload($payload);
    }

    /**
     * A new challenge, signed with the site key so the browser cannot lower
     * its difficulty or push back its expiry.
     *
     * @param array{cost: int, keyPrefix: string}|null $difficulty
     */
    public static function createChallenge(?array $difficulty = null): array
    {
        $difficulty ??= self::DEFAULT_DIFFICULTY;

        $parameters = [
            'algorithm' => self::ALGORITHM,
            'cost'      => (int) $difficulty['cost'],
            'expiresAt' => time() + self::EXPIRATION_SECONDS,
            'keyLength' => self::KEY_LENGTH,
            'keyPrefix' => strtolower($difficulty['keyPrefix']),
            'nonce'     => bin2hex(random_bytes(16)),
            'salt'      => bin2hex(random_bytes(16)),
        ];

        return [
            'parameters' => $parameters,
            'signature'  => self::sign($parameters),
        ];
    }

    /**
     * The JSON ALTCHA signs: object keys sorted at every level, null values
     * left out, slashes and Unicode unescaped like JavaScript's
     * JSON.stringify ("PBKDF2/SHA-256" holds a slash).
     */
    public static function canonicalJson(array $value): string
    {
        return json_encode(self::sortKeys($value), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    /**
     * The PBKDF2 key (raw bytes) for a counter, appended to the nonce as a
     * big-endian uint32 like ALTCHA's PasswordBuffer does.
     */
    public static function deriveKey(array $parameters, int $counter): string
    {
        return hash_pbkdf2(
            'sha256',
            hex2bin($parameters['nonce']) . pack('N', $counter),
            hex2bin($parameters['salt']),
            $parameters['cost'],
            $parameters['keyLength'],
            true
        );
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

        $challenge = $data['challenge'] ?? null;
        $solution  = $data['solution'] ?? null;
        if (!is_array($challenge) || !is_array($solution)) return false;

        $parameters = $challenge['parameters'] ?? null;
        $signature  = $challenge['signature'] ?? null;
        if (!is_array($parameters) || !is_string($signature) || !self::isWellFormed($parameters)) return false;

        // Cheap checks first, so a forged or stale challenge costs no PBKDF2
        if (time() > $parameters['expiresAt']) return false;
        if (!hash_equals(self::sign($parameters), $signature)) return false;

        $counter    = $solution['counter'] ?? null;
        $derivedKey = $solution['derivedKey'] ?? null;
        if (!is_int($counter) || $counter < 0 || $counter > self::MAX_COUNTER) return false;
        if (!is_string($derivedKey) || !preg_match('/^(?:[0-9a-f]{2})+$/D', $derivedKey)) return false;

        // A solved challenge is good for one submission only. The marker
        // lives until the challenge expires, after which the expiry check
        // above rejects it anyway.
        $marker = self::USED_PREFIX . $parameters['nonce'];
        if (get_transient($marker)) return false;

        $expected = bin2hex(static::deriveKey($parameters, $counter));
        if (!hash_equals($expected, $derivedKey) || !str_starts_with($expected, $parameters['keyPrefix'])) return false;

        set_transient($marker, 1, max(1, $parameters['expiresAt'] - time()));

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
        <tr>
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
            SecretBox::writeOption(self::OPTION_KEY, self::generateKey());
        }

        wp_safe_redirect(CaptchaSettings::tabUrl(self::id(), ['lcmt_altcha_key' => 'regenerated']));
        exit;
    }

    private static function getKey(): string
    {
        if (self::isKeyFromConfig()) {
            return (string) ALTCHA_HMAC_KEY;
        }

        // Unreadable counts as unset: a new key replaces it, no email needed
        $key = SecretBox::readOption(self::OPTION_KEY, __('ALTCHA key', 'lcmt-dev-mailer'), false);

        if ($key === '') {
            $key = self::generateKey();

            // add_option only writes when no other request stored a key
            // first; read back so every request signs with the same one. An
            // unreadable key is still stored, so it is replaced outright.
            if (get_option(self::OPTION_KEY, '') === '') {
                add_option(self::OPTION_KEY, SecretBox::seal($key), '', false);
            } else {
                SecretBox::writeOption(self::OPTION_KEY, $key);
            }

            $key = SecretBox::readOption(self::OPTION_KEY, __('ALTCHA key', 'lcmt-dev-mailer'), false) ?: $key;
        }

        return $key;
    }

    private static function generateKey(): string
    {
        return bin2hex(random_bytes(32));
    }

    private static function sign(array $parameters): string
    {
        return hash_hmac('sha256', self::canonicalJson($parameters), self::getKey());
    }

    /**
     * Exactly the parameters this class issues, with the types it issues:
     * an integer stays an integer in the canonical JSON, and a missing or
     * extra key cannot slip through the signature check.
     */
    private static function isWellFormed(array $parameters): bool
    {
        $keys = array_keys($parameters);
        sort($keys, SORT_STRING);

        return $keys === self::PARAMETER_KEYS
            && $parameters['algorithm'] === self::ALGORITHM
            && is_int($parameters['cost']) && $parameters['cost'] > 0
            && is_int($parameters['expiresAt'])
            && $parameters['keyLength'] === self::KEY_LENGTH
            && is_string($parameters['keyPrefix']) && preg_match('/^[0-9a-f]{1,64}$/D', $parameters['keyPrefix'])
            && is_string($parameters['nonce']) && preg_match('/^[0-9a-f]{32}$/D', $parameters['nonce'])
            && is_string($parameters['salt']) && preg_match('/^[0-9a-f]{32}$/D', $parameters['salt']);
    }

    /**
     * Sort object keys at every level and drop null values, like ALTCHA's
     * sortKeys(); lists keep their order.
     */
    private static function sortKeys(array $value): array
    {
        if (!array_is_list($value)) {
            $value = array_filter($value, fn ($item) => $item !== null);
            ksort($value, SORT_STRING);
        }

        return array_map(fn ($item) => is_array($item) ? self::sortKeys($item) : $item, $value);
    }
}
