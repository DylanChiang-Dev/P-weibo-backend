<?php
// 極簡診斷 - 不依賴任何其他文件
header('Content-Type: text/plain; charset=utf-8');

echo "=== 診斷信息 ===\n\n";
echo "當前時間: " . date('Y-m-d H:i:s') . "\n";
echo "PHP 版本: " . PHP_VERSION . "\n";
echo "當前文件: " . __FILE__ . "\n";
echo "文檔根目錄: " . ($_SERVER['DOCUMENT_ROOT'] ?? '未設置') . "\n\n";

echo "=== 檢查文件 ===\n";
$files = [
    'setup_media_library.php',
    'run_migration.php',
    '_tool_guard.php',
    'index.php'
];

foreach ($files as $f) {
    $path = __DIR__ . '/' . $f;
    echo "$f: " . (file_exists($path) ? "✅ 存在" : "❌ 不存在") . "\n";
}

echo "\n=== public 目錄內容 ===\n";
$phpFiles = glob(__DIR__ . '/*.php');
foreach ($phpFiles as $f) {
    echo "  " . basename($f) . "\n";
}
