<?php
/**
 * Media API 诊断脚本
 * 检查上传功能所需的所有条件
 */

header('Content-Type: text/html; charset=utf-8');

echo "<h2>Media API 诊断报告</h2>";
echo "<p>生成时间：" . date('Y-m-d H:i:s') . "</p>";
echo "<hr>";

// 1. 检查uploads目录
echo "<h3>1. Uploads目录检查</h3>";
$uploadDir = __DIR__ . '/../storage/uploads';
$altUploadDir = __DIR__ . '/uploads';

if (file_exists($uploadDir)) {
    $dir = $uploadDir;
} elseif (file_exists($altUploadDir)) {
    $dir = $altUploadDir;
} else {
    $dir = null;
}

if ($dir) {
    echo "✅ 目录存在: <code>$dir</code><br>";
    echo "可写: " . (is_writable($dir) ? "✅ 是" : "❌ <strong>否 - 需要修复权限</strong>") . "<br>";
    echo "权限: " . substr(sprintf('%o', fileperms($dir)), -4) . "<br>";
} else {
    echo "❌ <strong>uploads目录不存在！</strong><br>";
    echo "尝试创建...<br>";
    if (@mkdir($uploadDir, 0775, true)) {
        echo "✅ 创建成功<br>";
    } else {
        echo "❌ 创建失败<br>";
    }
}

// 2. 检查数据库连接和media表
echo "<h3>2. 数据库检查</h3>";
try {
    require_once __DIR__ . '/../config/config.php';
    require_once __DIR__ . '/../app/Core/Database.php';
    
    $config = config();
    \App\Core\Database::init($config['db']);
    $pdo = \App\Core\Database::getPdo();
    
    echo "✅ 数据库连接成功<br>";
    echo "数据库名: <code>{$config['db']['name']}</code><br>";
    
    // 检查media表
    $stmt = $pdo->query("SHOW TABLES LIKE 'media'");
    if ($stmt->rowCount() > 0) {
        echo "✅ media表存在<br>";
        
        // 检查表结构
        $stmt = $pdo->query("DESCRIBE media");
        $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $requiredColumns = ['id', 'user_id', 'url', 'filename', 'filepath', 'size', 'mime_type', 'created_at'];
        $missing = array_diff($requiredColumns, $columns);
        
        if (empty($missing)) {
            echo "✅ 表结构正确<br>";
        } else {
            echo "❌ <strong>缺少字段: " . implode(', ', $missing) . "</strong><br>";
        }
        
        // 显示media记录数
        $stmt = $pdo->query("SELECT COUNT(*) FROM media");
        $count = $stmt->fetchColumn();
        echo "媒体记录数: <strong>$count</strong><br>";
    } else {
        echo "❌ <strong>media表不存在！</strong><br>";
        echo "<a href='migrate_media.php'>点击这里执行迁移</a><br>";
    }
} catch (Exception $e) {
    echo "❌ <strong>数据库错误: " . htmlspecialchars($e->getMessage()) . "</strong><br>";
}

// 3. 检查PHP配置
echo "<h3>3. PHP配置</h3>";
echo "upload_max_filesize: <code>" . ini_get('upload_max_filesize') . "</code><br>";
echo "post_max_size: <code>" . ini_get('post_max_size') . "</code><br>";
echo "max_file_uploads: <code>" . ini_get('max_file_uploads') . "</code><br>";
echo "PHP版本: <code>" . PHP_VERSION . "</code><br>";

// 4. 测试文件上传
echo "<h3>4. 测试文件创建</h3>";
$testFile = ($dir ?? sys_get_temp_dir()) . '/test_' . time() . '.txt';
try {
    if (file_put_contents($testFile, 'test')) {
        echo "✅ 文件写入成功: <code>$testFile</code><br>";
        @unlink($testFile);
    } else {
        echo "❌ <strong>文件写入失败</strong><br>";
    }
} catch (Exception $e) {
    echo "❌ <strong>错误: " . htmlspecialchars($e->getMessage()) . "</strong><br>";
}

// 5. 检查代码文件
echo "<h3>5. 代码文件检查</h3>";
$files = [
    __DIR__ . '/../app/Controllers/MediaController.php',
    __DIR__ . '/../app/Models/Media.php',
];

foreach ($files as $file) {
    if (file_exists($file)) {
        echo "✅ " . basename($file) . "<br>";
    } else {
        echo "❌ <strong>" . basename($file) . " 不存在</strong><br>";
    }
}

// 6. 建议
echo "<h3>6. 修复建议</h3>";
echo "<ol>";
echo "<li>如果media表不存在，访问 <a href='migrate_media.php'>migrate_media.php</a> 执行迁移</li>";
echo "<li>如果uploads目录权限不足，执行: <code>chmod -R 775 " . ($dir ?? '/path/to/uploads') . "</code></li>";
echo "<li>如果还是失败，查看PHP错误日志: <code>tail -100 /www/server/php/82/var/log/php-fpm.log</code></li>";
echo "<li>部署最新代码: <code>cd /www/wwwroot/pyqapi.3331322.xyz && git pull && /etc/init.d/php-fpm-82 restart</code></li>";
echo "</ol>";

echo "<hr>";
echo "<h3>7. 快速测试上传</h3>";
echo "<form method='post' enctype='multipart/form-data' action='/api/media'>";
echo "Token: <input type='text' name='token' size='80' placeholder='粘贴您的JWT token'><br><br>";
echo "选择文件: <input type='file' name='files[]' multiple accept='image/*'><br><br>";
echo "<button type='submit'>测试上传</button>";
echo "</form>";
echo "<p><small>注意：这需要有效的认证token</small></p>";
?>
