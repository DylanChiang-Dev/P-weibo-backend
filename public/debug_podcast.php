<?php
/**
 * 診斷 Podcast API 問題的臨時腳本
 * 用法: https://你的域名/debug_podcast.php?action=test
 */

header('Content-Type: application/json; charset=utf-8');

$root = dirname(__DIR__);
require_once $root . '/config/config.php';
require_once $root . '/app/Core/Database.php';

$config = config();
$action = $_GET['action'] ?? 'info';

try {
    \App\Core\Database::init($config['db']);
    $pdo = \App\Core\Database::getPdo();
    
    switch ($action) {
        case 'info':
            // 顯示表結構
            $stmt = $pdo->query("DESCRIBE user_podcasts");
            $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode([
                'success' => true,
                'table_structure' => $columns,
                'message' => '使用 ?action=test 來測試插入'
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            break;
            
        case 'test':
            // 模擬插入測試
            $testData = [
                'user_id' => 1,
                'podcast_id' => 'test_' . time(),
                'itunes_id' => null,
                'title' => 'Test Podcast',
                'cover_image_cdn' => 'https://example.com/test.jpg',
                'overview' => 'Test overview',
                'genres' => json_encode(['Test', 'Podcast']),
                'external_rating' => null,
                'artist_name' => 'Test Host',
                'feed_url' => 'https://example.com/feed.rss',
                'episode_count' => 10,
                'explicit' => 0,
                'host' => 'Test Host',
                'rss_feed' => 'https://example.com/feed.rss',
                'my_rating' => null,
                'my_review' => null,
                'episodes_listened' => 0,
                'total_episodes' => null,
                'status' => 'plan_to_listen',
                'first_release_date' => null,
                'release_date' => '2025-01-01',
                'completed_date' => null
            ];
            
            // 構建 INSERT 語句
            $columns = array_keys($testData);
            $placeholders = array_fill(0, count($testData), '?');
            
            $sql = sprintf(
                'INSERT INTO user_podcasts (%s) VALUES (%s)',
                implode(', ', $columns),
                implode(', ', $placeholders)
            );
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute(array_values($testData));
            $insertId = $pdo->lastInsertId();
            
            // 刪除測試數據
            $pdo->exec("DELETE FROM user_podcasts WHERE id = $insertId");
            
            echo json_encode([
                'success' => true,
                'message' => '插入測試成功！ID: ' . $insertId . ' (已刪除)',
                'columns_used' => $columns
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            break;
            
        case 'enum':
            // 檢查 status 欄位的 ENUM 值
            $stmt = $pdo->query("SHOW COLUMNS FROM user_podcasts WHERE Field = 'status'");
            $column = $stmt->fetch(PDO::FETCH_ASSOC);
            echo json_encode([
                'success' => true,
                'status_column' => $column
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            break;
            
        default:
            echo json_encode(['error' => 'Unknown action. Use: info, test, enum']);
    }
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'code' => $e->getCode(),
        'trace' => $e->getTraceAsString()
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'type' => get_class($e),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}
