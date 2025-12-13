<?php
require_once __DIR__ . '/_tool_guard.php';
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 1);

$root = dirname(__DIR__);

try {
    // Load config
    require_once $root . '/config/config.php';
    $config = config();
    
    // Autoload
    spl_autoload_register(function ($class) use ($root) {
        $prefix = 'App\\';
        if (strpos($class, $prefix) !== 0) return;
        $relative = substr($class, strlen($prefix));
        $file = $root . '/app/' . str_replace('\\', '/', $relative) . '.php';
        if (file_exists($file)) require $file;
    });
    
    // Initialize database
    \App\Core\Database::init($config['db']);
    
    // Try to get posts
    $posts = \App\Models\Post::list(20, null, null);
    
    echo json_encode([
        'success' => true,
        'post_count' => count($posts),
        'posts' => $posts
    ], JSON_PRETTY_PRINT);
    
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => explode("\n", $e->getTraceAsString())
    ], JSON_PRETTY_PRINT);
}
