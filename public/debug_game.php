<?php
require_once __DIR__ . '/_tool_guard.php';
/**
 * 診斷 Games API 問題的臨時腳本
 */

header('Content-Type: application/json; charset=utf-8');

$root = dirname(__DIR__);
require_once $root . '/config/config.php';
require_once $root . '/app/Core/Database.php';
require_once $root . '/app/Core/QueryBuilder.php';
require_once $root . '/app/Models/UserGame.php';

$config = config();

try {
    \App\Core\Database::init($config['db']);
    $pdo = \App\Core\Database::getPdo();
    
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
    
    // 模擬前端發送的數據
    $data = [
        'rawg_id' => 1022,
        'title' => 'The Legend of Zelda',
        'cover_image_cdn' => 'https://images.igdb.com/igdb/image/upload/t_cover_big/co1uii.jpg',
        'overview' => 'The Legend of Zelda is the first title...',
        'release_date' => '1986-02-21',
        'platforms' => ['Family Computer Disk System', 'Nintendo 3DS'],
        'status' => 'completed',
        'my_rating' => 0,
        'platform' => ''
    ];
    
    $userId = 1;
    $igdbId = isset($data['igdb_id']) ? (int)$data['igdb_id'] : null;
    $rawgId = isset($data['rawg_id']) && $data['rawg_id'] ? (int)$data['rawg_id'] : null;
    
    $gameData = [
        'user_id' => $userId,
        'rawg_id' => $rawgId,
        'igdb_id' => $igdbId,
        'name' => $data['name'] ?? $data['title'] ?? null,
        'cover_image_cdn' => $data['cover_image_cdn'] ?? $data['cover_url'] ?? null,
        'overview' => $data['overview'] ?? null,
        'genres' => isset($data['genres']) ? json_encode($data['genres']) : null,
        'external_rating' => isset($data['external_rating']) ? (float)$data['external_rating'] : null,
        'backdrop_image_cdn' => $data['backdrop_image_cdn'] ?? null,
        'platforms' => isset($data['platforms']) ? json_encode($data['platforms']) : null,
        'developers' => isset($data['developers']) ? json_encode($data['developers']) : null,
        'publishers' => isset($data['publishers']) ? json_encode($data['publishers']) : null,
        'game_modes' => isset($data['game_modes']) ? json_encode($data['game_modes']) : null,
        'my_rating' => isset($data['my_rating']) ? (float)$data['my_rating'] : null,
        'my_review' => $data['my_review'] ?? $data['review'] ?? null,
        'playtime_hours' => isset($data['playtime_hours']) ? (int)$data['playtime_hours'] : null,
        'platform' => $data['platform'] ?? null,
        'status' => $data['status'] ?? 'played',
        'release_date' => $formatDate($data['release_date'] ?? $data['released'] ?? null),
        'completed_date' => $formatDate($data['completed_date'] ?? $data['date'] ?? null)
    ];
    
    echo "準備插入的數據:\n";
    echo json_encode($gameData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    echo "\n\n";
    
    // 嘗試插入
    $id = \App\Models\UserGame::create($gameData);
    
    // 刪除測試數據
    $pdo->exec("DELETE FROM user_games WHERE id = $id");
    
    echo json_encode([
        'success' => true,
        'message' => 'Controller 模擬成功！ID: ' . $id . ' (已刪除)'
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'code' => $e->getCode()
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
