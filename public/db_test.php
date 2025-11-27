<?php
require_once __DIR__ . '/../vendor/autoload.php';

try {
    $config = require __DIR__ . '/../config/config.php';
    $db = new \App\Core\Database($config['db']);
    $pdo = $db->getPdo();

    echo "<h3>Current Database: " . $config['db']['name'] . "</h3>";

    // Check users table columns
    $stmt = $pdo->query("SHOW COLUMNS FROM users");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "<h4>Users Table Columns:</h4>";
    echo "<ul>";
    foreach ($columns as $col) {
        echo "<li>$col</li>";
    }
    echo "</ul>";

    // Check if avatar_path exists
    if (!in_array('avatar_path', $columns)) {
        echo "<h3 style='color:red'>❌ Missing column: avatar_path</h3>";
    } else {
        echo "<h3 style='color:green'>✅ Column exists: avatar_path</h3>";
    }

    if (!in_array('display_name', $columns)) {
        echo "<h3 style='color:red'>❌ Missing column: display_name</h3>";
    } else {
        echo "<h3 style='color:green'>✅ Column exists: display_name</h3>";
    }

} catch (\Throwable $e) {
    echo "<h1>Error: " . $e->getMessage() . "</h1>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}
