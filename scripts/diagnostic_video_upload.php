<?php
// 诊断视频上传问题的脚本

require_once __DIR__ . '/../config/config.php';
$config = config();

echo "=== Video Upload Diagnostic ===\n\n";

// 1. Check upload path
echo "1. Upload Path Check:\n";
echo "   Path: {$config['upload']['path']}\n";
echo "   Exists: " . (is_dir($config['upload']['path']) ? 'YES' : 'NO') . "\n";
echo "   Writable: " . (is_writable($config['upload']['path']) ? 'YES' : 'NO') . "\n";
if (is_dir($config['upload']['path'])) {
    echo "   Permissions: " . substr(sprintf('%o', fileperms($config['upload']['path'])), -4) . "\n";
}
echo "\n";

// 2. Check log path
echo "2. Log Path Check:\n";
echo "   Path: {$config['log']['path']}\n";
echo "   Exists: " . (is_dir($config['log']['path']) ? 'YES' : 'NO') . "\n";
echo "   Writable: " . (is_writable($config['log']['path']) ? 'YES' : 'NO') . "\n\n";

// 3. Check PHP upload settings
echo "3. PHP Upload Settings:\n";
echo "   upload_max_filesize: " . ini_get('upload_max_filesize') . "\n";
echo "   post_max_size: " . ini_get('post_max_size') . "\n";
echo "   max_execution_time: " . ini_get('max_execution_time') . "\n";
echo "   memory_limit: " . ini_get('memory_limit') . "\n";
echo "   file_uploads: " . (ini_get('file_uploads') ? 'ON' : 'OFF') . "\n\n";

// 4. Check database connection
echo "4. Database Connection:\n";
try {
    require_once __DIR__ . '/../app/Core/Database.php';
    App\Core\Database::init($config['db']);
    $pdo = App\Core\Database::connection();
    echo "   Connection: OK\n";
    
    // Check post_videos table
    $stmt = $pdo->query("SHOW TABLES LIKE 'post_videos'");
    echo "   post_videos table: " . ($stmt->rowCount() > 0 ? 'EXISTS' : 'NOT FOUND') . "\n";
    
    if ($stmt->rowCount() > 0) {
        $stmt = $pdo->query("DESCRIBE post_videos");
        echo "   Columns:\n";
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            echo "      - {$row['Field']} ({$row['Type']})\n";
        }
        
        // Check if there are any videos
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM post_videos");
        $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        echo "   Total videos in DB: $count\n";
    }
} catch (Exception $e) {
    echo "   Error: " . $e->getMessage() . "\n";
}
echo "\n";

// 5. Check recent video upload logs
echo "5. Recent Video Upload Logs:\n";
$logFile = $config['log']['path'] . '/app.log';
if (file_exists($logFile)) {
    echo "   Log file exists\n";
    exec("tail -n 100 " . escapeshellarg($logFile) . " | grep -i video", $output);
    if (!empty($output)) {
        echo "   Last 10 video-related logs:\n";
        foreach (array_slice($output, -10) as $line) {
            echo "      " . $line . "\n";
        }
    } else {
        echo "   No video-related logs found in last 100 lines\n";
    }
} else {
    echo "   Log file not found: $logFile\n";
}
echo "\n";

// 6. Test file write
echo "6. Test File Write:\n";
$testFile = $config['upload']['path'] . '/test_' . time() . '.txt';
try {
    $result = file_put_contents($testFile, 'test');
    if ($result !== false) {
        echo "   Write test: SUCCESS\n";
        echo "   Test file: $testFile\n";
        unlink($testFile);
        echo "   Cleanup: OK\n";
    } else {
        echo "   Write test: FAILED\n";
    }
} catch (Exception $e) {
    echo "   Write test ERROR: " . $e->getMessage() . "\n";
}

echo "\n=== End of Diagnostic ===\n";
?>
