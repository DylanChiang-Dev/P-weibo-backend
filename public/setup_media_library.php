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
    // 步骤 7: 执行迁移 015 (添加IGDB支持)
    // ============================================
    output('步骤 7: 执行迁移 015 (添加IGDB支持)', 'title');
    
    $migrationFile015 = $root . '/migrations/015_add_igdb_support.sql';
    if (!file_exists($migrationFile015)) {
        $error = "找不到迁移文件: $migrationFile015";
        output("❌ $error", 'error');
        $errors[] = $error;
    } else {
        // 检查列是否已存在
        $stmt = $pdo->query("SHOW COLUMNS FROM user_games LIKE 'igdb_id'");
        if ($stmt->rowCount() > 0) {
            output("⚠️  igdb_id 列已存在，跳过添加", 'warning');
        } else {
            $sql = file_get_contents($migrationFile015);
            try {
                $pdo->exec($sql);
                output("✅ 成功添加 igdb_id 列到 user_games 表", 'success');
                $success[] = "添加了 IGDB 支持";
            } catch (\PDOException $e) {
                $error = "添加 igdb_id 列失败: " . $e->getMessage();
                output("❌ $error", 'error');
                $errors[] = $error;
            }
        }
    }
    
    // ============================================
    // 步骤 8: 执行迁移 016 (游戏元数据字段)
    // ============================================
    output('步骤 8: 执行迁移 016 (游戏元数据字段)', 'title');
    
    $migrationFile016 = $root . '/migrations/016_add_game_metadata_fields.sql';
    if (!file_exists($migrationFile016)) {
        $error = "找不到迁移文件: $migrationFile016";
        output("❌ $error", 'error');
        $errors[] = $error;
    } else {
        // 检查 name 列是否已存在
        $stmt = $pdo->query("SHOW COLUMNS FROM user_games LIKE 'name'");
        if ($stmt->rowCount() > 0) {
            output("⚠️  name 列已存在，跳过添加", 'warning');
        } else {
            $sql = file_get_contents($migrationFile016);
            try {
                $pdo->exec($sql);
                output("✅ 成功添加 name 和 cover_url 列", 'success');
                $success[] = "添加了游戏元数据字段";
            } catch (\PDOException $e) {
                $error = "添加游戏元数据字段失败: " . $e->getMessage();
                output("❌ $error", 'error');
                $errors[] = $error;
            }
        }
    }
    
    // ============================================
    // 步骤 9: 执行迁移 017 (修复RAWG约束支持IGDB)
    // ============================================
    output('步骤 9: 执行迁移 017 (修复RAWG约束)', 'title');
    
    $migrationFile017 = $root . '/migrations/017_fix_rawg_id_constraint.sql';
    if (!file_exists($migrationFile017)) {
        $error = "找不到迁移文件: $migrationFile017";
        output("❌ $error", 'error');
        $errors[] = $error;
    } else {
        // 检查 rawg_id 是否已经是 nullable
        $stmt = $pdo->query("SHOW COLUMNS FROM user_games WHERE Field = 'rawg_id'");
        $column = $stmt->fetch(\PDO::FETCH_ASSOC);
        if ($column && $column['Null'] === 'YES') {
            output("⚠️  rawg_id 已经是 nullable，跳过修改", 'warning');
        } else {
            $sql = file_get_contents($migrationFile017);
            try {
                // 分步执行
                $statements = array_filter(array_map('trim', explode(';', $sql)));
                foreach ($statements as $stmt) {
                    if (!empty($stmt)) {
                        $pdo->exec($stmt);
                    }
                }
                output("✅ 成功修复 rawg_id 约束", 'success');
                $success[] = "修复了 rawg_id 约束";
            } catch (\PDOException $e) {
                $error = "修复约束失败: " . $e->getMessage();
                output("❌ $error", 'error');
                $errors[] = $error;
            }
        }
    }
    
    // ============================================
    // 步骤 10: 执行迁移 018 (播客iTunes支持)
    // ============================================
    output('步骤 10: 执行迁移 018 (播客iTunes支持)', 'title');
    
    $migrationFile018 = $root . '/migrations/018_add_podcast_itunes_support.sql';
    if (!file_exists($migrationFile018)) {
        $error = "找不到迁移文件: $migrationFile018";
        output("❌ $error", 'error');
        $errors[] = $error;
    } else {
        // 检查 itunes_id 列是否已存在
        $stmt = $pdo->query("SHOW COLUMNS FROM user_podcasts LIKE 'itunes_id'");
        if ($stmt->rowCount() > 0) {
            output("⚠️  itunes_id 列已存在，跳过添加", 'warning');
        } else {
            $sql = file_get_contents($migrationFile018);
            try {
                $statements = array_filter(array_map('trim', explode(';', $sql)));
                foreach ($statements as $stmt) {
                    if (!empty($stmt)) {
                        $pdo->exec($stmt);
                    }
                }
                output("✅ 成功添加 itunes_id, artwork_url, release_date 列", 'success');
                $success[] = "添加了播客iTunes支持";
            } catch (\PDOException $e) {
                $error = "添加播客字段失败: " . $e->getMessage();
                output("❌ $error", 'error');
                $errors[] = $error;
            }
        }
    }
    
    // ============================================
    // 步骤 11: 执行迁移 019 (修复播客status枚举)
    // ============================================
    output('步骤 11: 执行迁移 019 (修复播客status)', 'title');
    
    $migrationFile019 = $root . '/migrations/019_fix_podcast_status_enum.sql';
    if (!file_exists($migrationFile019)) {
        output("⚠️  迁移文件不存在，跳过", 'warning');
    } else {
        $sql = file_get_contents($migrationFile019);
        try {
            $pdo->exec($sql);
            output("✅ 成功添加 'listened' 到 status 枚举", 'success');
        } catch (\PDOException $e) {
            // 可能已经修复
            output("ℹ️  " . $e->getMessage(), 'info');
        }
    }
    
    // ============================================
    // 步骤 12: 执行迁移 020 (统一status枚举)
    // ============================================
    output('步骤 12: 执行迁移 020 (统一status枚举)', 'title');
    
    $migrationFile020 = $root . '/migrations/020_unify_status_enums.sql';
    if (!file_exists($migrationFile020)) {
        output("⚠️  迁移文件不存在，跳过", 'warning');
    } else {
        $sql = file_get_contents($migrationFile020);
        try {
            $statements = array_filter(array_map('trim', explode(';', $sql)));
            foreach ($statements as $stmt) {
                if (!empty($stmt) && strpos($stmt, 'ALTER TABLE') !== false) {
                    $pdo->exec($stmt);
                }
            }
            output("✅ 成功统一所有媒体表的status枚举值", 'success');
        } catch (\PDOException $e) {
            $error = "修改枚举失败: " . $e->getMessage();
            output("❌ $error", 'error');
            $errors[] = $error;
        }
    }
    
    // ============================================
    // 步骤 13: 执行迁移 021 (Anime Anilist支持)
    // ============================================
    output('步骤 13: 执行迁移 021 (Anime Anilist支持)', 'title');
    
    $migrationFile021 = $root . '/migrations/021_add_anime_anilist_support.sql';
    if (!file_exists($migrationFile021)) {
        output("⚠️  迁移文件不存在，跳过", 'warning');
    } else {
        $stmt = $pdo->query("SHOW COLUMNS FROM user_anime LIKE 'anilist_id'");
        if ($stmt->rowCount() > 0) {
            output("⚠️  anilist_id 列已存在，跳过", 'warning');
        } else {
            $sql = file_get_contents($migrationFile021);
            try {
                $statements = array_filter(array_map('trim', explode(';', $sql)));
                foreach ($statements as $stmt) {
                    if (!empty($stmt)) {
                        $pdo->exec($stmt);
                    }
                }
                output("✅ 成功添加 Anime Anilist 支持", 'success');
            } catch (\PDOException $e) {
                $error = "添加Anilist支持失败: " . $e->getMessage();
                output("❌ $error", 'error');
                $errors[] = $error;
            }
        }
    }
    
    // ============================================
    // 步骤 14: 执行迁移 022 (媒体元数据P0核心字段)
    // ============================================
    output('步骤 14: 执行迁移 022 (媒体元数据P0核心字段)', 'title');
    
    $migrationFile022 = $root . '/migrations/022_add_media_metadata_p0.sql';
    if (!file_exists($migrationFile022)) {
        output("⚠️  迁移文件不存在，跳过", 'warning');
    } else {
        // 检查 title 列是否已存在于 user_movies
        $stmt = $pdo->query("SHOW COLUMNS FROM user_movies LIKE 'title'");
        if ($stmt->rowCount() > 0) {
            output("⚠️  title 列已存在，跳过P0字段添加", 'warning');
        } else {
            $sql = file_get_contents($migrationFile022);
            try {
                $statements = array_filter(array_map('trim', explode(';', $sql)));
                foreach ($statements as $stmt) {
                    if (!empty($stmt) && stripos($stmt, '--') !== 0) {
                        try {
                            $pdo->exec($stmt);
                        } catch (\PDOException $e) {
                            // 忽略列已存在的错误
                            if (strpos($e->getMessage(), '1060') === false) {
                                throw $e;
                            }
                        }
                    }
                }
                output("✅ 成功添加P0核心字段 (title, cover_image_cdn等)", 'success');
                $success[] = "添加了P0元数据核心字段";
            } catch (\PDOException $e) {
                $error = "添加P0字段失败: " . $e->getMessage();
                output("❌ $error", 'error');
                $errors[] = $error;
            }
        }
    }
    
    // ============================================
    // 步骤 15: 执行迁移 023 (媒体元数据P1扩展字段)
    // ============================================
    output('步骤 15: 执行迁移 023 (媒体元数据P1扩展字段)', 'title');
    
    $migrationFile023 = $root . '/migrations/023_add_media_metadata_p1.sql';
    if (!file_exists($migrationFile023)) {
        output("⚠️  迁移文件不存在，跳过", 'warning');
    } else {
        // 检查 overview 列是否已存在于 user_movies
        $stmt = $pdo->query("SHOW COLUMNS FROM user_movies LIKE 'overview'");
        if ($stmt->rowCount() > 0) {
            output("⚠️  overview 列已存在，跳过P1字段添加", 'warning');
        } else {
            $sql = file_get_contents($migrationFile023);
            try {
                $statements = array_filter(array_map('trim', explode(';', $sql)));
                foreach ($statements as $stmt) {
                    if (!empty($stmt) && stripos($stmt, '--') !== 0) {
                        try {
                            $pdo->exec($stmt);
                        } catch (\PDOException $e) {
                            if (strpos($e->getMessage(), '1060') === false) {
                                throw $e;
                            }
                        }
                    }
                }
                output("✅ 成功添加P1扩展字段 (overview, genres, external_rating等)", 'success');
                $success[] = "添加了P1元数据扩展字段";
            } catch (\PDOException $e) {
                $error = "添加P1字段失败: " . $e->getMessage();
                output("❌ $error", 'error');
                $errors[] = $error;
            }
        }
    }
    
    // ============================================
    // 步骤 16: 执行迁移 024 (媒体特定字段)
    // ============================================
    output('步骤 16: 执行迁移 024 (媒体特定字段)', 'title');
    
    $migrationFile024 = $root . '/migrations/024_add_media_specific_fields.sql';
    if (!file_exists($migrationFile024)) {
        output("⚠️  迁移文件不存在，跳过", 'warning');
    } else {
        // 检查 runtime 列是否已存在于 user_movies
        $stmt = $pdo->query("SHOW COLUMNS FROM user_movies LIKE 'runtime'");
        if ($stmt->rowCount() > 0) {
            output("⚠️  runtime 列已存在，跳过P2字段添加", 'warning');
        } else {
            $sql = file_get_contents($migrationFile024);
            try {
                $statements = array_filter(array_map('trim', explode(';', $sql)));
                foreach ($statements as $stmt) {
                    if (!empty($stmt) && stripos($stmt, '--') !== 0) {
                        try {
                            $pdo->exec($stmt);
                        } catch (\PDOException $e) {
                            if (strpos($e->getMessage(), '1060') === false) {
                                throw $e;
                            }
                        }
                    }
                }
                output("✅ 成功添加P2媒体特定字段 (runtime, director, authors等)", 'success');
                $success[] = "添加了P2媒体特定字段";
            } catch (\PDOException $e) {
                $error = "添加P2字段失败: " . $e->getMessage();
                output("❌ $error", 'error');
                $errors[] = $error;
            }
        }
    }
    
    // ============================================
    // 步骤 16.5: 修复 Books 表缺失的列
    // ============================================
    output('步骤 16.5: 检查并修复 Books 表列', 'title');
    
    $bookColumnsToAdd = [
        // P0 核心字段
        ['name' => 'title', 'sql' => "ALTER TABLE user_books ADD COLUMN title VARCHAR(500) NULL AFTER isbn"],
        ['name' => 'original_title', 'sql' => "ALTER TABLE user_books ADD COLUMN original_title VARCHAR(500) NULL AFTER title"],
        ['name' => 'cover_image_cdn', 'sql' => "ALTER TABLE user_books ADD COLUMN cover_image_cdn TEXT NULL AFTER original_title"],
        ['name' => 'cover_image_local', 'sql' => "ALTER TABLE user_books ADD COLUMN cover_image_local TEXT NULL AFTER cover_image_cdn"],
        // P1 扩展字段
        ['name' => 'overview', 'sql' => "ALTER TABLE user_books ADD COLUMN overview TEXT NULL AFTER cover_image_local"],
        ['name' => 'genres', 'sql' => "ALTER TABLE user_books ADD COLUMN genres JSON NULL AFTER overview"],
        ['name' => 'external_rating', 'sql' => "ALTER TABLE user_books ADD COLUMN external_rating DECIMAL(3,1) NULL AFTER genres"],
        // P2 特定字段
        ['name' => 'authors', 'sql' => "ALTER TABLE user_books ADD COLUMN authors JSON NULL AFTER external_rating"],
        ['name' => 'publisher', 'sql' => "ALTER TABLE user_books ADD COLUMN publisher VARCHAR(255) NULL AFTER authors"],
        ['name' => 'published_date', 'sql' => "ALTER TABLE user_books ADD COLUMN published_date DATE NULL AFTER publisher"],
        ['name' => 'page_count', 'sql' => "ALTER TABLE user_books ADD COLUMN page_count INT NULL AFTER published_date"],
        ['name' => 'isbn_10', 'sql' => "ALTER TABLE user_books ADD COLUMN isbn_10 VARCHAR(13) NULL AFTER page_count"],
        ['name' => 'isbn_13', 'sql' => "ALTER TABLE user_books ADD COLUMN isbn_13 VARCHAR(17) NULL AFTER isbn_10"],
        ['name' => 'language', 'sql' => "ALTER TABLE user_books ADD COLUMN language VARCHAR(10) NULL AFTER isbn_13"],
    ];
    
    foreach ($bookColumnsToAdd as $col) {
        $stmt = $pdo->query("SHOW COLUMNS FROM user_books LIKE '{$col['name']}'");
        if ($stmt->rowCount() === 0) {
            try {
                $pdo->exec($col['sql']);
                output("✅ 添加 user_books.{$col['name']} 列", 'success');
            } catch (\PDOException $e) {
                output("❌ 添加 {$col['name']} 失败: " . $e->getMessage(), 'error');
            }
        } else {
            output("⚠️  user_books.{$col['name']} 已存在", 'warning');
        }
    }
    
    // ============================================
    // 步骤 17: 回填现有数据的元数据 (可选)
    // ============================================
    output('步骤 17: 回填现有数据的元数据', 'title');
    
    // 检查是否有 TMDB API Key
    $tmdbApiKey = getenv('TMDB_API_KEY') ?: ($_ENV['TMDB_API_KEY'] ?? '');
    $limit = (int)($_GET['limit'] ?? 50);
    $skipBackfill = isset($_GET['skip-backfill']);
    
    if ($skipBackfill) {
        output("⏭️  跳过回填（?skip-backfill 参数）", 'warning');
    } elseif (empty($tmdbApiKey)) {
        output("⚠️  TMDB_API_KEY 未设置，跳过 Movies/TV/Docs 回填", 'warning');
        output("设置方法：export TMDB_API_KEY=xxx 或在 .env 中配置", 'info');
    }
    
    if (!$skipBackfill) {
        $backfillStats = ['processed' => 0, 'updated' => 0, 'failed' => 0];
        $movieCount = $tvCount = $docCount = $animeCount = $bookCount = $podcastCount = 0;
        
        // Movies 回填
        if (!empty($tmdbApiKey)) {
            $stmt = $pdo->query("SELECT COUNT(*) FROM user_movies WHERE (title IS NULL OR title = '') AND tmdb_id IS NOT NULL");
            $movieCount = (int)$stmt->fetchColumn();
            
            if ($movieCount > 0) {
                output("📽️  Movies 待回填: $movieCount 条（本次处理 $limit 条）", 'info');
                
                $stmt = $pdo->prepare("SELECT id, tmdb_id FROM user_movies WHERE (title IS NULL OR title = '') AND tmdb_id IS NOT NULL LIMIT :limit");
                $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
                $stmt->execute();
                $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                foreach ($records as $record) {
                    $backfillStats['processed']++;
                    $details = fetchTmdbDetails($record['tmdb_id'], 'movie', $tmdbApiKey);
                    
                    if ($details) {
                        backfillRecord($pdo, 'user_movies', $record['id'], $details);
                        $backfillStats['updated']++;
                        output("✅ Movie #{$record['id']}: {$details['title']}", 'success');
                    } else {
                        $backfillStats['failed']++;
                        output("❌ Movie #{$record['id']}: 获取失败", 'error');
                    }
                    usleep(60000); // 60ms delay
                }
            } else {
                output("✅ Movies 已全部有元数据", 'success');
            }
        }
        
        // TV Shows 回填
        if (!empty($tmdbApiKey)) {
            $stmt = $pdo->query("SELECT COUNT(*) FROM user_tv_shows WHERE (title IS NULL OR title = '') AND tmdb_id IS NOT NULL");
            $tvCount = (int)$stmt->fetchColumn();
            
            if ($tvCount > 0) {
                output("📺  TV Shows 待回填: $tvCount 条", 'info');
                
                $stmt = $pdo->prepare("SELECT id, tmdb_id FROM user_tv_shows WHERE (title IS NULL OR title = '') AND tmdb_id IS NOT NULL LIMIT :limit");
                $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
                $stmt->execute();
                $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                foreach ($records as $record) {
                    $backfillStats['processed']++;
                    $details = fetchTmdbDetails($record['tmdb_id'], 'tv', $tmdbApiKey);
                    
                    if ($details) {
                        backfillRecord($pdo, 'user_tv_shows', $record['id'], $details);
                        $backfillStats['updated']++;
                        output("✅ TV #{$record['id']}: {$details['title']}", 'success');
                    } else {
                        $backfillStats['failed']++;
                    }
                    usleep(60000);
                }
            } else {
                output("✅ TV Shows 已全部有元数据", 'success');
            }
        }
        
        // Documentaries 回填
        if (!empty($tmdbApiKey)) {
            $stmt = $pdo->query("SELECT COUNT(*) FROM user_documentaries WHERE (title IS NULL OR title = '') AND tmdb_id IS NOT NULL");
            $docCount = (int)$stmt->fetchColumn();
            
            if ($docCount > 0) {
                output("🎬  Documentaries 待回填: $docCount 条", 'info');
                
                $stmt = $pdo->prepare("SELECT id, tmdb_id FROM user_documentaries WHERE (title IS NULL OR title = '') AND tmdb_id IS NOT NULL LIMIT :limit");
                $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
                $stmt->execute();
                $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                foreach ($records as $record) {
                    $backfillStats['processed']++;
                    $details = fetchTmdbDetails($record['tmdb_id'], 'movie', $tmdbApiKey);
                    if (!$details) $details = fetchTmdbDetails($record['tmdb_id'], 'tv', $tmdbApiKey);
                    
                    if ($details) {
                        backfillRecord($pdo, 'user_documentaries', $record['id'], $details);
                        $backfillStats['updated']++;
                        output("✅ Doc #{$record['id']}: {$details['title']}", 'success');
                    } else {
                        $backfillStats['failed']++;
                    }
                    usleep(60000);
                }
            } else {
                output("✅ Documentaries 已全部有元数据", 'success');
            }
        }
        
        // Anime 回填 (AniList - 不需要 API Key)
        $stmt = $pdo->query("SELECT COUNT(*) FROM user_anime WHERE (title IS NULL OR title = '') AND anilist_id IS NOT NULL");
        $animeCount = (int)$stmt->fetchColumn();
        
        if ($animeCount > 0) {
            output("🎌  Anime 待回填: $animeCount 条", 'info');
            
            $stmt = $pdo->prepare("SELECT id, anilist_id FROM user_anime WHERE (title IS NULL OR title = '') AND anilist_id IS NOT NULL LIMIT :limit");
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($records as $record) {
                $backfillStats['processed']++;
                $details = fetchAniListDetails($record['anilist_id']);
                
                if ($details) {
                    backfillRecord($pdo, 'user_anime', $record['id'], $details);
                    $backfillStats['updated']++;
                    output("✅ Anime #{$record['id']}: {$details['title']}", 'success');
                } else {
                    $backfillStats['failed']++;
                }
                usleep(700000); // 700ms for AniList
            }
        } else {
            output("✅ Anime 已全部有元数据", 'success');
        }
        
        // Books 回填 (Google Books - 不需要 API Key)
        $bookCount = 0;
        try {
            $stmt = $pdo->query("SELECT COUNT(*) FROM user_books WHERE (title IS NULL OR title = '') AND google_books_id IS NOT NULL");
            $bookCount = (int)$stmt->fetchColumn();
            
            if ($bookCount > 0) {
                output("📚  Books 待回填: $bookCount 条", 'info');
                
                $stmt = $pdo->prepare("SELECT id, google_books_id FROM user_books WHERE (title IS NULL OR title = '') AND google_books_id IS NOT NULL LIMIT :limit");
                $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
                $stmt->execute();
                $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                foreach ($records as $record) {
                    $backfillStats['processed']++;
                    $details = fetchGoogleBooksDetails($record['google_books_id']);
                    
                    if ($details) {
                        backfillRecord($pdo, 'user_books', $record['id'], $details);
                        $backfillStats['updated']++;
                        output("✅ Book #{$record['id']}: {$details['title']}", 'success');
                    } else {
                        $backfillStats['failed']++;
                    }
                    usleep(50000);
                }
            } else {
                output("✅ Books 已全部有元数据", 'success');
            }
        } catch (\PDOException $e) {
            output("⚠️  Books 回填跳过: " . $e->getMessage(), 'warning');
        }
        
        // Podcasts 回填 (iTunes - 不需要 API Key)
        $podcastCount = 0;
        try {
            $stmt = $pdo->query("SELECT COUNT(*) FROM user_podcasts WHERE (title IS NULL OR title = '') AND itunes_id IS NOT NULL");
            $podcastCount = (int)$stmt->fetchColumn();
            
            if ($podcastCount > 0) {
                output("🎙️  Podcasts 待回填: $podcastCount 条", 'info');
                
                $stmt = $pdo->prepare("SELECT id, itunes_id FROM user_podcasts WHERE (title IS NULL OR title = '') AND itunes_id IS NOT NULL LIMIT :limit");
                $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
                $stmt->execute();
                $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                foreach ($records as $record) {
                    $backfillStats['processed']++;
                    $details = fetchItunesDetails($record['itunes_id']);
                    
                    if ($details) {
                        backfillRecord($pdo, 'user_podcasts', $record['id'], $details);
                        $backfillStats['updated']++;
                        output("✅ Podcast #{$record['id']}: {$details['title']}", 'success');
                    } else {
                        $backfillStats['failed']++;
                    }
                    usleep(100000);
                }
            } else {
                output("✅ Podcasts 已全部有元数据", 'success');
            }
        } catch (\PDOException $e) {
            output("⚠️  Podcasts 回填跳过: " . $e->getMessage(), 'warning');
        }
        
        output("回填统计: 处理 {$backfillStats['processed']}, 成功 {$backfillStats['updated']}, 失败 {$backfillStats['failed']}", 'info');
        
        if ($backfillStats['processed'] < $movieCount + $tvCount + $docCount + $animeCount + $bookCount + $podcastCount) {
            output("💡 还有更多数据待回填，请多次访问此页面或增加 ?limit=100", 'warning');
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

// ============================================
// Helper Functions for API calls
// ============================================

function fetchTmdbDetails(int $tmdbId, string $type, string $apiKey): ?array {
    $url = "https://api.themoviedb.org/3/{$type}/{$tmdbId}?api_key={$apiKey}&language=zh-CN";
    $context = stream_context_create(['http' => ['timeout' => 10, 'ignore_errors' => true]]);
    $response = @file_get_contents($url, false, $context);
    if (!$response) return null;
    
    $data = json_decode($response, true);
    if (!$data || isset($data['status_code'])) return null;
    
    $isMovie = $type === 'movie';
    return [
        'title' => $isMovie ? ($data['title'] ?? null) : ($data['name'] ?? null),
        'original_title' => $isMovie ? ($data['original_title'] ?? null) : ($data['original_name'] ?? null),
        'cover_image_cdn' => !empty($data['poster_path']) ? "https://image.tmdb.org/t/p/w500{$data['poster_path']}" : null,
        'backdrop_image_cdn' => !empty($data['backdrop_path']) ? "https://image.tmdb.org/t/p/original{$data['backdrop_path']}" : null,
        'overview' => $data['overview'] ?? null,
        'genres' => !empty($data['genres']) ? json_encode(array_column($data['genres'], 'name')) : null,
        'external_rating' => $data['vote_average'] ?? null,
        'runtime' => $data['runtime'] ?? null,
        'number_of_seasons' => $data['number_of_seasons'] ?? null,
        'number_of_episodes' => $data['number_of_episodes'] ?? null,
    ];
}

function fetchAniListDetails(int $anilistId): ?array {
    $query = 'query ($id: Int) { Media(id: $id, type: ANIME) { title { romaji english native } coverImage { large extraLarge } bannerImage description(asHtml: false) genres averageScore format season seasonYear studios(isMain: true) { nodes { name } } source episodes } }';
    
    $ch = curl_init('https://graphql.anilist.co');
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_HTTPHEADER => ['Content-Type: application/json'], CURLOPT_POSTFIELDS => json_encode(['query' => $query, 'variables' => ['id' => $anilistId]]), CURLOPT_TIMEOUT => 10]);
    $response = curl_exec($ch);
    curl_close($ch);
    
    if (!$response) return null;
    $data = json_decode($response, true);
    if (!$data || !isset($data['data']['Media'])) return null;
    
    $media = $data['data']['Media'];
    return [
        'title' => $media['title']['english'] ?? $media['title']['romaji'] ?? null,
        'original_title' => $media['title']['native'] ?? null,
        'cover_image_cdn' => $media['coverImage']['extraLarge'] ?? $media['coverImage']['large'] ?? null,
        'backdrop_image_cdn' => $media['bannerImage'] ?? null,
        'overview' => $media['description'] ?? null,
        'genres' => !empty($media['genres']) ? json_encode($media['genres']) : null,
        'external_rating' => isset($media['averageScore']) ? $media['averageScore'] / 10 : null,
        'format' => $media['format'] ?? null,
        'season_info' => isset($media['season'], $media['seasonYear']) ? "{$media['season']} {$media['seasonYear']}" : null,
        'studio' => $media['studios']['nodes'][0]['name'] ?? null,
        'source' => $media['source'] ?? null,
        'total_episodes' => $media['episodes'] ?? null,
    ];
}

function fetchGoogleBooksDetails(string $bookId): ?array {
    $url = "https://www.googleapis.com/books/v1/volumes/{$bookId}";
    $context = stream_context_create(['http' => ['timeout' => 10]]);
    $response = @file_get_contents($url, false, $context);
    if (!$response) return null;
    
    $data = json_decode($response, true);
    if (!$data || !isset($data['volumeInfo'])) return null;
    
    $info = $data['volumeInfo'];
    return [
        'title' => $info['title'] ?? null,
        'cover_image_cdn' => isset($info['imageLinks']['thumbnail']) ? str_replace('http://', 'https://', $info['imageLinks']['thumbnail']) : null,
        'overview' => $info['description'] ?? null,
        'genres' => !empty($info['categories']) ? json_encode($info['categories']) : null,
        'external_rating' => $info['averageRating'] ?? null,
        'authors' => !empty($info['authors']) ? json_encode($info['authors']) : null,
        'publisher' => $info['publisher'] ?? null,
        'page_count' => $info['pageCount'] ?? null,
    ];
}

function fetchItunesDetails(int $itunesId): ?array {
    $url = "https://itunes.apple.com/lookup?id={$itunesId}&entity=podcast";
    $context = stream_context_create(['http' => ['timeout' => 10]]);
    $response = @file_get_contents($url, false, $context);
    if (!$response) return null;
    
    $data = json_decode($response, true);
    if (empty($data['results'][0])) return null;
    
    $p = $data['results'][0];
    return [
        'title' => $p['collectionName'] ?? $p['trackName'] ?? null,
        'cover_image_cdn' => $p['artworkUrl600'] ?? $p['artworkUrl100'] ?? null,
        'genres' => !empty($p['genres']) ? json_encode($p['genres']) : null,
        'artist_name' => $p['artistName'] ?? null,
        'feed_url' => $p['feedUrl'] ?? null,
        'episode_count' => $p['trackCount'] ?? null,
    ];
}

function backfillRecord(PDO $pdo, string $table, int $id, array $data): void {
    $updates = [];
    $params = [':id' => $id];
    foreach ($data as $field => $value) {
        if ($value !== null) {
            $updates[] = "$field = :$field";
            $params[":$field"] = $value;
        }
    }
    if (empty($updates)) return;
    $pdo->prepare("UPDATE $table SET " . implode(', ', $updates) . " WHERE id = :id")->execute($params);
}
?>
