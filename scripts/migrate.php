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

Logger::init($config['log']['path']);
Database::init($config['db']);

$dir = $root . '/migrations';
$files = glob($dir . '/*.sql');
sort($files);

foreach ($files as $file) {
    $sql = file_get_contents($file);
    if ($sql === false) { echo "Skip $file\n"; continue; }
    echo "Running: $file\n";
    $pdo = Database::pdo();
    $pdo->exec($sql);
}
echo "Done.\n";
?>