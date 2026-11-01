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
    public const OPTION_DIFFICULTY = 'lcmt_mailer_altcha_difficulty';
    public const DIFFICULTY_DEFAULT = 'normal';
    public const REGENERATE_ACTION = 'lcmt_mailer_regenerate_altcha_key';

    // ALTCHA v2 protocol: the browser looks for a counter whose PBKDF2 key,
    // derived from the nonce and the counter, starts with keyPrefix.
    private const ALGORITHM = 'PBKDF2/SHA-256';
    private const KEY_LENGTH = 32;
    private const MAX_COUNTER = 0xFFFFFFFF; // the counter is packed as a uint32
    // The levels of the Difficulty setting: PBKDF2 iterations per try, and
    // the hex prefix the derived key must start with (three digits: 1 try in
    // 4096). A long prefix and a low cost keep the server's check cheap (one
    // PBKDF2 at `cost`) for the same work in the browser.
    // Measured 2026-11-01 (median of 20 solves, altcha/lib solveChallenge,
    // one thread, Node 20 on an Apple M1): low 0.30 s, normal 0.99 to 1.3 s,
    // high 2.7 to 2.9 s. In Chrome with the CPU throttled 4x (a mid-range
    // phone), widget workers, median of 10 from focus to "verified": low
    // 0.50 s, normal 0.53 s (slowest 2.5 s), high 1.2 s (slowest 9.5 s),
    // under the form handler's 30 s wait.
    private const DIFFICULTIES = [
        'low'    => ['cost' => 200, 'keyPrefix' => '000'],
        'normal' => ['cost' => 1200, 'keyPrefix' => '000'],
        'high'   => ['cost' => 3500, 'keyPrefix' => '000'],
    ];
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
     * The saved level, or "normal" for anything else.
     *
     * @param mixed $value
     */
    public static function sanitizeDifficulty($value): string
    {
        return is_string($value) && isset(self::DIFFICULTIES[$value]) ? $value : self::DIFFICULTY_DEFAULT;
    }

    /**
     * The PBKDF2 cost and key prefix of the level chosen on the ALTCHA tab.
     *
     * @return array{cost: int, keyPrefix: string}
     */
    public static function difficulty(): array
    {
        return self::DIFFICULTIES[self::sanitizeDifficulty(get_option(self::OPTION_DIFFICULTY, self::DIFFICULTY_DEFAULT))];
    }

    /**
     * Whether a difficulty gives parameters isWellFormed() accepts: a
     * positive integer cost, and a non-empty lowercase hex prefix no longer
     * than the derived key.
     */
    public static function isValidDifficulty(array $difficulty): bool
    {
        $cost   = $difficulty['cost'] ?? null;
        $prefix = $difficulty['keyPrefix'] ?? null;

        return is_int($cost) && $cost > 0
            && is_string($prefix) && preg_match('/^[0-9a-f]{1,' . (self::KEY_LENGTH * 2) . '}$/D', $prefix) === 1;
    }

    /**
     * A new challenge, signed with the site key so the browser cannot lower
     * its difficulty or push back its expiry.
     *
     * @param array{cost: int, keyPrefix: string}|null $difficulty Defaults to difficulty().
     * @throws \InvalidArgumentException When the difficulty would issue a challenge validatePayload() refuses.
     */
    public static function createChallenge(?array $difficulty = null): array
    {
        $difficulty ??= self::difficulty();

        if (!self::isValidDifficulty($difficulty)) {
            throw new \InvalidArgumentException('Invalid ALTCHA difficulty.');
        }

        $parameters = [
            'algorithm' => self::ALGORITHM,
            'cost'      => $difficulty['cost'],
            'expiresAt' => time() + self::EXPIRATION_SECONDS,
            'keyLength' => self::KEY_LENGTH,
            'keyPrefix' => $difficulty['keyPrefix'],
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
     * Expects parameters already checked by isWellFormed() (hex nonce and salt, integer cost).
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
     *
     * The cheap checks come first (structure, expiry, signature, solution
     * format). The challenge is then spent before the PBKDF2 step, right or
     * wrong, so one signed challenge costs the server one key derivation at
     * most, however many solutions a bot tries for it.
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

        // A challenge is good for one try only, spent before the costly step.
        // The marker lives until the challenge expires, after which the
        // expiry check above rejects it anyway.
        $marker = self::USED_PREFIX . $parameters['nonce'];
        if (get_transient($marker)) return false;
        set_transient($marker, 1, max(1, $parameters['expiresAt'] - time()));

        $expected = bin2hex(static::deriveKey($parameters, $counter));

        return hash_equals($expected, $derivedKey) && str_starts_with($expected, $parameters['keyPrefix']);
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
        $difficulty  = self::sanitizeDifficulty(get_option(self::OPTION_DIFFICULTY, self::DIFFICULTY_DEFAULT));
        $levels      = [
            'low'    => __('Light (fast, stops the simplest bots)', 'lcmt-dev-mailer'),
            'normal' => __('Normal (recommended)', 'lcmt-dev-mailer'),
            'high'   => __('High (slower on phones)', 'lcmt-dev-mailer'),
        ];
        ?>
        <tr>
            <th scope="row"><label for="lcmt-altcha-difficulty"><?php esc_html_e('Difficulty', 'lcmt-dev-mailer'); ?></label></th>
            <td>
                <select id="lcmt-altcha-difficulty" name="<?= esc_attr(self::OPTION_DIFFICULTY) ?>" aria-describedby="lcmt-altcha-difficulty-description">
                    <?php foreach ($levels as $level => $label): ?>
                        <option value="<?= esc_attr($level) ?>" <?php selected($difficulty, $level); ?>><?= esc_html($label) ?></option>
                    <?php endforeach; ?>
                </select>
                <p class="description" id="lcmt-altcha-difficulty-description">
                    <?php esc_html_e('The time a visitor\'s browser spends proving it is not a bot, without them noticing. A higher level costs bots more but makes older phones wait a few seconds before sending.', 'lcmt-dev-mailer'); ?>
                </p>
            </td>
        </tr>
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
            && self::isValidDifficulty($parameters)
            && is_int($parameters['expiresAt'])
            && $parameters['keyLength'] === self::KEY_LENGTH
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
