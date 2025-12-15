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

Logger::init($config['log']['path']);
Database::init($config['db']);

$result = MigrationRunner::run($root . '/migrations', [
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
