<?php
/**
 * 媒体库和每日打卡数据库安装工具
 * 
 * 功能：
 * 1. 执行每日打卡功能数据库迁移 (010)
 * 2. 执行媒体库功能数据库迁移 (011)
 * 
 * 使用方式：
 * - 在浏览器访问：https://yourdomain.com/setup_media_library.php
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
    echo "<!DOCTYPE html><html><head><meta charset='utf-8'><title>媒体库数据库安装</title></head><body>";
    echo "<h1>媒体库和每日打卡数据库安装工具</h1>";
    echo "<p>生成时间：" . date('Y-m-d H:i:s') . "</p><hr>";
}

$errors = [];
$success = [];

try {
    // 引入配置
    $root = dirname(__DIR__);
    require_once $root . '/config/config.php';
    require_once $root . '/app/Core/Database.php';
    
    $config = config();
    
    // ============================================
    // 步骤 1: 连接数据库
    // ============================================
    output('步骤 1: 连接数据库', 'title');
    
    \App\Core\Database::init($config['db']);
    $pdo = \App\Core\Database::getPdo();
    
    output("✅ 数据库连接成功", 'success');
    output("数据库: <code>{$config['db']['name']}</code> @ <code>{$config['db']['host']}</code>", 'info');
    
    // ============================================
    // 步骤 2: 执行迁移 010 (每日打卡)
    // ============================================
    output('步骤 2: 执行迁移 010 (每日打卡)', 'title');
    
    $migrationFile010 = $root . '/migrations/010_create_daily_activities_table.sql';
    if (!file_exists($migrationFile010)) {
        $error = "找不到迁移文件: $migrationFile010";
        output("❌ $error", 'error');
        $errors[] = $error;
    } else {
        // 检查表是否存在
        $stmt = $pdo->query("SHOW TABLES LIKE 'daily_activities'");
        if ($stmt->rowCount() > 0) {
            output("⚠️  daily_activities 表已存在，跳过创建", 'warning');
        } else {
            $sql = file_get_contents($migrationFile010);
            try {
                $pdo->exec($sql);
                output("✅ 成功创建 daily_activities 表", 'success');
                $success[] = "创建了 daily_activities 表";
            } catch (\PDOException $e) {
                $error = "创建 daily_activities 表失败: " . $e->getMessage();
                output("❌ $error", 'error');
                $errors[] = $error;
            }
        }
    }
    
    // ============================================
    // 步骤 3: 执行迁移 011 (媒体库)
    // ============================================
    output('步骤 3: 执行迁移 011 (媒体库)', 'title');
    
    $migrationFile011 = $root . '/migrations/011_create_media_library_tables.sql';
    if (!file_exists($migrationFile011)) {
        $error = "找不到迁移文件: $migrationFile011";
        output("❌ $error", 'error');
        $errors[] = $error;
    } else {
        // 检查表是否存在 (检查其中一个表即可)
        $stmt = $pdo->query("SHOW TABLES LIKE 'user_movies'");
        if ($stmt->rowCount() > 0) {
            output("⚠️  媒体库表 (user_movies 等) 已存在，跳过创建", 'warning');
        } else {
            $sql = file_get_contents($migrationFile011);
            try {
                // 分割多条SQL语句执行
                // 注意：这里简单地将整个文件内容作为一条执行，如果文件包含多条语句且驱动不支持，可能需要分割
                // 但通常PDO exec支持多条语句（取决于配置），或者我们可以简单分割
                $pdo->exec($sql);
                output("✅ 成功执行媒体库迁移 (user_movies, user_tv_shows, user_books, user_games)", 'success');
                $success[] = "创建了媒体库相关表";
            } catch (\PDOException $e) {
                $error = "执行媒体库迁移失败: " . $e->getMessage();
                output("❌ $error", 'error');
                $errors[] = $error;
            }
        }
    }
    
    // ============================================
    // 步骤 4: 执行迁移 012 (修复评分精度)
    // ============================================
    output('步骤 4: 执行迁移 012 (修复评分精度)', 'title');
    
    $migrationFile012 = $root . '/migrations/012_fix_rating_decimal.sql';
    if (!file_exists($migrationFile012)) {
        $error = "找不到迁移文件: $migrationFile012";
        output("❌ $error", 'error');
        $errors[] = $error;
    } else {
        $sql = file_get_contents($migrationFile012);
        try {
            // 检查列类型是否已经是 DECIMAL(3,1) - 这里简单直接执行ALTER，MySQL通常允许重复ALTER
            // 或者我们可以捕获错误
            $pdo->exec($sql);
            output("✅ 成功修复评分字段精度 (DECIMAL 3,1)", 'success');
            $success[] = "修复了评分字段精度";
        } catch (\PDOException $e) {
            // 如果已经修改过，可能不会报错，或者报无变化
            output("ℹ️ 执行修复迁移: " . $e->getMessage(), 'info');
        }
    }
    
    // ============================================
    // 步骤 5: 执行迁移 013 (扩展媒体库：播客/纪录片/动画)
    // ============================================
    output('步骤 5: 执行迁移 013 (扩展媒体库)', 'title');
    
    $migrationFile013 = $root . '/migrations/013_create_extended_media_tables.sql';
    if (!file_exists($migrationFile013)) {
        $error = "找不到迁移文件: $migrationFile013";
        output("❌ $error", 'error');
        $errors[] = $error;
    } else {
        // 检查表是否存在 (检查其中一个表即可)
        $stmt = $pdo->query("SHOW TABLES LIKE 'user_podcasts'");
        if ($stmt->rowCount() > 0) {
            output("⚠️  扩展媒体库表 (user_podcasts 等) 已存在，跳过创建", 'warning');
        } else {
            $sql = file_get_contents($migrationFile013);
            try {
                $pdo->exec($sql);
                output("✅ 成功创建扩展媒体库表 (user_podcasts, user_documentaries, user_anime)", 'success');
                $success[] = "创建了扩展媒体库相关表";
            } catch (\PDOException $e) {
                $error = "执行扩展媒体库迁移失败: " . $e->getMessage();
                output("❌ $error", 'error');
                $errors[] = $error;
            }
        }
    }
    
    // ============================================
    // 步骤 6: 执行迁移 014 (用户设置)
    // ============================================
    output('步骤 6: 执行迁移 014 (用户设置)', 'title');
    
    $migrationFile014 = $root . '/migrations/014_create_user_settings_table.sql';
    if (!file_exists($migrationFile014)) {
        $error = "找不到迁移文件: $migrationFile014";
        output("❌ $error", 'error');
        $errors[] = $error;
    } else {
        // 检查表是否存在
        $stmt = $pdo->query("SHOW TABLES LIKE 'user_settings'");
        if ($stmt->rowCount() > 0) {
            output("⚠️  user_settings 表已存在，跳过创建", 'warning');
        } else {
            $sql = file_get_contents($migrationFile014);
            try {
                $pdo->exec($sql);
                output("✅ 成功创建 user_settings 表", 'success');
                $success[] = "创建了 user_settings 表";
            } catch (\PDOException $e) {
                $error = "创建 user_settings 表失败: " . $e->getMessage();
                output("❌ $error", 'error');
                $errors[] = $error;
            }
        }
    }
    
    // ============================================
    // 总结
    // ============================================
    output('安装总结', 'title');
    
    if (empty($errors)) {
        output('🎉 所有操作已完成！', 'success');
    } else {
        output('发现错误：', 'error');
        foreach ($errors as $error) {
            output("  • $error", 'error');
        }
    }
    
} catch (\Throwable $e) {
    output('发生致命错误', 'title');
    output('错误信息: ' . $e->getMessage(), 'error');
    if (!$isCli) {
        echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
    }
}

if (!$isCli) {
    echo "<hr><p><small>提示：为了安全，生产环境部署后建议删除此脚本。</small></p>";
    echo "</body></html>";
}
?>
