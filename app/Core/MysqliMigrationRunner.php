<?php
namespace App\Core;

class MysqliMigrationRunner {
    public static function run(array $db, string $migrationsDir, array $opts = []): array {
        $tolerateExisting = (bool)($opts['tolerate_existing'] ?? false);

        $mysqli = self::connect($db);
        try {
            self::ensureMigrationsTable($mysqli);
            $applied = self::getApplied($mysqli);

            $files = glob(rtrim($migrationsDir, '/') . '/*.sql') ?: [];
            $files = array_values(array_filter($files, fn ($f) => basename($f) !== 'schema.sql'));
            sort($files);

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

            $appliedFiles = [];
            foreach ($pending as $file) {
                $name = basename($file);
                Logger::info('migration_start', ['migration' => $name]);

                $sql = file_get_contents($file);
                if ($sql === false) {
                    throw new \RuntimeException('Failed to read migration: ' . $name);
                }

                try {
                    $statements = self::splitSqlStatements($sql);
                    foreach ($statements as $stmt) {
                        self::execStatement($mysqli, $stmt, $tolerateExisting);
                    }
                } catch (\Throwable $e) {
                    throw new \RuntimeException($name . ': ' . $e->getMessage(), 0, $e);
                }

                $safeName = $mysqli->real_escape_string($name);
                $ok = $mysqli->query("INSERT INTO schema_migrations (migration, applied_at) VALUES ('{$safeName}', NOW())");
                if (!$ok) {
                    if ($tolerateExisting && $mysqli->errno === 1062) {
                        // already recorded
                    } else {
                        throw new \RuntimeException('Failed to record migration: ' . $mysqli->error);
                    }
                }

                $appliedFiles[] = $name;
                Logger::info('migration_applied', ['migration' => $name]);
            }

            return ['applied' => count($appliedFiles), 'pending' => count($pending), 'files' => $appliedFiles];
        } finally {
            $mysqli->close();
        }
    }

    public static function status(array $db, string $migrationsDir): array {
        $mysqli = self::connect($db);
        try {
            self::ensureMigrationsTable($mysqli);
            $applied = self::getApplied($mysqli);

            $files = glob(rtrim($migrationsDir, '/') . '/*.sql') ?: [];
            $files = array_values(array_filter($files, fn ($f) => basename($f) !== 'schema.sql'));
            sort($files);

            $pending = [];
            foreach ($files as $file) {
                $name = basename($file);
                if (!isset($applied[$name])) {
                    $pending[] = $name;
                }
            }

            return [
                'pending' => $pending,
                'pending_count' => count($pending),
                'applied_count' => count($applied),
            ];
        } finally {
            $mysqli->close();
        }
    }

    private static function connect(array $db): \mysqli {
        if (!class_exists(\mysqli::class)) {
            throw new \RuntimeException('mysqli extension not available');
        }

        $host = (string)($db['host'] ?? '127.0.0.1');
        $port = (int)($db['port'] ?? 3306);
        $user = (string)($db['user'] ?? '');
        $pass = (string)($db['pass'] ?? '');
        $name = (string)($db['name'] ?? '');
        $charset = (string)($db['charset'] ?? 'utf8mb4');

        $mysqli = @new \mysqli($host, $user, $pass, $name, $port);
        if ($mysqli->connect_errno) {
            throw new \RuntimeException('MySQL connect failed: ' . $mysqli->connect_error);
        }

        if (!$mysqli->set_charset($charset)) {
            // fallback
            $mysqli->query("SET NAMES '" . $mysqli->real_escape_string($charset) . "'");
        }

        return $mysqli;
    }

    private static function ensureMigrationsTable(\mysqli $mysqli): void {
        $sql = 'CREATE TABLE IF NOT EXISTS schema_migrations (
            id INT AUTO_INCREMENT PRIMARY KEY,
            migration VARCHAR(255) NOT NULL UNIQUE,
            applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4';
        if (!$mysqli->query($sql)) {
            throw new \RuntimeException('Failed to create schema_migrations: ' . $mysqli->error);
        }
    }

    private static function getApplied(\mysqli $mysqli): array {
        $out = [];
        $res = $mysqli->query('SELECT migration FROM schema_migrations');
        if ($res instanceof \mysqli_result) {
            while ($row = $res->fetch_assoc()) {
                if (isset($row['migration'])) {
                    $out[(string)$row['migration']] = true;
                }
            }
            $res->free();
        }
        return $out;
    }

    private static function execStatement(\mysqli $mysqli, string $sql, bool $tolerateExisting): void {
        $trimmed = trim($sql);
        if ($trimmed === '') return;

        $normalized = self::stripUnsupportedIfNotExists($trimmed);
        $analyzable = self::stripLeadingPreamble($normalized);

        $ok = $mysqli->query($normalized);
        if ($ok) return;

        // If a multi-op ALTER TABLE fails due to duplicate column/index, retry by splitting operations.
        if ($tolerateExisting && self::looksLikeAlterTable($analyzable)) {
            $code = (int)$mysqli->errno;
            if (in_array($code, [1050, 1060, 1061, 1062, 1091], true)) {
                self::execAlterTableOps($mysqli, $analyzable, $tolerateExisting);
                return;
            }
        }

        // Retry if syntax error likely caused by IF NOT EXISTS.
        if ($mysqli->errno === 1064 && stripos($trimmed, 'if not exists') !== false) {
            $retry = self::stripUnsupportedIfNotExists($trimmed);
            if ($retry !== $trimmed) {
                if ($mysqli->query($retry)) return;
            }
        }

        if ($tolerateExisting && self::isTolerableDdlError($mysqli->errno)) {
            Logger::warn('migration_stmt_skipped', [
                'reason' => 'tolerable_error',
                'driver_code' => $mysqli->errno,
                'message' => $mysqli->error,
            ]);
            return;
        }

        throw new \RuntimeException('Migration failed: ' . $mysqli->error);
    }

    private static function looksLikeAlterTable(string $sql): bool {
        return preg_match('/^ALTER\\s+TABLE\\s+/i', ltrim($sql)) === 1;
    }

    private static function execAlterTableOps(\mysqli $mysqli, string $sql, bool $tolerateExisting): void {
        $sql = rtrim(trim($sql), ';');
        $m = [];
        if (!preg_match('/^ALTER\\s+TABLE\\s+(.+?)\\s+(.*)$/is', $sql, $m)) {
            throw new \RuntimeException('Not an ALTER TABLE statement');
        }

        $table = trim($m[1]);
        $ops = trim($m[2]);
        if ($ops === '') {
            throw new \RuntimeException('No operations');
        }

        $parts = self::splitTopLevelCommas($ops);
        if (count($parts) <= 1) {
            throw new \RuntimeException('Not split-able');
        }

        foreach ($parts as $op) {
            $stmtSql = self::stripUnsupportedIfNotExists('ALTER TABLE ' . $table . ' ' . $op);
            $ok = $mysqli->query($stmtSql);
            if ($ok) continue;

            if ($tolerateExisting && self::isTolerableDdlError((int)$mysqli->errno)) {
                Logger::warn('migration_stmt_skipped', [
                    'reason' => 'tolerable_error',
                    'driver_code' => $mysqli->errno,
                    'message' => $mysqli->error,
                ]);
                continue;
            }

            throw new \RuntimeException('Migration failed: ' . $mysqli->error);
        }
    }

    private static function isTolerableDdlError(int $errno): bool {
        return in_array($errno, [
            1050, // Table already exists
            1060, // Duplicate column name
            1061, // Duplicate key name
            1062, // Duplicate entry
            1091, // Can't DROP ... doesn't exist
        ], true);
    }

    private static function stripUnsupportedIfNotExists(string $sql): string {
        $sql = preg_replace('/\\bADD\\s+COLUMN\\s+IF\\s+NOT\\s+EXISTS\\b/i', 'ADD COLUMN', $sql) ?? $sql;
        $sql = preg_replace('/\\bADD\\s+INDEX\\s+IF\\s+NOT\\s+EXISTS\\b/i', 'ADD INDEX', $sql) ?? $sql;
        $sql = preg_replace('/\\bADD\\s+UNIQUE\\s+INDEX\\s+IF\\s+NOT\\s+EXISTS\\b/i', 'ADD UNIQUE INDEX', $sql) ?? $sql;
        $sql = preg_replace('/\\bADD\\s+KEY\\s+IF\\s+NOT\\s+EXISTS\\b/i', 'ADD KEY', $sql) ?? $sql;
        return $sql;
    }

    private static function stripLeadingPreamble(string $sql): string {
        $head = ltrim($sql);

        // Remove leading /* */ block comments.
        while (preg_match('/^\\/\\*.*?\\*\\//s', $head, $m)) {
            $head = ltrim(substr($head, strlen($m[0])));
        }

        // Remove leading -- / # line comments.
        while (preg_match('/^(?:--|#)[^\\n]*\\n/s', $head, $m)) {
            $head = ltrim(substr($head, strlen($m[0])));
        }

        return $head;
    }

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

    private static function splitTopLevelCommas(string $sql): array {
        $len = strlen($sql);
        $buf = '';
        $parts = [];

        $inSingle = false;
        $inDouble = false;
        $inBacktick = false;
        $depth = 0;

        for ($i = 0; $i < $len; $i++) {
            $ch = $sql[$i];

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

            if (!$inSingle && !$inDouble && !$inBacktick) {
                if ($ch === '(') $depth++;
                if ($ch === ')' && $depth > 0) $depth--;
                if ($ch === ',' && $depth === 0) {
                    $part = trim($buf);
                    if ($part !== '') $parts[] = $part;
                    $buf = '';
                    continue;
                }
            }

            $buf .= $ch;
        }

        $tail = trim($buf);
        if ($tail !== '') $parts[] = $tail;
        return $parts;
    }
}
