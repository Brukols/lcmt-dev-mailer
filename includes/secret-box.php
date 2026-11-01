<?php

namespace LcmtDevMailer;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Encrypts the secrets kept in the database (spam protection keys), so a copy
 * of the database alone does not give them away.
 *
 * The key comes from the LCMT_MAILER_ENCRYPTION_KEY constant when wp-config.php
 * defines it, otherwise from WordPress's AUTH_KEY and SECURE_AUTH_SALT. When
 * neither exists, values are stored as they are (see canEncrypt()).
 */
class SecretBox
{
    public const PREFIX = 'lcmt1:';
    public const CONST_KEY = 'LCMT_MAILER_ENCRYPTION_KEY';

    private const PLACEHOLDER = 'put your unique phrase here';

    public static function isEncrypted(string $stored): bool
    {
        return strncmp($stored, self::PREFIX, strlen(self::PREFIX)) === 0;
    }

    /**
     * Whether a key can be derived: the constant, or real WordPress salts.
     */
    public static function canEncrypt(): bool
    {
        return self::material() !== '';
    }

    /**
     * @throws \LogicException When no key can be derived (see canEncrypt()).
     */
    public static function encrypt(string $plain): string
    {
        if (!self::canEncrypt()) {
            throw new \LogicException('SecretBox::encrypt() needs the ' . self::CONST_KEY . ' constant or WordPress security keys; check canEncrypt() or use seal().');
        }

        self::loadSodium();

        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

        return self::PREFIX . base64_encode($nonce . sodium_crypto_secretbox($plain, $nonce, self::key()));
    }

    /**
     * Null when the value is not encrypted, was tampered with, or was
     * encrypted with another key.
     */
    public static function decrypt(string $stored): ?string
    {
        if (!self::isEncrypted($stored)) return null;

        $raw = base64_decode(substr($stored, strlen(self::PREFIX)), true);
        if ($raw === false || strlen($raw) < SODIUM_CRYPTO_SECRETBOX_NONCEBYTES + SODIUM_CRYPTO_SECRETBOX_MACBYTES) return null;

        try {
            self::loadSodium();

            $plain = sodium_crypto_secretbox_open(
                substr($raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES),
                substr($raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES),
                self::key()
            );
        } catch (\Throwable $e) {
            return null;
        }

        return $plain === false ? null : $plain;
    }

    /**
     * Read a secret option. A value stored before encryption existed is
     * encrypted in place; one that cannot be read is reported and comes back
     * empty, so the caller can generate or ask for a new one.
     */
    public static function readOption(string $option, string $label, bool $report = true): string
    {
        $stored = (string) get_option($option, '');
        if ($stored === '') return '';

        if (!self::isEncrypted($stored)) {
            if (self::canEncrypt()) {
                self::writeOption($option, $stored);
            }

            return $stored;
        }

        $plain = self::decrypt($stored);
        if ($plain !== null) return $plain;

        if ($report) {
            UnreadableSecrets::report($option, $label);
        }

        return '';
    }

    /**
     * The value to store for a secret: encrypted when a key can be derived,
     * as it is otherwise.
     */
    public static function seal(string $plain): string
    {
        return $plain !== '' && self::canEncrypt() ? self::encrypt($plain) : $plain;
    }

    public static function writeOption(string $option, string $plain): void
    {
        $value = self::seal($plain);

        update_option($option, $value, false);
        UnreadableSecrets::clear($option);
    }

    /**
     * 32-byte key derived from the given key material.
     */
    private static function keyFrom(string $material): string
    {
        return hash_hkdf('sha256', $material, SODIUM_CRYPTO_SECRETBOX_KEYBYTES, 'lcmt-dev-mailer');
    }

    private static function key(): string
    {
        return self::keyFrom(self::material());
    }

    private static function material(): string
    {
        if (defined(self::CONST_KEY) && constant(self::CONST_KEY) !== '') {
            return (string) constant(self::CONST_KEY);
        }

        foreach (['AUTH_KEY', 'SECURE_AUTH_SALT'] as $name) {
            if (!defined($name) || constant($name) === '' || constant($name) === self::PLACEHOLDER) return '';
        }

        return AUTH_KEY . SECURE_AUTH_SALT;
    }

    /**
     * PHP's sodium extension is usual; WordPress bundles a polyfill otherwise.
     */
    private static function loadSodium(): void
    {
        if (!function_exists('sodium_crypto_secretbox')) {
            require_once ABSPATH . WPINC . '/sodium_compat/autoload.php';
        }
    }
}
