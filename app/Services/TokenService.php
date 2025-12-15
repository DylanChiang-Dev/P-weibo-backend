<?php
namespace App\Services;

use App\Core\Auth;
use App\Core\Response;
use App\Core\Logger;
use App\Models\RefreshToken;
use App\Exceptions\UnauthorizedException;

class TokenService {
    private static array $jwt;
    private static string $refreshSecret;
    private static int $refreshReuseGraceSeconds = 0;

    public static function init(array $jwtConfig): void {
        self::$jwt = $jwtConfig;
        self::$refreshSecret = $jwtConfig['refresh_secret'];
        self::$refreshReuseGraceSeconds = max(0, (int)($jwtConfig['refresh_reuse_grace_seconds'] ?? 0));
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

    public static function refresh(string $refreshToken, string $ua = '', string $ip = ''): array {
        $hash = self::hashToken($refreshToken);
        $row = RefreshToken::findValid($hash);
        if (!$row) {
            // reuse detection：若找到紀錄但已 revoked，可能是被盜用或是並發 refresh（多請求同時刷新）
            $any = RefreshToken::findAny($hash);
            if ($any && (int)($any['revoked'] ?? 0) === 1) {
                $userId = (int)($any['user_id'] ?? 0);

                $grace = self::$refreshReuseGraceSeconds;
                if ($userId > 0 && $grace > 0 && $ua !== '' && $ip !== '') {
                    $latest = RefreshToken::findLatestValidForContext($userId, $ua, $ip);
                    if ($latest) {
                        $createdAt = strtotime((string)($latest['created_at'] ?? '')) ?: 0;
                        if ($createdAt > 0 && $createdAt >= (time() - $grace)) {
                            Logger::warn('refresh_race_detected', ['user_id' => $userId, 'grace_seconds' => $grace]);
                            // Issue a new token pair to keep this request working under concurrent refresh.
                            return self::issueTokens($userId, $ua, $ip);
                        }
                    }
                }

                self::revokeAll($userId);
                Logger::warn('refresh_reuse_detected', ['user_id' => $userId]);
                throw new UnauthorizedException('Invalid refresh token');
            }

            Logger::warn('refresh_invalid', []);
            throw new UnauthorizedException('Invalid refresh token');
        }
        // rotation：撤銷舊 token，產新 token
        RefreshToken::revokeByHash($hash);
        $userId = (int)$row['user_id'];
        $tokens = self::issueTokens(
            $userId,
            $ua !== '' ? $ua : (string)($row['user_agent'] ?? ''),
            $ip !== '' ? $ip : (string)($row['ip'] ?? '')
        );
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
