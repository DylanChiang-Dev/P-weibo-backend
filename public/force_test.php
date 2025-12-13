<?php
require_once __DIR__ . '/_tool_guard.php';
/**
 * 强制测试 - 直接在 API 端点开头写入日志
 * 这会告诉我们请求是否到达 PHP
 */

// 强制创建日志目录并写入
$logDir = '/www/wwwroot/pyqapi.3331322.xyz/logs';
if (!is_dir($logDir)) {
    mkdir($logDir, 0777, true);
}

$logFile = $logDir . '/FORCE_DEBUG.log';
$timestamp = date('Y-m-d H:i:s');
$message = "$timestamp - POST 请求到达！\n";
$message .= "URI: " . ($_SERVER['REQUEST_URI'] ?? 'unknown') . "\n";
$message .= "Method: " . ($_SERVER['REQUEST_METHOD'] ?? 'unknown') . "\n";
$message .= "Content-Type: " . ($_SERVER['CONTENT_TYPE'] ?? 'none') . "\n";
$message .= "Files count: " . count($_FILES) . "\n";
$message .= "Has videos: " . (isset($_FILES['videos']) ? 'YES' : 'NO') . "\n";
$message .= "---\n";

file_put_contents($logFile, $message, FILE_APPEND);

// 继续正常的 API 处理...
?>
<!DOCTYPE html>
<html>
<head><title>强制日志测试</title></head>
<body>
<h1>✅ 强制日志测试</h1>
<p>日志已写入: <?php echo $logFile; ?></p>
<p>时间: <?php echo $timestamp; ?></p>
<p>请求方法: <?php echo $_SERVER['REQUEST_METHOD'] ?? 'unknown'; ?></p>
<p>$_FILES 数量: <?php echo count($_FILES); ?></p>

<hr>
<h2>查看日志内容：</h2>
<pre><?php
if (file_exists($logFile)) {
    echo htmlspecialchars(file_get_contents($logFile));
} else {
    echo "日志文件不存在！";
}
?></pre>

<hr>
<p><a href="/force_test.php">刷新</a> | <a href="/">返回首页</a></p>
</body>
</html>
