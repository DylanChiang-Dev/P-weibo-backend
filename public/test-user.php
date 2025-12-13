<?php
require_once __DIR__ . '/_tool_guard.php';
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 1);

$root = dirname(__DIR__);

try {
    // Test autoloader
    spl_autoload_register(function ($class) use ($root) {
        $prefix = 'App\\';
        if (strpos($class, $prefix) !== 0) return;
        $relative = substr($class, strlen($prefix));
        $file = $root . '/app/' . str_replace('\\', '/', $relative) . '.php';
        
        echo "<!-- Trying to load: $file -->\n";
        
        if (file_exists($file)) {
            require $file;
            echo "<!-- Loaded: $file -->\n";
        } else {
            echo "<!-- NOT FOUND: $file -->\n";
        }
    });
    
    // Try to use User class
    require_once $root . '/app/Core/QueryBuilder.php';
    
    $userFile = $root . '/app/Models/User.php';
    echo json_encode([
        'success' => true,
        'user_file_exists' => file_exists($userFile),
        'user_file_path' => $userFile,
        'user_file_readable' => is_readable($userFile),
        'class_exists_before' => class_exists('App\\Models\\User', false),
    ], JSON_PRETTY_PRINT);
    
    // Try to load it
    $user = new \App\Models\User();
    
} catch (\Throwable $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString()
    ], JSON_PRETTY_PRINT);
}
