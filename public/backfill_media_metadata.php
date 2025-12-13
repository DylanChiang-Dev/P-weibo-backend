<?php
require_once __DIR__ . '/_tool_guard.php';
/**
 * 媒体元数据回填工具
 * 
 * 功能：遍历现有媒体记录，调用第三方 API 回填元数据
 * 
 * 使用方式：
 * - 浏览器访问：https://yourdomain.com/backfill_media_metadata.php
 * - 可选参数: ?type=movies&limit=50
 */

declare(strict_types=1);

// 设置超时和内存限制
set_time_limit(600); // 10分钟
ini_set('memory_limit', '256M');

header('Content-Type: text/html; charset=utf-8');

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
        flush();
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
            echo "<div style='color: $color; margin: 3px 0; font-family: monospace;'>$message</div>\n";
        }
        flush();
        ob_flush();
    }
}

if (!$isCli) {
    echo "<!DOCTYPE html><html><head><meta charset='utf-8'><title>媒体元数据回填</title></head><body>";
    echo "<h1>媒体元数据回填工具</h1>";
    echo "<p>开始时间：" . date('Y-m-d H:i:s') . "</p><hr>";
    ob_flush();
    flush();
}

// 获取参数
$type = $_GET['type'] ?? ($argv[1] ?? null);
$limit = (int)($_GET['limit'] ?? ($argv[2] ?? 100));
$dryRun = isset($_GET['dry-run']) || in_array('--dry-run', $argv ?? []);

// 获取 TMDB API Key
$tmdbApiKey = getenv('TMDB_API_KEY') ?: '';

try {
    // 引入配置
    $root = dirname(__DIR__);
    require_once $root . '/config/config.php';
    require_once $root . '/app/Core/Database.php';
    
    \App\Core\Database::init(config()['db']);
    $pdo = \App\Core\Database::getPdo();
    
    output("数据库连接成功", 'success');
    
    if ($dryRun) {
        output("🔍 DRY RUN 模式 - 不会写入数据", 'warning');
    }
    
    // ============================================
    // 统计需要迁移的记录数
    // ============================================
    output('统计待迁移记录', 'title');
    
    $counts = [];
    $tables = [
        'movies' => ['table' => 'user_movies', 'id_field' => 'tmdb_id'],
        'tv_shows' => ['table' => 'user_tv_shows', 'id_field' => 'tmdb_id'],
        'documentaries' => ['table' => 'user_documentaries', 'id_field' => 'tmdb_id'],
        'anime' => ['table' => 'user_anime', 'id_field' => 'anilist_id'],
        'books' => ['table' => 'user_books', 'id_field' => 'google_books_id'],
        'games' => ['table' => 'user_games', 'id_field' => 'igdb_id'],
        'podcasts' => ['table' => 'user_podcasts', 'id_field' => 'itunes_id'],
    ];
    
    foreach ($tables as $name => $config) {
        $stmt = $pdo->query("SELECT COUNT(*) FROM {$config['table']} WHERE (title IS NULL OR title = '') AND {$config['id_field']} IS NOT NULL");
        $counts[$name] = (int)$stmt->fetchColumn();
        output("$name: {$counts[$name]} 条待迁移", 'info');
    }
    
    $totalCount = array_sum($counts);
    output("总计: $totalCount 条记录需要回填", 'info');
    
    if ($totalCount === 0) {
        output("🎉 无需迁移，所有记录已有元数据！", 'success');
        exit;
    }
    
    // 确定要迁移的类型
    $typesToMigrate = $type ? [$type] : array_keys($tables);
    
    $stats = ['processed' => 0, 'updated' => 0, 'failed' => 0, 'skipped' => 0];
    
    // ============================================
    // Movies 迁移
    // ============================================
    if (in_array('movies', $typesToMigrate) && $counts['movies'] > 0) {
        output("迁移 Movies (TMDB)", 'title');
        
        if (empty($tmdbApiKey)) {
            output("TMDB_API_KEY 未设置，跳过 Movies", 'warning');
        } else {
            $stmt = $pdo->prepare("SELECT id, tmdb_id FROM user_movies WHERE (title IS NULL OR title = '') AND tmdb_id IS NOT NULL LIMIT :limit");
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($records as $record) {
                $stats['processed']++;
                $details = fetchTmdbDetails($record['tmdb_id'], 'movie', $tmdbApiKey);
                
                if ($details && !$dryRun) {
                    updateRecord($pdo, 'user_movies', $record['id'], $details);
                    $stats['updated']++;
                    output("✅ ID {$record['id']}: {$details['title']}", 'success');
                } elseif ($details && $dryRun) {
                    output("🔍 Would update ID {$record['id']}: {$details['title']}", 'info');
                } else {
                    $stats['failed']++;
                    output("❌ ID {$record['id']}: 无法获取数据", 'error');
                }
                
                usleep(60000); // 60ms delay
            }
        }
    }
    
    // ============================================
    // TV Shows 迁移
    // ============================================
    if (in_array('tv_shows', $typesToMigrate) && $counts['tv_shows'] > 0) {
        output("迁移 TV Shows (TMDB)", 'title');
        
        if (empty($tmdbApiKey)) {
            output("TMDB_API_KEY 未设置，跳过 TV Shows", 'warning');
        } else {
            $stmt = $pdo->prepare("SELECT id, tmdb_id FROM user_tv_shows WHERE (title IS NULL OR title = '') AND tmdb_id IS NOT NULL LIMIT :limit");
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($records as $record) {
                $stats['processed']++;
                $details = fetchTmdbDetails($record['tmdb_id'], 'tv', $tmdbApiKey);
                
                if ($details && !$dryRun) {
                    updateRecord($pdo, 'user_tv_shows', $record['id'], $details);
                    $stats['updated']++;
                    output("✅ ID {$record['id']}: {$details['title']}", 'success');
                } elseif ($details && $dryRun) {
                    output("🔍 Would update ID {$record['id']}: {$details['title']}", 'info');
                } else {
                    $stats['failed']++;
                    output("❌ ID {$record['id']}: 无法获取数据", 'error');
                }
                
                usleep(60000);
            }
        }
    }
    
    // ============================================
    // Documentaries 迁移
    // ============================================
    if (in_array('documentaries', $typesToMigrate) && $counts['documentaries'] > 0) {
        output("迁移 Documentaries (TMDB)", 'title');
        
        if (empty($tmdbApiKey)) {
            output("TMDB_API_KEY 未设置，跳过 Documentaries", 'warning');
        } else {
            $stmt = $pdo->prepare("SELECT id, tmdb_id FROM user_documentaries WHERE (title IS NULL OR title = '') AND tmdb_id IS NOT NULL LIMIT :limit");
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($records as $record) {
                $stats['processed']++;
                // Documentaries can be movies or TV shows in TMDB, try movie first
                $details = fetchTmdbDetails($record['tmdb_id'], 'movie', $tmdbApiKey);
                if (!$details) {
                    $details = fetchTmdbDetails($record['tmdb_id'], 'tv', $tmdbApiKey);
                }
                
                if ($details && !$dryRun) {
                    updateRecord($pdo, 'user_documentaries', $record['id'], $details);
                    $stats['updated']++;
                    output("✅ ID {$record['id']}: {$details['title']}", 'success');
                } elseif ($details && $dryRun) {
                    output("🔍 Would update ID {$record['id']}: {$details['title']}", 'info');
                } else {
                    $stats['failed']++;
                    output("❌ ID {$record['id']}: 无法获取数据", 'error');
                }
                
                usleep(60000);
            }
        }
    }
    
    // ============================================
    // Anime 迁移 (AniList)
    // ============================================
    if (in_array('anime', $typesToMigrate) && $counts['anime'] > 0) {
        output("迁移 Anime (AniList)", 'title');
        
        $stmt = $pdo->prepare("SELECT id, anilist_id FROM user_anime WHERE (title IS NULL OR title = '') AND anilist_id IS NOT NULL LIMIT :limit");
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($records as $record) {
            $stats['processed']++;
            $details = fetchAniListDetails($record['anilist_id']);
            
            if ($details && !$dryRun) {
                updateRecord($pdo, 'user_anime', $record['id'], $details);
                $stats['updated']++;
                output("✅ ID {$record['id']}: {$details['title']}", 'success');
            } elseif ($details && $dryRun) {
                output("🔍 Would update ID {$record['id']}: {$details['title']}", 'info');
            } else {
                $stats['failed']++;
                output("❌ ID {$record['id']}: 无法获取数据", 'error');
            }
            
            usleep(700000); // 700ms for AniList rate limit
        }
    }
    
    // ============================================
    // Books 迁移 (Google Books)
    // ============================================
    if (in_array('books', $typesToMigrate) && $counts['books'] > 0) {
        output("迁移 Books (Google Books)", 'title');
        
        $stmt = $pdo->prepare("SELECT id, google_books_id FROM user_books WHERE (title IS NULL OR title = '') AND google_books_id IS NOT NULL LIMIT :limit");
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($records as $record) {
            $stats['processed']++;
            $details = fetchGoogleBooksDetails($record['google_books_id']);
            
            if ($details && !$dryRun) {
                updateRecord($pdo, 'user_books', $record['id'], $details);
                $stats['updated']++;
                output("✅ ID {$record['id']}: {$details['title']}", 'success');
            } elseif ($details && $dryRun) {
                output("🔍 Would update ID {$record['id']}: {$details['title']}", 'info');
            } else {
                $stats['failed']++;
                output("❌ ID {$record['id']}: 无法获取数据", 'error');
            }
            
            usleep(50000);
        }
    }
    
    // ============================================
    // Games 迁移 (使用现有 name 字段)
    // ============================================
    if (in_array('games', $typesToMigrate) && $counts['games'] > 0) {
        output("迁移 Games", 'title');
        output("Games 使用 name 字段，需要检查 cover_image_cdn", 'info');
        
        // Games already have name field, just need to ensure cover is set
        $stmt = $pdo->prepare("SELECT id, name, cover_url FROM user_games WHERE name IS NOT NULL AND name != '' AND (cover_image_cdn IS NULL OR cover_image_cdn = '') LIMIT :limit");
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (count($records) === 0) {
            output("所有 Games 已有 cover_image_cdn", 'success');
        } else {
            foreach ($records as $record) {
                // Copy cover_url to cover_image_cdn if exists
                if (!empty($record['cover_url']) && !$dryRun) {
                    $pdo->prepare("UPDATE user_games SET cover_image_cdn = ? WHERE id = ?")->execute([$record['cover_url'], $record['id']]);
                    $stats['updated']++;
                    output("✅ ID {$record['id']}: 已复制封面 URL", 'success');
                }
            }
        }
    }
    
    // ============================================
    // Podcasts 迁移 (iTunes)
    // ============================================
    if (in_array('podcasts', $typesToMigrate) && $counts['podcasts'] > 0) {
        output("迁移 Podcasts (iTunes)", 'title');
        
        $stmt = $pdo->prepare("SELECT id, itunes_id FROM user_podcasts WHERE (title IS NULL OR title = '') AND itunes_id IS NOT NULL LIMIT :limit");
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($records as $record) {
            $stats['processed']++;
            $details = fetchItunesDetails($record['itunes_id']);
            
            if ($details && !$dryRun) {
                updateRecord($pdo, 'user_podcasts', $record['id'], $details);
                $stats['updated']++;
                output("✅ ID {$record['id']}: {$details['title']}", 'success');
            } elseif ($details && $dryRun) {
                output("🔍 Would update ID {$record['id']}: {$details['title']}", 'info');
            } else {
                $stats['failed']++;
                output("❌ ID {$record['id']}: 无法获取数据", 'error');
            }
            
            usleep(100000);
        }
    }
    
    // ============================================
    // 总结
    // ============================================
    output('迁移总结', 'title');
    output("处理: {$stats['processed']} 条", 'info');
    output("成功: {$stats['updated']} 条", 'success');
    output("失败: {$stats['failed']} 条", $stats['failed'] > 0 ? 'error' : 'info');
    
    if ($dryRun) {
        output("💡 这是 DRY RUN，请去掉 ?dry-run 参数重新运行以应用更改", 'warning');
    }
    
} catch (\Throwable $e) {
    output('发生错误: ' . $e->getMessage(), 'error');
}

if (!$isCli) {
    echo "<hr><p>完成时间：" . date('Y-m-d H:i:s') . "</p>";
    echo "<p><small>提示：为了安全，生产环境部署后建议删除此脚本。</small></p>";
    echo "</body></html>";
}

// ============================================
// Helper Functions
// ============================================

function fetchTmdbDetails(int $tmdbId, string $type, string $apiKey): ?array {
    $url = "https://api.themoviedb.org/3/{$type}/{$tmdbId}?api_key={$apiKey}&language=zh-CN";
    
    $context = stream_context_create(['http' => ['timeout' => 10]]);
    $response = @file_get_contents($url, false, $context);
    if (!$response) return null;
    
    $data = json_decode($response, true);
    if (!$data || isset($data['status_code'])) return null;
    
    $isMovie = $type === 'movie';
    
    return [
        'title' => $isMovie ? ($data['title'] ?? null) : ($data['name'] ?? null),
        'original_title' => $isMovie ? ($data['original_title'] ?? null) : ($data['original_name'] ?? null),
        'cover_image_cdn' => !empty($data['poster_path']) 
            ? "https://image.tmdb.org/t/p/w500{$data['poster_path']}" 
            : null,
        'backdrop_image_cdn' => !empty($data['backdrop_path']) 
            ? "https://image.tmdb.org/t/p/original{$data['backdrop_path']}" 
            : null,
        'overview' => $data['overview'] ?? null,
        'genres' => !empty($data['genres']) 
            ? json_encode(array_column($data['genres'], 'name')) 
            : null,
        'external_rating' => $data['vote_average'] ?? null,
        'runtime' => $data['runtime'] ?? null,
        'number_of_seasons' => $data['number_of_seasons'] ?? null,
        'number_of_episodes' => $data['number_of_episodes'] ?? null,
    ];
}

function fetchAniListDetails(int $anilistId): ?array {
    $query = <<<'GRAPHQL'
query ($id: Int) {
    Media(id: $id, type: ANIME) {
        title { romaji english native }
        coverImage { large extraLarge }
        bannerImage
        description(asHtml: false)
        genres
        averageScore
        format
        season
        seasonYear
        studios(isMain: true) { nodes { name } }
        source
        episodes
    }
}
GRAPHQL;
    
    $ch = curl_init('https://graphql.anilist.co');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json'],
        CURLOPT_POSTFIELDS => json_encode(['query' => $query, 'variables' => ['id' => $anilistId]]),
        CURLOPT_TIMEOUT => 10,
    ]);
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    if (!$response) return null;
    
    $data = json_decode($response, true);
    if (!$data || !isset($data['data']['Media'])) return null;
    
    $media = $data['data']['Media'];
    $title = $media['title']['english'] ?? $media['title']['romaji'] ?? null;
    
    return [
        'title' => $title,
        'original_title' => $media['title']['native'] ?? null,
        'cover_image_cdn' => $media['coverImage']['extraLarge'] ?? $media['coverImage']['large'] ?? null,
        'backdrop_image_cdn' => $media['bannerImage'] ?? null,
        'overview' => $media['description'] ?? null,
        'genres' => !empty($media['genres']) ? json_encode($media['genres']) : null,
        'external_rating' => isset($media['averageScore']) ? $media['averageScore'] / 10 : null,
        'format' => $media['format'] ?? null,
        'season_info' => isset($media['season'], $media['seasonYear']) 
            ? "{$media['season']} {$media['seasonYear']}" 
            : null,
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
        'cover_image_cdn' => isset($info['imageLinks']['thumbnail']) 
            ? str_replace('http://', 'https://', $info['imageLinks']['thumbnail'])
            : null,
        'overview' => $info['description'] ?? null,
        'genres' => !empty($info['categories']) ? json_encode($info['categories']) : null,
        'external_rating' => $info['averageRating'] ?? null,
        'authors' => !empty($info['authors']) ? json_encode($info['authors']) : null,
        'publisher' => $info['publisher'] ?? null,
        'page_count' => $info['pageCount'] ?? null,
        'language' => $info['language'] ?? null,
    ];
}

function fetchItunesDetails(int $itunesId): ?array {
    $url = "https://itunes.apple.com/lookup?id={$itunesId}&entity=podcast";
    
    $context = stream_context_create(['http' => ['timeout' => 10]]);
    $response = @file_get_contents($url, false, $context);
    if (!$response) return null;
    
    $data = json_decode($response, true);
    if (empty($data['results'][0])) return null;
    
    $podcast = $data['results'][0];
    
    return [
        'title' => $podcast['collectionName'] ?? $podcast['trackName'] ?? null,
        'cover_image_cdn' => $podcast['artworkUrl600'] ?? $podcast['artworkUrl100'] ?? null,
        'genres' => !empty($podcast['genres']) ? json_encode($podcast['genres']) : null,
        'artist_name' => $podcast['artistName'] ?? null,
        'feed_url' => $podcast['feedUrl'] ?? null,
        'episode_count' => $podcast['trackCount'] ?? null,
        'explicit' => ($podcast['collectionExplicitness'] ?? '') === 'explicit' ? 1 : 0,
    ];
}

function updateRecord(PDO $pdo, string $table, int $id, array $data): void {
    $updates = [];
    $params = [];
    
    foreach ($data as $field => $value) {
        if ($value !== null) {
            $updates[] = "$field = :$field";
            $params[":$field"] = $value;
        }
    }
    
    if (empty($updates)) return;
    
    $params[':id'] = $id;
    $sql = "UPDATE $table SET " . implode(', ', $updates) . " WHERE id = :id";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
}
?>
