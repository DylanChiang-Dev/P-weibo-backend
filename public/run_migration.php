<?php
declare(strict_types=1);

require_once __DIR__ . '/_tool_guard.php';
/**
 * 數據庫遷移執行工具
 * 
 * 使用方式：
 * - 執行特定遷移: https://your-api.com/run_migration.php?file=030_add_neodb_id_to_user_books
 * - 列出所有遷移: https://your-api.com/run_migration.php?action=list
 * - 執行多個遷移: https://your-api.com/run_migration.php?file=030,031,032
 */

header('Content-Type: application/json; charset=utf-8');

$response = ['success' => false, 'message' => '', 'results' => []];

try {
    // 引入配置
    $root = dirname(__DIR__);
    require_once $root . '/config/config.php';
    require_once $root . '/app/Core/Database.php';
    
    $config = config();
    
    // 連接數據庫
    \App\Core\Database::init($config['db']);
    $pdo = \App\Core\Database::getPdo();
    
    $action = $_GET['action'] ?? 'run';
    $migrationsDir = $root . '/migrations';
    
    // 列出所有遷移文件
    if ($action === 'list') {
        $files = glob($migrationsDir . '/*.sql');
        $migrations = [];
        foreach ($files as $file) {
            $basename = basename($file);
            $migrations[] = [
                'name' => $basename,
                'id' => preg_replace('/^(\d+)_.*\.sql$/', '$1', $basename)
            ];
        }
        usort($migrations, fn($a, $b) => (int)$a['id'] - (int)$b['id']);
        
        $response['success'] = true;
        $response['message'] = '找到 ' . count($migrations) . ' 個遷移文件';
        $response['migrations'] = $migrations;
        echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }
    
    // 執行遷移
    $fileParam = $_GET['file'] ?? '';
    if (empty($fileParam)) {
        $response['message'] = '請提供 file 參數。使用 ?action=list 查看所有可用遷移。';
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    $fileIds = array_map('trim', explode(',', $fileParam));
    $results = [];
    
    foreach ($fileIds as $fileId) {
        // 支持完整文件名或只提供 ID
        if (preg_match('/^\d+$/', $fileId)) {
            // 只提供了數字 ID，搜尋匹配的文件
            $matches = glob($migrationsDir . '/' . $fileId . '_*.sql');
            if (empty($matches)) {
                $results[] = ['file' => $fileId, 'success' => false, 'message' => '找不到該遷移文件'];
                continue;
            }
            $filePath = $matches[0];
        } elseif (preg_match('/^\d+_[\w]+$/', $fileId)) {
            // 提供了不帶 .sql 的文件名
            $filePath = $migrationsDir . '/' . $fileId . '.sql';
        } else {
            // 完整文件名
            $filePath = $migrationsDir . '/' . $fileId;
            if (!str_ends_with($filePath, '.sql')) {
                $filePath .= '.sql';
            }
        }
        
        if (!file_exists($filePath)) {
            $results[] = ['file' => basename($filePath), 'success' => false, 'message' => '文件不存在'];
            continue;
        }
        
        $sql = file_get_contents($filePath);
        $statements = array_filter(array_map('trim', preg_split('/;(?=\s*(?:--|ALTER|CREATE|DROP|INSERT|UPDATE|DELETE|$))/i', $sql)));
        
        $executed = 0;
        $skipped = 0;
        $errors = [];
        
        foreach ($statements as $stmt) {
            // 跳過純註釋行
            if (empty($stmt) || preg_match('/^--/', $stmt)) {
                continue;
            }
            
            try {
                $pdo->exec($stmt);
                $executed++;
            } catch (\PDOException $e) {
                $errMsg = $e->getMessage();
                // 忽略「列已存在」「索引已存在」等常見錯誤
                if (strpos($errMsg, '1060') !== false || // Duplicate column name
                    strpos($errMsg, '1061') !== false || // Duplicate key name
                    strpos($errMsg, '1068') !== false || // Multiple primary key
                    strpos($errMsg, '1050') !== false) { // Table already exists
                    $skipped++;
                } else {
                    $errors[] = substr($errMsg, 0, 200);
                }
            }
        }
        
        $results[] = [
            'file' => basename($filePath),
            'success' => empty($errors),
            'executed' => $executed,
            'skipped' => $skipped,
            'errors' => $errors
        ];
    }
    
    $allSuccess = !array_filter($results, fn($r) => !$r['success']);
    $response['success'] = $allSuccess;
    $response['message'] = $allSuccess ? '遷移執行成功' : '部分遷移有錯誤';
    $response['results'] = $results;
    
} catch (\Exception $e) {
    $response['message'] = '執行失敗: ' . $e->getMessage();
}

echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
