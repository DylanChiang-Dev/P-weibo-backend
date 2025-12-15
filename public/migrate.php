<?php
// Token-protected web migration tool.
// Usage:
// 1) Set MIGRATION_TOOL_TOKEN in .env
// 2) Visit: /migrate.php?token=YOUR_TOKEN

declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/config/config.php';
$config = config();

// Autoload App\Name\Class => app/Name/Class.php
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
use PDO;

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');

$token = (string)($config['migrations']['tool_token'] ?? '');
$provided = (string)($_GET['token'] ?? $_POST['token'] ?? '');
if ($token === '' || !hash_equals($token, $provided)) {
    http_response_code(403);
    echo '<h1>403 Forbidden</h1><p>Missing or invalid token.</p>';
    exit;
}

Logger::init($config['log']['path']);
$db = $config['db'];
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

$migrationsDir = $root . '/migrations';
$message = null;
$result = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'run') {
    try {
        $result = MigrationRunner::runWithPdo($pdo, $migrationsDir, [
            'tolerate_existing' => true,
        ]);
        $message = 'Migrations applied: ' . (int)($result['applied'] ?? 0);
    } catch (\Throwable $e) {
        $message = 'Failed: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES);
    }
}

$status = MigrationRunner::statusWithPdo($pdo, $migrationsDir);
$pending = $status['pending'] ?? [];

?>
<!doctype html>
<html>
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>Migration Tool</title>
    <style>
      body { font-family: system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif; padding: 20px; max-width: 900px; margin: 0 auto; }
      code { background: #f3f3f3; padding: 2px 6px; border-radius: 6px; }
      .box { border: 1px solid #ddd; border-radius: 10px; padding: 14px; margin: 14px 0; }
      .warn { color: #b00; }
      button { padding: 10px 14px; border-radius: 10px; border: 1px solid #333; background: #111; color: #fff; cursor: pointer; }
      button:disabled { opacity: .5; cursor: not-allowed; }
      ul { margin: 10px 0 0 20px; }
    </style>
  </head>
  <body>
    <h1>Database Migrations</h1>
    <p>Token ok. This page applies <code>migrations/*.sql</code> once and records them in <code>schema_migrations</code>.</p>

    <?php if ($message): ?>
      <div class="box">
        <strong>Result</strong>
        <div><?= $message ?></div>
        <?php if (is_array($result) && !empty($result['files'])): ?>
          <ul>
            <?php foreach ($result['files'] as $f): ?>
              <li><code><?= htmlspecialchars((string)$f, ENT_QUOTES) ?></code></li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </div>
    <?php endif; ?>

    <div class="box">
      <div><strong>Applied:</strong> <?= (int)($status['applied_count'] ?? 0) ?></div>
      <div><strong>Pending:</strong> <?= (int)($status['pending_count'] ?? 0) ?></div>
      <?php if (!empty($pending)): ?>
        <ul>
          <?php foreach ($pending as $p): ?>
            <li><code><?= htmlspecialchars((string)$p, ENT_QUOTES) ?></code></li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>

    <form method="post" class="box">
      <input type="hidden" name="token" value="<?= htmlspecialchars($provided, ENT_QUOTES) ?>" />
      <input type="hidden" name="action" value="run" />
      <p class="warn"><strong>Warning:</strong> only run this on the correct DB. Recommended: remove token after success.</p>
      <button type="submit" <?= empty($pending) ? 'disabled' : '' ?>>Run Pending Migrations</button>
    </form>
  </body>
</html>
