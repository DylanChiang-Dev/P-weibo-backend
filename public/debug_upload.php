&lt;?php
/**
 * 调试上传问题
File 专门用于诊断为什么files[]上传后items为空
 */

// 显示所有错误
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json; charset=utf-8');

$debug = [
    'timestamp' => date('Y-m-d H:i:s'),
    'request_method' => $_SERVER['REQUEST_METHOD'] ?? 'unknown',
    'content_type' => $_SERVER['CONTENT_TYPE'] ?? 'unknown',
    'FILES' => $_FILES,
    'POST' => $_POST,
    'GET' => $_GET,
    'php_input_length' => strlen(file_get_contents('php://input')),
];

// 检查是否有files上传
if (!empty($_FILES)) {
    $debug['files_structure'] = [];
    
    foreach ($_FILES as $key => $value) {
        $debug['files_structure'][$key] = [
            'is_array' => is_array($value),
            'structure' => is_array($value) ? array_keys($value) : 'not_array',
            'raw_value' => $value
        ];
        
        // 检查第一个维度是否是name/type/tmp_name等
        if (isset($value['name'])) {
            $debug['single_file_upload'] = true;
            $debug['files_structure'][$key]['detected_type'] = 'single_file';
            
            // 如果name是数组，说明是multiple upload
            if (is_array($value['name'])) {
                $debug['files_structure'][$key]['detected_type'] = 'multiple_files';
                $debug['files_structure'][$key]['count'] = count($value['name']);
            }
        }
    }
}

// 测试upload_path
require_once __DIR__ . '/../config/config.php';
$config = config();
$uploadPath = $config['upload']['path'];

$debug['upload_config'] = [
    'upload_path' => $uploadPath,
    'exists' => is_dir($uploadPath),
    'writable' => is_writable($uploadPath),
    'full_path' => realpath($uploadPath) ?: $uploadPath
];

echo json_encode($debug, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
