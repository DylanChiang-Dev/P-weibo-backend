<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

$root = dirname(__DIR__);
require_once $root . '/config/config.php';
require_once $root . '/app/Core/Database.php';

$config = config();
\App\Core\Database::init($config['db']);
$pdo = \App\Core\Database::getPdo();

$results = [];

// 1. 檢查 neodb_id 欄位是否存在
$stmt = $pdo->query("SHOW COLUMNS FROM user_books LIKE 'neodb_id'");
$exists = $stmt->rowCount() > 0;

if ($exists) {
    $results[] = ['step' => 'check_column', 'status' => 'exists', 'message' => 'neodb_id 欄位已存在'];
} else {
    // 2. 添加 neodb_id 欄位
    try {
        $pdo->exec("ALTER TABLE user_books ADD COLUMN neodb_id VARCHAR(100) NULL AFTER google_books_id");
        $results[] = ['step' => 'add_column', 'status' => 'success', 'message' => '成功添加 neodb_id 欄位'];
    } catch (PDOException $e) {
        $results[] = ['step' => 'add_column', 'status' => 'error', 'message' => $e->getMessage()];
    }
}

// 3. 檢查索引是否存在
$stmt = $pdo->query("SHOW INDEX FROM user_books WHERE Key_name = 'idx_neodb_id'");
$indexExists = $stmt->rowCount() > 0;

if ($indexExists) {
    $results[] = ['step' => 'check_index', 'status' => 'exists', 'message' => 'idx_neodb_id 索引已存在'];
} else {
    try {
        $pdo->exec("CREATE INDEX idx_neodb_id ON user_books(neodb_id)");
        $results[] = ['step' => 'add_index', 'status' => 'success', 'message' => '成功添加 idx_neodb_id 索引'];
    } catch (PDOException $e) {
        $results[] = ['step' => 'add_index', 'status' => 'error', 'message' => $e->getMessage()];
    }
}

// 4. 驗證結果
$stmt = $pdo->query("SHOW COLUMNS FROM user_books LIKE 'neodb_id'");
$finalCheck = $stmt->rowCount() > 0;

echo json_encode([
    'success' => $finalCheck,
    'message' => $finalCheck ? 'neodb_id 欄位已就緒' : '添加失敗',
    'results' => $results
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
