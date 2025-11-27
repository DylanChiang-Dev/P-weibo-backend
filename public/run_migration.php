<?php
/**
 * 自动执行数据库迁移脚本
 * 读取 migrations/007_blog_complete_migration.sql 并执行
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../app/Core/Database.php';

try {
    // 1. 初始化数据库连接
    $config = config();
    \App\Core\Database::init($config['db']);
    $pdo = \App\Core\Database::getPdo();
    
    echo "<h3>当前数据库: " . $config['db']['name'] . "</h3>";
    
    // 2. 读取迁移文件
    $sqlFile = __DIR__ . '/../migrations/007_blog_complete_migration.sql';
    if (!file_exists($sqlFile)) {
        throw new Exception("找不到迁移文件: $sqlFile");
    }
    
    $sql = file_get_contents($sqlFile);
    
    // 3. 执行迁移
    echo "正在执行迁移...<br>";
    
    // 分割SQL语句（简单分割，仅适用于当前特定的SQL文件）
    // 注意：这里假设SQL文件中没有复杂的存储过程或触发器包含分号
    $statements = array_filter(array_map('trim', explode(';', $sql)));
    
    foreach ($statements as $stmt) {
        if (empty($stmt)) continue;
        try {
            $pdo->exec($stmt);
            echo "<div style='color:green'>✅ 执行成功: " . substr($stmt, 0, 50) . "...</div>";
        } catch (PDOException $e) {
            // 忽略 "Table already exists" 错误
            if (strpos($e->getMessage(), 'already exists') !== false) {
                echo "<div style='color:orange'>⚠️ 表已存在 (跳过): " . substr($stmt, 0, 50) . "...</div>";
            } else {
                echo "<div style='color:red'>❌ 执行失败: " . $e->getMessage() . "</div>";
                echo "<pre>$stmt</pre>";
            }
        }
    }
    
    echo "<h2>🎉 迁移完成！</h2>";
    echo "<p>现在请再次访问 API 测试。</p>";
    
} catch (Throwable $e) {
    echo "<h1>❌ 致命错误</h1>";
    echo "<p>" . $e->getMessage() . "</p>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}
