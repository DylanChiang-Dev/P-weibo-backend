<?php
require_once __DIR__ . '/_tool_guard.php';
/**
 * 数据迁移脚本：将现有的 post_images 迁移到 media 表
 * 执行方式：直接在浏览器访问此文件
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../app/Core/Database.php';

try {
    $config = config();
    \App\Core\Database::init($config['db']);
    $pdo = \App\Core\Database::getPdo();
    
    echo "<h3>媒体数据迁移工具</h3>";
    
    // 1. 执行表创建
    echo "<h4>步骤1: 创建 media 表</h4>";
    $sqlFile = __DIR__ . '/../migrations/008_create_media_table.sql';
    
    if (file_exists($sqlFile)) {
        $sql = file_get_contents($sqlFile);
        $pdo->exec($sql);
        echo "<div style='color:green'>✅ media 表创建成功</div>";
    } else {
        echo "<div style='color:red'>❌ 找不到迁移文件</div>";
        exit;
    }
    
    // 2. 从 post_images 迁移数据
    echo "<h4>步骤2: 迁移现有图片数据</h4>";
    
    $migrateSQL = "
        INSERT INTO media (user_id, url, filename, filepath, size, created_at)
        SELECT 
            posts.user_id,
            post_images.image_path AS url,
            SUBSTRING_INDEX(post_images.image_path, '/', -1) AS filename,
            CONCAT(?, '/', SUBSTRING_INDEX(post_images.image_path, '/', -1)) AS filepath,
            NULL AS size,
            post_images.created_at
        FROM post_images
        INNER JOIN posts ON post_images.post_id = posts.id
        WHERE NOT EXISTS (
            SELECT 1 FROM media 
            WHERE media.url = post_images.image_path
        )
    ";
    
    $stmt = $pdo->prepare($migrateSQL);
    $stmt->execute([$config['upload']['path']]);
    $migrated = $stmt->rowCount();
    
    echo "<div style='color:green'>✅ 成功迁移 {$migrated} 条图片记录</div>";
    
    // 3. 显示统计
    echo "<h4>步骤3: 统计信息</h4>";
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM media");
    $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    echo "<div>总共有 <strong>{$total}</strong> 条媒体记录</div>";
    
    echo "<hr>";
    echo "<h3>🎉 迁移完成！</h3>";
    echo "<p>现在可以通过 <code>GET /api/media</code> 访问媒体库了。</p>";
    echo "<p><strong>建议</strong>：迁移完成后请删除此脚本文件（public/migrate_media.php）</p>";
    
} catch (Throwable $e) {
    echo "<h1>❌ 错误</h1>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
}
?>
