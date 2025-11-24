<?php
/**
 * 终极调试 - 直接查看视频上传的原始数据
 * 访问: https://pyqapi.3331322.xyz/debug_upload_raw.php
 */

// 允许跨域（测试用）
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: *');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// 强制输出，不缓存
header('Content-Type: text/plain; charset=utf-8');
ob_implicit_flush(true);

echo "=== 原始上传数据调试 ===\n";
echo "时间: " . date('Y-m-d H:i:s') . "\n\n";

echo "--- 1. REQUEST METHOD ---\n";
echo $_SERVER['REQUEST_METHOD'] . "\n\n";

echo "--- 2. Content-Type ---\n";
echo ($_SERVER['CONTENT_TYPE'] ?? 'Not set') . "\n\n";

echo "--- 3. $_POST 数据 ---\n";
if (empty($_POST)) {
    echo "❌ $_POST 是空的\n\n";
} else {
    print_r($_POST);
    echo "\n";
}

echo "--- 4. $_FILES 数据（关键！）---\n";
if (empty($_FILES)) {
    echo "❌ $_FILES 是空的！这就是问题所在！\n";
    echo "可能原因:\n";
    echo "  1. 前端没有发送文件\n";
    echo "  2. Content-Type 不是 multipart/form-data\n";
    echo "  3. PHP 配置问题\n\n";
} else {
    echo "✅ $_FILES 有数据:\n";
    print_r($_FILES);
    echo "\n";
    
    // 详细分析 videos 字段
    if (isset($_FILES['videos'])) {
        echo "--- 5. videos 字段详细分析 ---\n";
        $videos = $_FILES['videos'];
        
        if (is_array($videos['name'])) {
            $count = count($videos['name']);
            echo "✅ 检测到 $count 个视频文件\n\n";
            
            for ($i = 0; $i < $count; $i++) {
                echo "视频 #" . ($i + 1) . ":\n";
                echo "  文件名: " . $videos['name'][$i] . "\n";
                echo "  MIME: " . $videos['type'][$i] . "\n";
                echo "  大小: " . number_format($videos['size'][$i]) . " bytes\n";
                echo "  临时路径: " . $videos['tmp_name'][$i] . "\n";
                echo "  错误码: " . $videos['error'][$i] . "\n";
                echo "  临时文件存在: " . (file_exists($videos['tmp_name'][$i]) ? 'YES' : 'NO') . "\n";
                
                // 检测实际 MIME
                if (file_exists($videos['tmp_name'][$i])) {
                    $finfo = new finfo(FILEINFO_MIME_TYPE);
                    $actualMime = $finfo->file($videos['tmp_name'][$i]);
                    echo "  实际 MIME (finfo): $actualMime\n";
                }
                echo "\n";
            }
        } else {
            echo "单个视频:\n";
            echo "  文件名: " . $videos['name'] . "\n";
            echo "  MIME: " . $videos['type'] . "\n";
            echo "  大小: " . $videos['size'] . " bytes\n";
            echo "  临时路径: " . $videos['tmp_name'] . "\n";
            echo "  错误码: " . $videos['error'] . "\n\n";
        }
    } else {
        echo "❌ $_FILES 中没有 'videos' 键\n";
        echo "可用的键: " . implode(', ', array_keys($_FILES)) . "\n\n";
    }
}

echo "--- 6. php://input (原始请求体) 前100字节 ---\n";
$rawInput = file_get_contents('php://input');
echo substr($rawInput, 0, 100) . "...\n\n";

echo "--- 7. PHP 配置 ---\n";
echo "upload_max_filesize: " . ini_get('upload_max_filesize') . "\n";
echo "post_max_size: " . ini_get('post_max_size') . "\n";
echo "file_uploads: " . (ini_get('file_uploads') ? 'ON' : 'OFF') . "\n";
echo "upload_tmp_dir: " . (ini_get('upload_tmp_dir') ?: '/tmp') . "\n\n";

echo "=== 调试完成 ===\n";
?>
