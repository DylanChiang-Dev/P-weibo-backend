<?php
/**
 * 强制清除 OPcache 并验证代码
 */

echo "=== 清除 OPcache 并验证 ===\n\n";

// 1. 清除 OPcache
if (function_exists('opcache_reset')) {
    $result = opcache_reset();
    echo "OPcache 清除: " . ($result ? "✅ 成功" : "❌ 失败") . "\n";
} else {
    echo "❌ opcache_reset 函数不可用\n";
}

// 2. 检查 OPcache 状态
if (function_exists('opcache_get_status')) {
    $status = opcache_get_status();
    if ($status) {
        echo "OPcache 状态:\n";
        echo "  启用: " . ($status['opcache_enabled'] ? 'YES' : 'NO') . "\n";
        echo "  缓存文件数: " . $status['opcache_statistics']['num_cached_scripts'] . "\n";
        echo "  命中率: " . number_format($status['opcache_statistics']['opcache_hit_rate'], 2) . "%\n";
    }
} else {
    echo "opcache_get_status 函数不可用\n";
}

echo "\n";
echo "=== 下一步 ===\n";
echo "1. 重启 PHP-FPM: /etc/init.d/php-fpm-82 restart\n";
echo "2. 重新测试上传\n";
echo "3. 查看日志: tail -f /www/wwwroot/pyqapi.3331322.xyz/logs/" . date('Y-m-d') . ".log\n";
?>
