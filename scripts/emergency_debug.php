<?php
/**
 * 紧急调试脚本 - 直接测试视频上传逻辑
 * 绕过所有缓存和中间层
 */

// 直接写入调试日志
function debug_log($message) {
    $logFile = '/www/wwwroot/pyqapi.3331322.xyz/logs/emergency_debug.log';
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND);
    echo "[$timestamp] $message\n";
}

debug_log("=== 紧急调试开始 ===");

// 1. 检查代码是否更新
debug_log("检查 PostService.php 修改时间...");
$postServiceFile = '/www/wwwroot/pyqapi.3331322.xyz/app/Services/PostService.php';
$modTime = filemtime($postServiceFile);
debug_log("PostService.php 最后修改: " . date('Y-m-d H:i:s', $modTime));

// 2. 检查代码内容
debug_log("检查是否包含新增的日志代码...");
$content = file_get_contents($postServiceFile);
$hasVideoUploadStart = strpos($content, 'video_upload_start') !== false;
$hasVideoProcessing = strpos($content, 'video_processing') !== false;
$hasVideoError = strpos($content, 'video_process_failed') !== false;

debug_log("包含 'video_upload_start': " . ($hasVideoUploadStart ? 'YES' : 'NO'));
debug_log("包含 'video_processing': " . ($hasVideoProcessing ? 'YES' : 'NO'));
debug_log("包含 'video_process_failed': " . ($hasVideoError ? 'YES' : 'NO'));

if (!$hasVideoUploadStart || !$hasVideoProcessing || !$hasVideoError) {
    debug_log("❌ 警告：代码没有更新！需要重新同步代码！");
} else {
    debug_log("✅ 代码已更新");
}

// 3. 检查 MediaService.php
debug_log("\n检查 MediaService.php...");
$mediaServiceFile = '/www/wwwroot/pyqapi.3331322.xyz/app/Services/MediaService.php';
$modTime2 = filemtime($mediaServiceFile);
debug_log("MediaService.php 最后修改: " . date('Y-m-d H:i:s', $modTime2));

$content2 = file_get_contents($mediaServiceFile);
$hasMediaLog = strpos($content2, 'media_service_process_start') !== false;
$hasMimeLog = strpos($content2, 'file_mime_detected') !== false;
$hasMoveLog = strpos($content2, 'moving_uploaded_file') !== false;

debug_log("包含 'media_service_process_start': " . ($hasMediaLog ? 'YES' : 'NO'));
debug_log("包含 'file_mime_detected': " . ($hasMimeLog ? 'YES' : 'NO'));
debug_log("包含 'moving_uploaded_file': " . ($hasMoveLog ? 'YES' : 'NO'));

if (!$hasMediaLog || !hasMimeLog || !$hasMoveLog) {
    debug_log("❌ 警告：MediaService 代码没有更新！");
} else {
    debug_log("✅ MediaService 代码已更新");
}

// 4. 检查 OPcache 状态
debug_log("\n检查 OPcache...");
if (function_exists('opcache_get_status')) {
    $status = opcache_get_status();
    debug_log("OPcache 启用: " . ($status['opcache_enabled'] ? 'YES' : 'NO'));
    if ($status['opcache_enabled']) {
        debug_log("⚠️ OPcache 已启用，可能缓存了旧代码！");
        debug_log("解决方案：执行 opcache_reset() 或重启 PHP-FPM");
        
        // 尝试清除缓存
        if (function_exists('opcache_reset')) {
            opcache_reset();
            debug_log("✅ 已执行 opcache_reset()");
        }
    }
} else {
    debug_log("OPcache 函数不可用");
}

// 5. 检查日志目录
debug_log("\n检查日志配置...");
debug_log("日志目录应该在: /www/wwwroot/pyqapi.3331322.xyz/logs");
debug_log("日志文件名格式: YYYY-MM-DD.log");
debug_log("今天的日志文件: " . date('Y-m-d') . ".log");

$logDir = '/www/wwwroot/pyqapi.3331322.xyz/logs';
if (is_dir($logDir)) {
    $files = scandir($logDir);
    debug_log("日志目录中的文件:");
    foreach ($files as $file) {
        if ($file != '.' && $file != '..') {
            $filePath = $logDir . '/' . $file;
            $size = filesize($filePath);
            debug_log("  - $file (大小: $size bytes)");
        }
    }
} else {
    debug_log("❌ 日志目录不存在！");
}

// 6. 查看最近的请求日志
debug_log("\n查看今天的日志文件...");
$todayLog = $logDir . '/' . date('Y-m-d') . '.log';
if (file_exists($todayLog)) {
    $logContent = file_get_contents($todayLog);
    $lines = explode("\n", $logContent);
    $videoLines = array_filter($lines, function($line) {
        return stripos($line, 'video') !== false || stripos($line, 'post') !== false;
    });
    
    debug_log("找到 " . count($videoLines) . " 条视频相关日志");
    if (count($videoLines) > 0) {
        debug_log("最近 10 条:");
        $recent = array_slice($videoLines, -10);
        foreach ($recent as $line) {
            debug_log("  " . $line);
        }
    }
} else {
    debug_log("❌ 今天的日志文件不存在: $todayLog");
}

// 7. 测试 Logger 类
debug_log("\n测试 Logger 类...");
try {
    require_once '/www/wwwroot/pyqapi.3331322.xyz/config/config.php';
    require_once '/www/wwwroot/pyqapi.3331322.xyz/app/Core/Logger.php';
    
    $config = config();
    App\Core\Logger::init($config['log']['path']);
    App\Core\Logger::info('emergency_debug_test', ['test' => 'value']);
    
    debug_log("✅ Logger 测试成功");
    debug_log("检查文件: " . $todayLog);
} catch (Exception $e) {
    debug_log("❌ Logger 测试失败: " . $e->getMessage());
}

debug_log("\n=== 调试完成 ===");
debug_log("调试日志已保存到: /www/wwwroot/pyqapi.3331322.xyz/logs/emergency_debug.log");
?>
