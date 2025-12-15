<?php
// 簡易 migration 執行器：依檔名排序執行 migrations/*.sql

declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/config/config.php';
$config = config();

spl_autoload_register(function (string $class) use ($root) {
    $prefix = 'App\\';
    if (strncmp($class, $prefix, strlen($prefix)) !== 0) return;
    $rel = substr($class, strlen($prefix));
    $path = $root . '/app/' . str_replace('\\', '/', $rel) . '.php';
    if (file_exists($path)) require_once $path;
});

use App\Core\Database;
use App\Core\Logger;
use App\Core\MigrationRunner;
use App\Core\MysqliMigrationRunner;
use PDO;

Logger::init($config['log']['path']);

$db = $config['db'];
$migrationsDir = $root . '/migrations';

// Prefer mysqli (avoids PDO "2014 unbuffered query" issues on some hosts)
try {
    $result = MysqliMigrationRunner::run($db, $migrationsDir, [
        'tolerate_existing' => true,
    ]);

    echo "Applied {$result['applied']} migration(s)\n";
    if (!empty($result['files'])) {
        foreach ($result['files'] as $f) {
            echo " - {$f}\n";
        }
    }
    echo "Done.\n";
    exit;
} catch (\Throwable $e) {
    echo "mysqli migration failed, falling back to PDO: {$e->getMessage()}\n";
}

$dsn = sprintf(
    'mysql:host=%s;port=%d;dbname=%s;charset=%s',
    $db['host'],
    (int)$db['port'],
    $db['name'],
    $db['charset']
);
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];
if (defined('PDO::MYSQL_ATTR_USE_BUFFERED_QUERY')) {
    $options[PDO::MYSQL_ATTR_USE_BUFFERED_QUERY] = true;
}
$pdo = new PDO($dsn, $db['user'], $db['pass'], $options);

$result = MigrationRunner::runWithPdo($pdo, $migrationsDir, [
    // CLI runs are often used to bootstrap/repair; tolerate common "already exists" errors.
    'tolerate_existing' => true,
]);

echo "Applied {$result['applied']} migration(s)\n";
if (!empty($result['files'])) {
    foreach ($result['files'] as $f) {
        echo " - {$f}\n";
    }
}
echo "Done.\n";
?>
