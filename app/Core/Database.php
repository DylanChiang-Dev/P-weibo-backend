<?php
namespace App\Core;

use PDO;
use PDOException;
use App\Exceptions\ServiceUnavailableException;

class Database {
    private static ?PDO $pdo = null;

    public static function init(array $db): void {
        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $db['host'], $db['port'], $db['name'], $db['charset']);
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
        try {
            self::$pdo = new PDO($dsn, $db['user'], $db['pass'], $options);
        } catch (PDOException $e) {
            Logger::error('db_connect_failed', [
                'error' => $e->getMessage(),
                'host' => $db['host'] ?? null,
                'port' => $db['port'] ?? null,
                'name' => $db['name'] ?? null,
                'user' => $db['user'] ?? null,
            ]);
            throw new ServiceUnavailableException(
                'Database unavailable',
                0,
                ['reason' => 'db_connect_failed'],
                $e
            );
        }
    }

    public static function pdo(): PDO {
        if (!self::$pdo) throw new \RuntimeException('Database not initialized');
        return self::$pdo;
    }

    public static function getPdo(): PDO {
        return self::pdo();
    }

    public static function query(string $sql, array $params = []): \PDOStatement {
        $stmt = self::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public static function execute(string $sql, array $params = []): int {
        $stmt = self::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    public static function begin(): void { self::pdo()->beginTransaction(); }
    public static function commit(): void { self::pdo()->commit(); }
    public static function rollback(): void { if (self::pdo()->inTransaction()) self::pdo()->rollBack(); }
    public static function lastInsertId(): string { return self::pdo()->lastInsertId(); }
}
?>
