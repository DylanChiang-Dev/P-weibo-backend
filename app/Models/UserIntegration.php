<?php
namespace App\Models;

use App\Core\Database;
use App\Core\QueryBuilder;

class UserIntegration {
    private static function ensureTable(): void {
        try {
            Database::query('SELECT 1 FROM user_integrations LIMIT 1');
        } catch (\PDOException $e) {
            // Table missing (MySQL: 42S02)
            if (($e->getCode() ?? '') === '42S02') {
                Database::execute(
                    'CREATE TABLE IF NOT EXISTS user_integrations (
                        user_id BIGINT PRIMARY KEY,
                        credentials_enc MEDIUMTEXT NOT NULL,
                        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
                );
                return;
            }
            throw $e;
        }
    }

    public static function getByUserId(int $userId): ?array {
        self::ensureTable();
        return QueryBuilder::table('user_integrations')
            ->where('user_id', '=', $userId)
            ->first();
    }

    public static function upsertEncrypted(int $userId, string $credentialsEnc): void {
        self::ensureTable();
        Database::execute(
            'INSERT INTO user_integrations (user_id, credentials_enc) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE credentials_enc = VALUES(credentials_enc), updated_at = CURRENT_TIMESTAMP',
            [$userId, $credentialsEnc]
        );
    }
}
?>

