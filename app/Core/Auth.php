<?php
namespace App\Core;

class Auth {
    private static array $jwt;
    private static string $iss;

    public static function init(array $jwtConfig, string $iss): void {
        self::$jwt = $jwtConfig;
        self::$iss = $iss;
    }

    private static function base64url_encode(string $data): string {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private static function base64url_decode(string $data): string|false {
        $remainder = strlen($data) % 4;
        if ($remainder) {
            $data .= str_repeat('=', 4 - $remainder);
        }
        return base64_decode(strtr($data, '-_', '+/'));
    }

    public static function issueAccessToken(int $userId, int $ttl = null): string {
        $ttl = $ttl ?? self::$jwt['access_ttl'];
        $iat = time();
        $exp = $iat + $ttl;
        $jti = bin2hex(random_bytes(16));
        $payload = [
            'iss' => self::$iss,
            'sub' => $userId,
            'iat' => $iat,
            'exp' => $exp,
            'jti' => $jti,
        ];
        $header = ['alg' => 'HS256', 'typ' => 'JWT'];
        $segments = [
            self::base64url_encode(json_encode($header)),
            self::base64url_encode(json_encode($payload)),
        ];
        $signingInput = implode('.', $segments);
        $signature = hash_hmac('sha256', $signingInput, self::$jwt['access_secret'], true);
        $segments[] = self::base64url_encode($signature);
        return implode('.', $segments);
    }

    public static function verifyAccessToken(?string $token): array {
        if (!$token) throw new \RuntimeException('Missing token');
        $parts = explode('.', $token);
        if (count($parts) !== 3) throw new \RuntimeException('Invalid token');
        [$h64, $p64, $s64] = $parts;
        $signingInput = $h64 . '.' . $p64;
        $expected = self::base64url_encode(hash_hmac('sha256', $signingInput, self::$jwt['access_secret'], true));
        if (!hash_equals($expected, $s64)) throw new \RuntimeException('Bad signature');
        $payloadRaw = self::base64url_decode($p64);
        $payload = json_decode($payloadRaw, true);
        if (!$payload || ($payload['iss'] ?? '') !== self::$iss) throw new \RuntimeException('Bad iss');
        if (($payload['exp'] ?? 0) < time()) throw new \RuntimeException('Expired');
        return $payload;
    }

    public static function requireAccess(?string $token): array {
        try {
            $payload = self::verifyAccessToken($token);
            return ['id' => (int)$payload['sub']];
        } catch (\Throwable $e) {
            Response::json(['success' => false, 'error' => 'Unauthorized'], 401);
        }
        return [];
    }
}
?>