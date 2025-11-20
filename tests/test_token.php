<?php
// 測試 TokenService 與 Auth 的基本行為
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/config/config.php';
$cfg = config();

spl_autoload_register(function (string $class) use ($root) {
    $prefix = 'App\\';
    if (strncmp($class, $prefix, strlen($prefix)) !== 0) return;
    $rel = substr($class, strlen($prefix));
    $path = $root . '/app/' . str_replace('\\', '/', $rel) . '.php';
    if (file_exists($path)) require_once $path;
});

use App\Core\Auth;
use App\Services\TokenService;

Auth::init($cfg['jwt'], $cfg['app_url']);
TokenService::init($cfg['jwt']);

$userId = 123;
$tokens = TokenService::issueTokens($userId, 'CLI', '127.0.0.1');
assert(isset($tokens['access_token']));
assert(isset($tokens['refresh_token']));

$payload = App\Core\Auth::verifyAccessToken($tokens['access_token']);
assert($payload['sub'] === $userId);

echo "Token tests passed\n";
?>