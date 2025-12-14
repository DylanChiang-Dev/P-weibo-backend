<?php
namespace App\Core;

use App\Exceptions\InternalServerErrorException;

/**
 * AES-256-GCM helper for encrypting small JSON blobs (e.g. third-party credentials).
 *
 * Storage format: base64( iv(12) || tag(16) || ciphertext )
 */
class Crypto {
    private const IV_BYTES = 12;
    private const TAG_BYTES = 16;
    private const CIPHER = 'aes-256-gcm';

    private static function keyBytes(): string {
        $config = \config();

        $raw = (string)($config['integrations']['enc_key'] ?? '');
        if ($raw !== '') {
            $decoded = base64_decode($raw, true);
            if (is_string($decoded) && strlen($decoded) === 32) {
                return $decoded;
            }
            // Accept raw strings too (derive 32 bytes deterministically).
            return hash('sha256', $raw, true);
        }

        // Zero-config fallback: derive from existing secrets so server .env edits aren't required.
        $jwt = $config['jwt'] ?? [];
        $fallback = (string)($jwt['refresh_secret'] ?? ($jwt['access_secret'] ?? ''));
        if ($fallback === '') {
            throw new InternalServerErrorException('Integrations encryption key not configured');
        }

        return hash('sha256', $fallback, true);
    }

    public static function encryptJson(array $data): string {
        $key = self::keyBytes();
        $iv = random_bytes(self::IV_BYTES);
        $tag = '';
        $plaintext = json_encode($data, JSON_UNESCAPED_UNICODE);
        if ($plaintext === false) {
            throw new InternalServerErrorException('Failed to encode payload');
        }

        $ciphertext = openssl_encrypt($plaintext, self::CIPHER, $key, OPENSSL_RAW_DATA, $iv, $tag);
        if (!is_string($ciphertext) || $ciphertext === '' || !is_string($tag) || strlen($tag) !== self::TAG_BYTES) {
            throw new InternalServerErrorException('Failed to encrypt payload');
        }

        return base64_encode($iv . $tag . $ciphertext);
    }

    public static function decryptJson(string $blob): ?array {
        $key = self::keyBytes();
        $raw = base64_decode($blob, true);
        if (!is_string($raw) || strlen($raw) < (self::IV_BYTES + self::TAG_BYTES + 1)) {
            return null;
        }

        $iv = substr($raw, 0, self::IV_BYTES);
        $tag = substr($raw, self::IV_BYTES, self::TAG_BYTES);
        $ciphertext = substr($raw, self::IV_BYTES + self::TAG_BYTES);

        $plaintext = openssl_decrypt($ciphertext, self::CIPHER, $key, OPENSSL_RAW_DATA, $iv, $tag);
        if (!is_string($plaintext) || $plaintext === '') {
            return null;
        }

        $data = json_decode($plaintext, true);
        return is_array($data) ? $data : null;
    }
}
?>

