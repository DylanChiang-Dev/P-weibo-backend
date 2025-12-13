<?php
require_once __DIR__ . '/_tool_guard.php';
/**
 * Media API 安装和诊断工具
 * 
 * 功能：
 * 1. 自动创建必要的目录结构
 * 2. 验证和创建 media 数据库表
 * 3. 检查所有依赖条件
 * 4. 提供详细的诊断报告
 * 
 * 使用方式：
 * - 在浏览器访问：https://yourdomain.com/setup_media.php
 * - 或命令行运行：php public/setup_media.php
 */

declare(strict_types=1);

// 设置字符编码
header('Content-Type: text/html; charset=utf-8');

// 判断是否从命令行运行
$isCli = php_sapi_name() === 'cli';

function output(string $message, string $type = 'info'): void {
    global $isCli;
    
    if ($isCli) {
        $prefix = match($type) {
            'success' => '✅ ',
            'error' => '❌ ',
            'warning' => '⚠️  ',
            'title' => "\n## ",
            default => '   '
        };
        echo $prefix . strip_tags($message) . "\n";
    } else {
        $color = match($type) {
            'success' => 'green',
            'error' => 'red',
            'warning' => 'orange',
            'title' => 'blue',
            default => 'black'
        };
        
        if ($type === 'title') {
            echo "<h3 style='color: $color; margin-top: 20px;'>$message</h3>\n";
        } else {
            echo "<div style='color: $color; margin: 5px 0;'>$message</div>\n";
        }
    }
}

if (!$isCli) {
    echo "<!DOCTYPE html><html><head><meta charset='utf-8'><title>Media API 安装诊断</title></head><body>";
    echo "<h1>Media API 安装和诊断工具</h1>";
    echo "<p>生成时间：" . date('Y-m-d H:i:s') . "</p><hr>";
}

$errors = [];
$warnings = [];
$success = [];

try {
    // 引入配置
    $root = dirname(__DIR__);
    require_once $root . '/config/config.php';
    require_once $root . '/app/Core/Database.php';
    
    $config = config();
    
    // ============================================
    // 步骤 1: 检查和创建目录
    // ============================================
    output('步骤 1: 检查和创建目录', 'title');
    
    $uploadPath = $config['upload']['path'];
    $logPath = $config['log']['path'];
    
    // 检查 uploads 目录
    if (!is_dir($uploadPath)) {
        output("uploads 目录不存在: <code>$uploadPath</code>", 'warning');
        if (@mkdir($uploadPath, 0775, true)) {
            @chmod($uploadPath, 0775);
            output("✅ 成功创建 uploads 目录", 'success');
            $success[] = "创建了 uploads 目录";
        } else {
            $error = "无法创建 uploads 目录: $uploadPath";
            output("❌ $error", 'error');
            $errors[] = $error;
        }
    } else {
        output("✅ uploads 目录已存在: <code>$uploadPath</code>", 'success');
    }
    
    // 检查目录权限
    if (is_dir($uploadPath)) {
        $perms = substr(sprintf('%o', fileperms($uploadPath)), -4);
        output("目录权限: <code>$perms</code>", 'info');
        
        if (!is_writable($uploadPath)) {
            $warning = "uploads 目录不可写，需要修改权限";
            output("⚠️  $warning", 'warning');
            $warnings[] = $warning;
            output("执行命令: <code>chmod 775 $uploadPath</code>", 'info');
        } else {
            output("✅ 目录可写", 'success');
        }
    }
    
    // 检查 logs 目录
    if (!is_dir($logPath)) {
        if (@mkdir($logPath, 0775, true)) {
            @chmod($logPath, 0775);
            output("✅ 成功创建 logs 目录", 'success');
            $success[] = "创建了 logs 目录";
        } else {
            $warning = "无法创建 logs 目录: $logPath";
            output("⚠️  $warning", 'warning');
            $warnings[] = $warning;
        }
    } else {
        output("✅ logs 目录已存在", 'success');
    }
    
    // ============================================
    // 步骤 2: 测试文件写入
    // ============================================
    output('步骤 2: 测试文件写入', 'title');
    
    $testFile = $uploadPath . '/test_' . time() . '.txt';
    $testContent = 'Media API test file - ' . date('Y-m-d H:i:s');
    
    if (@file_put_contents($testFile, $testContent)) {
        output("✅ 文件写入测试成功", 'success');
        @unlink($testFile);
    } else {
        $error = "文件写入测试失败";
        output("❌ $error", 'error');
        $errors[] = $error;
    }
    
    // ============================================
    // 步骤 3: 检查数据库连接和 media 表
    // ============================================
    output('步骤 3: 检查数据库', 'title');
    
    \App\Core\Database::init($config['db']);
    $pdo = \App\Core\Database::getPdo();
    
    output("✅ 数据库连接成功", 'success');
    output("数据库: <code>{$config['db']['name']}</code> @ <code>{$config['db']['host']}</code>", 'info');
    
    // 检查 media 表
    $stmt = $pdo->query("SHOW TABLES LIKE 'media'");
    $tableExists = $stmt->rowCount() > 0;
    
    if (!$tableExists) {
        output("⚠️  media 表不存在，尝试创建...", 'warning');
        
        $migrationFile = $root . '/migrations/008_create_media_table.sql';
        if (!file_exists($migrationFile)) {
            $error = "找不到迁移文件: $migrationFile";
            output("❌ $error", 'error');
            $errors[] = $error;
        } else {
            $sql = file_get_contents($migrationFile);
            try {
                $pdo->exec($sql);
                output("✅ 成功创建 media 表", 'success');
                $success[] = "创建了 media 数据库表";
                $tableExists = true;
            } catch (\PDOException $e) {
                $error = "创建 media 表失败: " . $e->getMessage();
                output("❌ $error", 'error');
                $errors[] = $error;
            }
        }
    } else {
        output("✅ media 表已存在", 'success');
    }
    
    // 检查表结构
    if ($tableExists) {
        $stmt = $pdo->query("DESCRIBE media");
        $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $requiredColumns = ['id', 'user_id', 'url', 'filename', 'filepath', 'size', 'mime_type', 'created_at'];
        $missing = array_diff($requiredColumns, $columns);
        
        if (empty($missing)) {
            output("✅ media 表结构正确", 'success');
            output("字段: <code>" . implode(', ', $columns) . "</code>", 'info');
        } else {
            $error = "media 表缺少字段: " . implode(', ', $missing);
            output("❌ $error", 'error');
            $errors[] = $error;
        }
        
        // 统计记录数
        $stmt = $pdo->query("SELECT COUNT(*) FROM media");
        $count = $stmt->fetchColumn();
        output("当前媒体记录数: <strong>$count</strong>", 'info');
    }
    
    // ============================================
    // 步骤 4: 检查 PHP 配置
    // ============================================
    output('步骤 4: 检查 PHP 配置', 'title');
    
    $phpVersion = PHP_VERSION;
    $uploadMaxFilesize = ini_get('upload_max_filesize');
    $postMaxSize = ini_get('post_max_size');
    $maxFileUploads = ini_get('max_file_uploads');
    $memoryLimit = ini_get('memory_limit');
    
    output("PHP 版本: <code>$phpVersion</code>", 'info');
    output("upload_max_filesize: <code>$uploadMaxFilesize</code>", 'info');
    output("post_max_size: <code>$postMaxSize</code>", 'info');
    output("max_file_uploads: <code>$maxFileUploads</code>", 'info');
    output("memory_limit: <code>$memoryLimit</code>", 'info');
    
    // 检查是否满足最低要求
    if (version_compare($phpVersion, '8.1.0', '<')) {
        $warning = "PHP 版本过低，建议使用 PHP 8.1+";
        output("⚠️  $warning", 'warning');
        $warnings[] = $warning;
    } else {
        output("✅ PHP 版本满足要求", 'success');
    }
    
    // ============================================
    // 步骤 5: 检查必需的 PHP 扩展
    // ============================================
    output('步骤 5: 检查 PHP 扩展', 'title');
    
    $requiredExtensions = ['pdo', 'pdo_mysql', 'fileinfo', 'json'];
    foreach ($requiredExtensions as $ext) {
        if (extension_loaded($ext)) {
            output("✅ $ext", 'success');
        } else {
            $error = "缺少 PHP 扩展: $ext";
            output("❌ $error", 'error');
            $errors[] = $error;
        }
    }
    
    // ============================================
    // 步骤 6: 检查代码文件
    // ============================================
    output('步骤 6: 检查代码文件', 'title');
    
    $codeFiles = [
        'app/Controllers/MediaController.php' => '媒体控制器',
        'app/Models/Media.php' => '媒体模型',
        'app/Core/Database.php' => '数据库核心',
        'app/Core/Logger.php' => '日志核心',
        'config/config.php' => '配置文件',
    ];
    
    foreach ($codeFiles as $file => $desc) {
        $fullPath = $root . '/' . $file;
        if (file_exists($fullPath)) {
            output("✅ $desc: <code>$file</code>", 'success');
        } else {
            $error = "缺少文件: $file ($desc)";
            output("❌ $error", 'error');
            $errors[] = $error;
        }
    }
    
    // ============================================
    // 总结报告
    // ============================================
    output('诊断总结', 'title');
    
    if (empty($errors)) {
        output('🎉 所有检查通过！Media API 已准备就绪。', 'success');
    } else {
        output('发现 ' . count($errors) . ' 个错误需要修复：', 'error');
        foreach ($errors as $error) {
            output("  • $error", 'error');
        }
    }
    
    if (!empty($warnings)) {
        output('发现 ' . count($warnings) . ' 个警告：', 'warning');
        foreach ($warnings as $warning) {
            output("  • $warning", 'warning');
        }
    }
    
    if (!empty($success)) {
        output('已完成以下修复：', 'success');
        foreach ($success as $item) {
            output("  • $item", 'success');
        }
    }
    
    // ============================================
    // 后续步骤建议
    // ============================================
    if (!empty($errors) || !empty($warnings)) {
        output('建议的修复步骤', 'title');
        
        if (!empty($errors)) {
            output('1. 修复上述错误', 'info');
        }
        
        if (in_array("uploads 目录不可写，需要修改权限", $warnings)) {
            output("2. 修改目录权限：<code>chmod -R 775 $uploadPath</code>", 'info');
            output("   或：<code>chown -R www:www $uploadPath</code>", 'info');
        }
        
        output('3. 重新运行此诊断脚本验证', 'info');
        output('4. 重启 PHP-FPM：<code>/etc/init.d/php-fpm-82 restart</code>', 'info');
    }
    
    // ============================================
    // 测试建议
    // ============================================
    if (empty($errors)) {
        output('快速测试', 'title');
        
        $testCurl = <<<'BASH'
# 1. 登录获取 token
TOKEN=$(curl -s -X POST https://pyqapi.3331322.xyz/api/login \
  -H "Content-Type: application/json" \
  -d '{"email":"YOUR_EMAIL","password":"YOUR_PASSWORD"}' \
  | jq -r '.data.access_token')

# 2. 创建测试文件
echo "test" > /tmp/test.txt

# 3. 测试上传
curl -v -X POST \
  -H "Authorization: Bearer $TOKEN" \
  -F "files[]=@/tmp/test.txt" \
  https://pyqapi.3331322.xyz/api/media
BASH;
        
        output("使用以下命令测试上传：", 'info');
        if ($isCli) {
            echo "\n" . $testCurl . "\n";
        } else {
            echo "<pre style='background: #f5f5f5; padding: 10px; border-radius: 5px;'>" . 
                 htmlspecialchars($testCurl) . "</pre>";
        }
    }
    
} catch (\Throwable $e) {
    output('发生致命错误', 'title');
    output('错误信息: ' . $e->getMessage(), 'error');
    output('文件: ' . $e->getFile() . ':' . $e->getLine(), 'error');
    
    if (!$isCli) {
        echo "<details><summary>详细堆栈跟踪</summary><pre>";
        echo htmlspecialchars($e->getTraceAsString());
        echo "</pre></details>";
    } else {
        echo "\n堆栈跟踪:\n" . $e->getTraceAsString() . "\n";
    }
}

if (!$isCli) {
    echo "<hr><p><small>提示：为了安全，生产环境部署后应删除此脚本。</small></p>";
    echo "</body></html>";
}
?>
