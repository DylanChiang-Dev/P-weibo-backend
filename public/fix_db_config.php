<?php
/**
 * 自动修复数据库配置脚本
 * 用于将 .env 中的 DB_NAME 修改为 pyqapi_3331322_x
 */

$envPath = __DIR__ . '/../.env';

if (!file_exists($envPath)) {
    die("❌ 找不到 .env 文件: $envPath");
}

$content = file_get_contents($envPath);
$newContent = preg_replace(
    '/^DB_NAME=.*$/m', 
    'DB_NAME=pyqapi_3331322_x', 
    $content
);

if ($content === $newContent) {
    echo "⚠️ 配置未发生变化（可能已经是正确的值了）。<br>";
} else {
    if (file_put_contents($envPath, $newContent)) {
        echo "✅ 成功修改 .env 文件！<br>";
        echo "旧值被替换为: DB_NAME=pyqapi_3331322_x<br>";
    } else {
        die("❌ 无法写入 .env 文件，请检查权限。<br>");
    }
}

// 尝试重启 PHP-FPM (通常需要root权限，这里可能无效，但可以尝试)
// 或者提示用户重启
echo "<hr>";
echo "<h3>⚠️ 重要：请务必重启 PHP 服务！</h3>";
echo "在宝塔面板中重启 PHP-8.2，或者在终端执行：<br>";
echo "<code>/etc/init.d/php-fpm-82 restart</code>";

// 自毁
// unlink(__FILE__);
?>
