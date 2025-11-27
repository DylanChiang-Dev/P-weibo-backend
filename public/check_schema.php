<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../app/Core/Database.php';

try {
    $config = config();
    \App\Core\Database::init($config['db']);
    $pdo = \App\Core\Database::getPdo();
    
    echo "<h3>Users Table Schema</h3>";
    
    $stmt = $pdo->query("SHOW CREATE TABLE users");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "<pre>" . htmlspecialchars($result['Create Table']) . "</pre>";
    
} catch (Throwable $e) {
    echo "Error: " . $e->getMessage();
}
