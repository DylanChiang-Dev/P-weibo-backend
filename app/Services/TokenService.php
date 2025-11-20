<?php
namespace App\Services;

use App\Core\Auth;
use App\Core\Response;
use App\Core\Logger;
use App\Models\RefreshToken;

class TokenService {
    private static array $jwt;
    private static string $refreshSecret;

    public static function init(array $jwtConfig): void {
        self::$jwt = $jwtConfig;
        self::$refreshSecret = $jwtConfig['refresh_secret'];
    }

    private static function hashToken(string $token): string {
        return hash_hmac('sha256', $token, self::$refreshSecret);
    }

    public static function issueTokens(int $userId, string $ua, string $ip): array {
        $access = Auth::issueAccessToken($userId, self::$jwt['access_ttl']);
        $refreshPlain = base64_encode(random_bytes(64));
        $hash = self::hashToken($refreshPlain);
        RefreshToken::create($userId, $hash, $ua, $ip, self::$jwt['refresh_ttl']);
        return [
            'access_token' => $access,
            'access_expires_in' => self::$jwt['access_ttl'],
            'refresh_token' => $refreshPlain,
        ];
    }

    public static function refresh(string $refreshToken): array {
        $hash = self::hashToken($refreshToken);
        $row = RefreshToken::findValid($hash);
        if (!$row) {
            // reuse detection：若找到紀錄但已 revoked，撤銷該使用者所有 refresh
            $any = RefreshToken::findAny($hash);
            if ($any && (int)$any['revoked'] === 1) {
                self::revokeAll((int)$any['user_id']);
                Logger::warn('refresh_reuse_detected', ['user_id' => (int)$any['user_id']]);
            } else {
                Logger::warn('refresh_invalid', []);
            }
            throw new \RuntimeException('Invalid refresh token', 401);
        }
        // rotation：撤銷舊 token，產新 token
        RefreshToken::revokeByHash($hash);
        $userId = (int)$row['user_id'];
        $tokens = self::issueTokens($userId, $row['user_agent'] ?? '', $row['ip'] ?? '');
        return $tokens;
    }

    public static function revoke(string $refreshToken): void {
        $hash = self::hashToken($refreshToken);
        RefreshToken::revokeByHash($hash);
    }

    public static function revokeAll(int $userId): void {
        RefreshToken::revokeAllByUser($userId);
    }
}
?>