<?php
namespace App\Models;

use App\Core\Database;

class RefreshToken {
    public static function create(int $userId, string $tokenHash, string $userAgent, string $ip, int $ttl): void {
        $expiresAt = date('Y-m-d H:i:s', time() + $ttl);
        Database::execute('INSERT INTO refresh_tokens (user_id, token_hash, user_agent, ip, expires_at) VALUES (?, ?, ?, ?, ?)', [
            $userId, $tokenHash, $userAgent, $ip, $expiresAt
        ]);
    }

    public static function findValid(string $tokenHash): ?array {
        $stmt = Database::query('SELECT * FROM refresh_tokens WHERE token_hash = ? AND revoked = 0 AND expires_at > NOW() LIMIT 1', [$tokenHash]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function findAny(string $tokenHash): ?array {
        $stmt = Database::query('SELECT * FROM refresh_tokens WHERE token_hash = ? LIMIT 1', [$tokenHash]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function findLatestValidForContext(int $userId, string $userAgent, string $ip): ?array {
        $stmt = Database::query(
            'SELECT * FROM refresh_tokens
             WHERE user_id = ? AND revoked = 0 AND expires_at > NOW() AND user_agent = ? AND ip = ?
             ORDER BY created_at DESC
             LIMIT 1',
            [$userId, $userAgent, $ip]
        );
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function revokeByHash(string $tokenHash): void {
        Database::execute('UPDATE refresh_tokens SET revoked = 1, revoked_at = NOW() WHERE token_hash = ?', [$tokenHash]);
    }

    public static function revokeAllByUser(int $userId): void {
        Database::execute('UPDATE refresh_tokens SET revoked = 1, revoked_at = NOW() WHERE user_id = ?', [$userId]);
    }
}
?>
