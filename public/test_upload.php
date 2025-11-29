<?php
/**
 * 简单的媒体上传测试
 * 不需要认证，仅用于测试上传功能是否正常
 */

header('Content-Type: text/html; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['test_file'])) {
    require_once __DIR__ . '/../config/config.php';
    require_once __DIR__ . '/../app/Core/Database.php';
    
    try {
        $config = config();
        \App\Core\Database::init($config['db']);
        
        $file = $_FILES['test_file'];
        $uploadPath = $config['upload']['path'];
        $appUrl = rtrim($config['app_url'], '/');
        
        // 检查上传错误
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('Upload error code: ' . $file['error']);
        }
        
        // 验证MIME类型
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($file['tmp_name']);
        
        $allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (!in_array($mimeType, $allowedMimes)) {
            throw new Exception('Invalid file type: ' . $mimeType);
        }
        
        // 生成文件名
        $extension = match($mimeType) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            default => 'jpg'
        };
        
        $uniqueName = 'test_' . bin2hex(random_bytes(8)) . '.' . $extension;
        $filepath = $uploadPath . '/' . $uniqueName;
        $url = $appUrl . '/uploads/' . $uniqueName;
        
        // 移动文件
        if (!move_uploaded_file($file['tmp_name'], $filepath)) {
            throw new Exception('Failed to move uploaded file');
        }
        
        @chmod($filepath, 0644);
        
        // 插入数据库 (user_id = 1 for test)
        $pdo = \App\Core\Database::getPdo();
        $stmt = $pdo->prepare("
            INSERT INTO media (user_id, url, filename, filepath, size, mime_type, created_at)
            VALUES (1, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$url, $file['name'], $filepath, $file['size'], $mimeType]);
        $mediaId = $pdo->lastInsertId();
        
        echo "<div style='padding:20px; background:#d4edda; border:1px solid #c3e6cb; color:#155724; margin:20px 0;'>";
        echo "<h3>✅ 上传成功！</h3>";
        echo "<p><strong>ID:</strong> $mediaId</p>";
        echo "<p><strong>URL:</strong> <a href='$url' target='_blank'>$url</a></p>";
        echo "<p><strong>文件名:</strong> {$file['name']}</p>";
        echo "<p><strong>大小:</strong> " . number_format($file['size']) . " bytes</p>";
        echo "<p><strong>MIME:</strong> $mimeType</p>";
        echo "<p><img src='$url' style='max-width:300px; margin-top:10px;' alt='uploaded'></p>";
        echo "</div>";
        
    } catch (Exception $e) {
        echo "<div style='padding:20px; background:#f8d7da; border:1px solid #f5c6cb; color:#721c24; margin:20px 0;'>";
        echo "<h3>❌ 上传失败</h3>";
        echo "<p><strong>错误:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
        echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
        echo "</div>";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset='utf-8'>
    <title>媒体上传测试</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 50px auto; padding: 20px; }
        h2 { color: #333; }
        .form-group { margin: 20px 0; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        input[type="file"] { padding: 10px; border: 2px dashed #ccc; width: 100%; }
        button { background: #007bff; color: white; padding: 12px 30px; border: none; cursor: pointer; font-size: 16px; }
        button:hover { background: #0056b3; }
    </style>
</head>
<body>
    <h2>📤 媒体上传测试工具</h2>
    <p>这个工具可以直接测试上传功能，无需API认证。</p>
    
    <form method="post" enctype="multipart/form-data">
        <div class="form-group">
            <label>选择图片文件：</label>
            <input type="file" name="test_file" accept="image/*" required>
        </div>
        <button type="submit">🚀 测试上传</button>
    </form>
    
    <hr style="margin: 30px 0;">
    
    <h3>查看已上传的媒体</h3>
    <?php
    try {
        require_once __DIR__ . '/../config/config.php';
        require_once __DIR__ . '/../app/Core/Database.php';
        
        $config = config();
        \App\Core\Database::init($config['db']);
        $pdo = \App\Core\Database::getPdo();
        
        $stmt = $pdo->query("SELECT * FROM media ORDER BY created_at DESC LIMIT 10");
        $media = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($media)) {
            echo "<p>暂无媒体记录</p>";
        } else {
            echo "<table border='1' cellpadding='10' style='width:100%; border-collapse:collapse;'>";
            echo "<tr><th>ID</th><th>预览</th><th>文件名</th><th>大小</th><th>时间</th></tr>";
            foreach ($media as $m) {
                echo "<tr>";
                echo "<td>{$m['id']}</td>";
                echo "<td><img src='{$m['url']}' style='max-width:100px; max-height:100px;'></td>";
                echo "<td>{$m['filename']}</td>";
                echo "<td>" . number_format($m['size']) . "</td>";
                echo "<td>{$m['created_at']}</td>";
                echo "</tr>";
            }
            echo "</table>";
        }
    } catch (Exception $e) {
        echo "<p style='color:red;'>数据库错误: " . htmlspecialchars($e->getMessage()) . "</p>";
    }
    ?>
</body>
</html>
