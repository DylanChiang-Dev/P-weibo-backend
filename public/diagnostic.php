<?php
/**
 * Diagnostic Test Page
 * This page will help identify what's causing the 500 error
 */
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>系統診斷 - P-Weibo Backend</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            max-width: 1200px;
            margin: 40px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        h1 { color: #333; }
        .test {
            background: white;
            padding: 20px;
            margin-bottom: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .success { border-left: 4px solid #10b981; }
        .error { border-left: 4px solid #ef4444; }
        .warning { border-left: 4px solid #f59e0b; }
        .test h2 { margin-top: 0; font-size: 18px; }
        .test pre {
            background: #f9fafb;
            padding: 15px;
            border-radius: 4px;
            overflow-x: auto;
            font-size: 13px;
        }
        .status {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 4px;
            font-size: 14px;
            font-weight: 600;
            margin-left: 10px;
        }
        .status.ok { background: #10b981; color: white; }
        .status.fail { background: #ef4444; color: white; }
        .status.warn { background: #f59e0b; color: white; }
    </style>
</head>
<body>
    <h1>🔍 P-Weibo Backend 系統診斷</h1>
    
    <?php
    $root = dirname(__DIR__);
    $errors = [];
    
    // Test 1: PHP Version
    echo '<div class="test success">';
    echo '<h2>✅ PHP 版本 <span class="status ok">OK</span></h2>';
    echo '<pre>PHP Version: ' . phpversion() . '</pre>';
    echo '</div>';
    
    // Test 2: Required Extensions
    echo '<div class="test">';
    $required_extensions = ['pdo', 'pdo_mysql', 'mbstring', 'fileinfo', 'gd', 'openssl'];
    $missing_extensions = [];
    foreach ($required_extensions as $ext) {
        if (!extension_loaded($ext)) {
            $missing_extensions[] = $ext;
        }
    }
    
    if (empty($missing_extensions)) {
        echo '<h2>✅ PHP 擴展 <span class="status ok">OK</span></h2>';
        echo '<pre>' . implode(', ', $required_extensions) . '</pre>';
        echo '</div>';
    } else {
        echo '<h2>❌ PHP 擴展 <span class="status fail">缺少擴展</span></h2>';
        echo '<pre>缺少: ' . implode(', ', $missing_extensions) . '</pre>';
        echo '</div>';
        $errors[] = '缺少 PHP 擴展: ' . implode(', ', $missing_extensions);
    }
    
    // Test 3: Check .env file
    echo '<div class="test">';
    $envPath = $root . '/.env';
    if (file_exists($envPath)) {
        echo '<h2>✅ .env 文件 <span class="status ok">存在</span></h2>';
        echo '<pre>路徑: ' . $envPath . "\n";
        echo '大小: ' . filesize($envPath) . ' bytes</pre>';
        echo '</div>';
    } else {
        echo '<h2>❌ .env 文件 <span class="status fail">不存在</span></h2>';
        echo '<pre>應該在: ' . $envPath . '</pre>';
        echo '</div>';
        $errors[] = '.env 文件不存在';
    }
    
    // Test 4: Try to load config
    echo '<div class="test">';
    try {
        require_once $root . '/config/config.php';
        $config = config();
        
        echo '<h2>✅ 配置文件 <span class="status ok">加載成功</span></h2>';
        echo '<pre>';
        echo 'DB_HOST: ' . ($config['db']['host'] ?? 'NOT SET') . "\n";
        echo 'DB_NAME: ' . ($config['db']['name'] ?? 'NOT SET') . "\n";
        echo 'DB_USER: ' . ($config['db']['user'] ?? 'NOT SET') . "\n";
        echo 'UPLOAD_PATH: ' . ($config['upload']['path'] ?? 'NOT SET') . "\n";
        echo '</pre>';
        echo '</div>';
    } catch (Exception $e) {
        echo '<h2>❌ 配置文件 <span class="status fail">加載失敗</span></h2>';
        echo '<pre>' . htmlspecialchars($e->getMessage()) . '</pre>';
        echo '</div>';
        $errors[] = '配置文件加載失敗: ' . $e->getMessage();
    }
    
    // Test 5: Database Connection
    echo '<div class="test">';
    try {
        if (isset($config)) {
            $dsn = "mysql:host={$config['db']['host']};dbname={$config['db']['name']};charset=utf8mb4";
            $pdo = new PDO($dsn, $config['db']['user'], $config['db']['pass'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
            ]);
            
            echo '<h2>✅ 數據庫連接 <span class="status ok">成功</span></h2>';
            
            // Check tables
            $stmt = $pdo->query("SHOW TABLES");
            $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            echo '<pre>';
            echo '數據庫表 (' . count($tables) . ')：' . "\n";
            echo implode(', ', $tables);
            echo '</pre>';
            echo '</div>';
        } else {
            throw new Exception('配置未加載');
        }
    } catch (PDOException $e) {
        echo '<h2>❌ 數據庫連接 <span class="status fail">失敗</span></h2>';
        echo '<pre>' . htmlspecialchars($e->getMessage()) . '</pre>';
        echo '</div>';
        $errors[] = '數據庫連接失敗: ' . $e->getMessage();
    }
    
    // Test 6: Autoload
    echo '<div class="test">';
    try {
        spl_autoload_register(function ($class) use ($root) {
            $prefix = 'App\\';
            if (strpos($class, $prefix) !== 0) return;
            $relative = substr($class, strlen($prefix));
            $file = $root . '/app/' . str_replace('\\', '/', $relative) . '.php';
            if (file_exists($file)) require $file;
        });
        
        echo '<h2>✅ Autoload <span class="status ok">已註冊</span></h2>';
        echo '<pre>測試加載類...</pre>';
        
        // Try to load some classes
        $testClasses = [
            'App\\Core\\Database',
            'App\\Models\\Post',
            'App\\Models\\User',
        ];
        
        foreach ($testClasses as $class) {
            if (class_exists($class)) {
                echo '<pre>✓ ' . $class . '</pre>';
            } else {
                echo '<pre>✗ ' . $class . ' (找不到)</pre>';
                $errors[] = "無法加載類: $class";
            }
        }
        echo '</div>';
    } catch (Exception $e) {
        echo '<h2>❌ Autoload <span class="status fail">失敗</span></h2>';
        echo '<pre>' . htmlspecialchars($e->getMessage()) . '</pre>';
        echo '</div>';
        $errors[] = 'Autoload 失敗: ' . $e->getMessage();
    }
    
    // Test 7: Directory Permissions
    echo '<div class="test">';
    $checkDirs = [
        $root . '/public/uploads' => '上傳目錄',
        $root . '/logs' => '日誌目錄',
    ];
    
    $permissionIssues = [];
    foreach ($checkDirs as $dir => $name) {
        if (!file_exists($dir)) {
            $permissionIssues[] = "$name 不存在: $dir";
        } elseif (!is_writable($dir)) {
            $permissionIssues[] = "$name 不可寫: $dir";
        }
    }
    
    if (empty($permissionIssues)) {
        echo '<h2>✅ 目錄權限 <span class="status ok">OK</span></h2>';
        echo '<pre>';
        foreach ($checkDirs as $dir => $name) {
            echo "✓ $name: " . substr(sprintf('%o', fileperms($dir)), -4) . "\n";
        }
        echo '</pre>';
        echo '</div>';
    } else {
        echo '<h2>⚠️ 目錄權限 <span class="status warn">警告</span></h2>';
        echo '<pre>' . implode("\n", $permissionIssues) . '</pre>';
        echo '</div>';
    }
    
    // Test 8: Try to actually fetch posts
    echo '<div class="test">';
    try {
        if (isset($config) && isset($pdo)) {
            require_once $root . '/app/Core/Database.php';
            require_once $root . '/app/Core/QueryBuilder.php';
            require_once $root . '/app/Core/Logger.php';
            require_once $root . '/app/Models/Post.php';
            
            \App\Core\Database::init($config['db']);
            
            $stmt = $pdo->query("SELECT COUNT(*) as count FROM posts WHERE is_deleted = 0");
            $result = $stmt->fetch();
            
            echo '<h2>✅ Posts 查詢 <span class="status ok">成功</span></h2>';
            echo '<pre>貼文數量: ' . $result['count'] . '</pre>';
            echo '</div>';
        } else {
            throw new Exception('前置條件未滿足');
        }
    } catch (Exception $e) {
        echo '<h2>❌ Posts 查詢 <span class="status fail">失敗</span></h2>';
        echo '<pre>' . htmlspecialchars($e->getMessage()) . "\n\n";
        echo 'Stack trace:' . "\n" . htmlspecialchars($e->getTraceAsString()) . '</pre>';
        echo '</div>';
        $errors[] = 'Posts 查詢失敗: ' . $e->getMessage();
    }
    
    // Summary
    echo '<div class="test ' . (empty($errors) ? 'success' : 'error') . '">';
    if (empty($errors)) {
        echo '<h2>🎉 診斷完成</h2>';
        echo '<p>所有測試通過！系統應該可以正常運行。</p>';
        echo '<p><a href="/api/posts" style="color: #667eea;">嘗試訪問 API →</a></p>';
    } else {
        echo '<h2>⚠️ 發現 ' . count($errors) . ' 個問題</h2>';
        echo '<pre>';
        foreach ($errors as $i => $error) {
            echo ($i + 1) . '. ' . $error . "\n";
        }
        echo '</pre>';
    }
    echo '</div>';
    ?>
    
    <div style="text-align: center; color: #666; margin-top: 40px; font-size: 14px;">
        生成時間: <?= date('Y-m-d H:i:s') ?>
    </div>
</body>
</html>
