<?php
namespace App\Core;

use PDO;
use PDOException;

class MigrationRunner {
    /**
     * Run pending SQL migrations from a directory.
     *
     * Options:
     * - tolerate_existing (bool): if true, ignores common "already exists" DDL errors.
     * - lock_name (string): MySQL GET_LOCK name.
     * - lock_timeout (int): seconds.
     */
    public static function run(string $migrationsDir, array $opts = []): array {
        $pdo = Database::pdo();

        $lockName = (string)($opts['lock_name'] ?? 'pweibo:schema_migrations');
        $lockTimeout = (int)($opts['lock_timeout'] ?? 10);

        $createdTrackingTable = self::ensureMigrationsTable($pdo);
        $tolerateExisting = array_key_exists('tolerate_existing', $opts)
            ? (bool)$opts['tolerate_existing']
            : $createdTrackingTable;

        $files = glob(rtrim($migrationsDir, '/') . '/*.sql') ?: [];
        sort($files);

        $applied = self::getApplied($pdo);
        $pending = [];
        foreach ($files as $file) {
            $name = basename($file);
            if (!isset($applied[$name])) {
                $pending[] = $file;
            }
        }

        if (count($pending) === 0) {
            return ['applied' => 0, 'pending' => 0, 'files' => []];
        }

        $locked = false;
        try {
            $locked = self::acquireLock($pdo, $lockName, $lockTimeout);
            if (!$locked) {
                throw new \RuntimeException('Could not acquire migration lock');
            }

            // Recompute pending inside lock.
            $applied = self::getApplied($pdo);
            $pending = [];
            foreach ($files as $file) {
                $name = basename($file);
                if (!isset($applied[$name])) {
                    $pending[] = $file;
                }
            }

            $appliedFiles = [];
            foreach ($pending as $file) {
                $name = basename($file);
                Logger::info('migration_start', ['migration' => $name]);

                $sql = file_get_contents($file);
                if ($sql === false) {
                    throw new \RuntimeException('Failed to read migration: ' . $name);
                }

                $statements = self::splitSqlStatements($sql);
                foreach ($statements as $stmt) {
                    self::execStatement($pdo, $stmt, $tolerateExisting);
                }

                $insert = $pdo->prepare('INSERT INTO schema_migrations (migration, applied_at) VALUES (?, NOW())');
                $insert->execute([$name]);
                $appliedFiles[] = $name;
                Logger::info('migration_applied', ['migration' => $name]);
            }

            return ['applied' => count($appliedFiles), 'pending' => count($pending), 'files' => $appliedFiles];
        } finally {
            if ($locked) {
                self::releaseLock($pdo, $lockName);
            }
        }
    }

    private static function ensureMigrationsTable(PDO $pdo): bool {
        $created = false;
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS schema_migrations (
                id INT AUTO_INCREMENT PRIMARY KEY,
                migration VARCHAR(255) NOT NULL UNIQUE,
                applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );

        // Detect "first run" by checking whether table is empty.
        $stmt = $pdo->query('SELECT COUNT(*) AS c FROM schema_migrations');
        $row = $stmt ? $stmt->fetch() : null;
        if ($stmt) {
            try { $stmt->closeCursor(); } catch (\Throwable) {}
        }
        if (is_array($row) && (int)($row['c'] ?? 0) === 0) {
            $created = true;
        }
        return $created;
    }

    private static function getApplied(PDO $pdo): array {
        $out = [];
        $stmt = $pdo->query('SELECT migration FROM schema_migrations');
        if (!$stmt) return $out;
        while ($row = $stmt->fetch()) {
            if (is_array($row) && isset($row['migration'])) {
                $out[(string)$row['migration']] = true;
            }
        }
        try { $stmt->closeCursor(); } catch (\Throwable) {}
        return $out;
    }

    private static function acquireLock(PDO $pdo, string $name, int $timeout): bool {
        try {
            $stmt = $pdo->prepare('SELECT GET_LOCK(?, ?) AS l');
            $stmt->execute([$name, $timeout]);
            $row = $stmt->fetch();
            try { $stmt->closeCursor(); } catch (\Throwable) {}
            return is_array($row) && (int)($row['l'] ?? 0) === 1;
        } catch (\Throwable) {
            // If GET_LOCK isn't available, run without lock (best-effort).
            return true;
        }
    }

    private static function releaseLock(PDO $pdo, string $name): void {
        try {
            $stmt = $pdo->prepare('SELECT RELEASE_LOCK(?) AS r');
            $stmt->execute([$name]);
            try { $stmt->closeCursor(); } catch (\Throwable) {}
        } catch (\Throwable) {
            // ignore
        }
    }

    private static function execStatement(PDO $pdo, string $sql, bool $tolerateExisting): void {
        $trimmed = trim($sql);
        if ($trimmed === '') return;

        // Compat: MySQL 5.7 doesn't support `ADD COLUMN IF NOT EXISTS`, but many of this repo's
        // migrations use it. We normalize to plain `ADD COLUMN` and rely on tolerateExisting
        // to skip duplicate-column errors on bootstrap.
        $normalized = self::normalizeCompatSql($trimmed);

        try {
            $pdo->exec($normalized);
        } catch (PDOException $e) {
            // If we hit a syntax error that may be caused by unsupported IF NOT EXISTS, retry with it stripped.
            $driverCode = (int)($e->errorInfo[1] ?? 0);
            if ($driverCode === 1064 && stripos($trimmed, 'if not exists') !== false) {
                $retry = self::stripUnsupportedIfNotExists($trimmed);
                if ($retry !== $trimmed) {
                    $pdo->exec($retry);
                    return;
                }
            }

            if ($tolerateExisting && self::isTolerableDdlError($e)) {
                Logger::warn('migration_stmt_skipped', [
                    'reason' => 'tolerable_error',
                    'sqlstate' => (string)($e->errorInfo[0] ?? $e->getCode() ?? ''),
                    'driver_code' => (int)($e->errorInfo[1] ?? 0),
                    'message' => $e->getMessage(),
                ]);
                return;
            }
            throw $e;
        }
    }

    private static function normalizeCompatSql(string $sql): string {
        // Remove unsupported `IF NOT EXISTS` clauses from ALTER statements for older MySQL.
        $sql = self::stripUnsupportedIfNotExists($sql);
        return $sql;
    }

    private static function stripUnsupportedIfNotExists(string $sql): string {
        $sql = preg_replace('/\\bADD\\s+COLUMN\\s+IF\\s+NOT\\s+EXISTS\\b/i', 'ADD COLUMN', $sql) ?? $sql;
        $sql = preg_replace('/\\bADD\\s+INDEX\\s+IF\\s+NOT\\s+EXISTS\\b/i', 'ADD INDEX', $sql) ?? $sql;
        $sql = preg_replace('/\\bADD\\s+UNIQUE\\s+INDEX\\s+IF\\s+NOT\\s+EXISTS\\b/i', 'ADD UNIQUE INDEX', $sql) ?? $sql;
        $sql = preg_replace('/\\bADD\\s+KEY\\s+IF\\s+NOT\\s+EXISTS\\b/i', 'ADD KEY', $sql) ?? $sql;
        return $sql;
    }

    private static function isTolerableDdlError(PDOException $e): bool {
        $driverCode = (int)($e->errorInfo[1] ?? 0);
        return in_array($driverCode, [
            1050, // Table already exists
            1060, // Duplicate column name
            1061, // Duplicate key name
            1062, // Duplicate entry (e.g., unique insert during backfill)
            1091, // Can't DROP ... check that column/key exists
        ], true);
    }

    /**
     * Splits a SQL file into statements, handling quotes and comments.
     * Assumes no custom DELIMITER usage (true for this repo).
     */
    private static function splitSqlStatements(string $sql): array {
        $sql = str_replace("\r\n", "\n", $sql);
        $len = strlen($sql);

        $stmts = [];
        $buf = '';

        $inSingle = false;
        $inDouble = false;
        $inBacktick = false;
        $inLineComment = false;
        $inBlockComment = false;

        for ($i = 0; $i < $len; $i++) {
            $ch = $sql[$i];
            $next = $i + 1 < $len ? $sql[$i + 1] : '';

            if ($inLineComment) {
                $buf .= $ch;
                if ($ch === "\n") {
                    $inLineComment = false;
                }
                continue;
            }

            if ($inBlockComment) {
                $buf .= $ch;
                if ($ch === '*' && $next === '/') {
                    $buf .= $next;
                    $i++;
                    $inBlockComment = false;
                }
                continue;
            }

            if (!$inSingle && !$inDouble && !$inBacktick) {
                if ($ch === '-' && $next === '-') {
                    $inLineComment = true;
                    $buf .= $ch;
                    continue;
                }
                if ($ch === '#') {
                    $inLineComment = true;
                    $buf .= $ch;
                    continue;
                }
                if ($ch === '/' && $next === '*') {
                    $inBlockComment = true;
                    $buf .= $ch . $next;
                    $i++;
                    continue;
                }
            }

            if ($ch === "'" && !$inDouble && !$inBacktick) {
                $escaped = $i > 0 && $sql[$i - 1] === '\\';
                if (!$escaped) $inSingle = !$inSingle;
                $buf .= $ch;
                continue;
            }

            if ($ch === '"' && !$inSingle && !$inBacktick) {
                $escaped = $i > 0 && $sql[$i - 1] === '\\';
                if (!$escaped) $inDouble = !$inDouble;
                $buf .= $ch;
                continue;
            }

            if ($ch === '`' && !$inSingle && !$inDouble) {
                $inBacktick = !$inBacktick;
                $buf .= $ch;
                continue;
            }

            if ($ch === ';' && !$inSingle && !$inDouble && !$inBacktick) {
                $stmts[] = $buf;
                $buf = '';
                continue;
            }

            $buf .= $ch;
        }

        if (trim($buf) !== '') {
            $stmts[] = $buf;
        }

        return $stmts;
    }
}
