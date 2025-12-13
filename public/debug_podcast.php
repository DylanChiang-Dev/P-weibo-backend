<?php
require_once __DIR__ . '/_tool_guard.php';
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
                'message' => '使用 ?action=test 來測試插入, ?action=simulate 來模擬 Controller'
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
            
        case 'simulate':
            // 模擬 addPodcast Controller 的完整處理
            require_once $root . '/app/Core/QueryBuilder.php';
            require_once $root . '/app/Models/UserPodcast.php';
            
            // 模擬前端發送的數據
            $inputData = [
                'podcast_id' => '917918570',
                'title' => 'Serial',
                'host' => 'Serial Productions & The New York Times',
                'cover_image_cdn' => 'https://is1-ssl.mzstatic.com/image/thumb/test.jpg',
                'genres' => ['News', 'Podcasts', 'True Crime'],
                'release_date' => '2025-10-30T09:50:00Z',
                'rss_feed' => 'https://example.com/feed.rss',
                'total_episodes' => 0,
                'episodes_listened' => 0,
                'status' => 'plan_to_listen',
                'my_rating' => 0,
                'my_review' => '',
                'completed_date' => '2025-12-06'
            ];
            
            $userId = 1;
            
            // 使用 Controller 相同的邏輯處理
            $itunesId = isset($inputData['itunes_id']) ? (int)$inputData['itunes_id'] : null;
            $podcastId = $inputData['podcast_id'] ?? null;
            
            // formatDate 函數
            $formatDate = function(?string $date): ?string {
                if (empty($date)) return null;
                if (strpos($date, 'T') !== false) {
                    $timestamp = strtotime($date);
                    return $timestamp ? date('Y-m-d', $timestamp) : null;
                }
                $timestamp = strtotime($date);
                return $timestamp ? date('Y-m-d', $timestamp) : null;
            };
            
            $podcastData = [
                'user_id' => $userId,
                'podcast_id' => $podcastId,
                'itunes_id' => $itunesId,
                'title' => $inputData['title'] ?? null,
                'cover_image_cdn' => $inputData['cover_image_cdn'] ?? $inputData['artwork_url'] ?? null,
                'overview' => $inputData['overview'] ?? null,
                'genres' => isset($inputData['genres']) ? json_encode($inputData['genres']) : null,
                'external_rating' => isset($inputData['external_rating']) ? (float)$inputData['external_rating'] : null,
                'artist_name' => $inputData['artist_name'] ?? $inputData['host'] ?? null,
                'feed_url' => $inputData['feed_url'] ?? $inputData['rss_feed'] ?? null,
                'episode_count' => isset($inputData['episode_count']) ? (int)$inputData['episode_count'] : null,
                'explicit' => isset($inputData['explicit']) ? (bool)$inputData['explicit'] : false,
                'host' => $inputData['host'] ?? null,
                'rss_feed' => $inputData['rss_feed'] ?? null,
                'my_rating' => isset($inputData['my_rating']) ? (float)$inputData['my_rating'] : null,
                'my_review' => $inputData['my_review'] ?? $inputData['review'] ?? null,
                'episodes_listened' => isset($inputData['episodes_listened']) ? (int)$inputData['episodes_listened'] : 0,
                'total_episodes' => isset($inputData['total_episodes']) && $inputData['total_episodes'] ? (int)$inputData['total_episodes'] : null,
                'status' => $inputData['status'] ?? 'listening',
                'first_release_date' => $formatDate($inputData['first_release_date'] ?? null),
                'release_date' => $formatDate($inputData['release_date'] ?? null),
                'completed_date' => $formatDate($inputData['completed_date'] ?? $inputData['date'] ?? null)
            ];
            
            // 嘗試用 UserPodcast::create
            $id = \App\Models\UserPodcast::create($podcastData);
            
            // 刪除測試數據
            $pdo->exec("DELETE FROM user_podcasts WHERE id = $id");
            
            echo json_encode([
                'success' => true,
                'message' => 'Controller 模擬成功！ID: ' . $id . ' (已刪除)',
                'processed_data' => $podcastData
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
            echo json_encode(['error' => 'Unknown action. Use: info, test, enum, simulate']);
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
