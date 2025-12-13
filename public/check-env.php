<?php
require_once __DIR__ . '/_tool_guard.php';
// Quick .env checker
$root = dirname(__DIR__);
$envPath = $root . '/.env';

echo "<h2>檢查 .env 文件</h2>";
echo "<pre>";
echo "查找路徑: $envPath\n\n";

if (file_exists($envPath)) {
    echo "✅ .env 文件存在\n";
    echo "大小: " . filesize($envPath) . " bytes\n";
    echo "可讀: " . (is_readable($envPath) ? 'YES' : 'NO') . "\n\n";
    
    echo "--- 文件內容 (前 50 行) ---\n";
    $lines = file($envPath);
    foreach (array_slice($lines, 0, 50) as $i => $line) {
        // 隱藏敏感信息
        if (str_contains($line, 'PASS') || str_contains($line, 'SECRET')) {
            $parts = explode('=', $line, 2);
            echo ($i + 1) . ": " . $parts[0] . "=***隱藏***\n";
        } else {
            echo ($i + 1) . ": " . htmlspecialchars($line);
        }
    }
} else {
    echo "❌ .env 文件不存在！\n\n";
    echo "請檢查以下位置：\n";
    echo "1. $root/.env\n";
    echo "2. 確認安裝程序是否成功創建了 .env 文件\n";
    echo "3. 確認文件權限是否正確\n\n";
    
    // Check if .installed file exists
    if (file_exists($root . '/.installed')) {
        echo "發現 .installed 文件，說明安裝程序曾經運行過\n";
        echo "但 .env 文件丟失了！\n\n";
        echo "解決方案：\n";
        echo "1. 刪除 .installed 文件\n";
        echo "2. 重新訪問 /install.html 安裝\n";
    }
}

echo "\n--- 當前目錄結構 ---\n";
$files = scandir($root);
foreach ($files as $file) {
    if ($file === '.' || $file === '..') continue;
    $path = $root . '/' . $file;
    $type = is_dir($path) ? '[DIR]' : '[FILE]';
    echo "$type $file\n";
}

echo "</pre>";
