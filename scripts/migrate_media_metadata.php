#!/usr/bin/env php
<?php
/**
 * Media Metadata Migration Script
 * 
 * This script backfills metadata for existing media library records
 * by calling external APIs (TMDB, AniList, Google Books, IGDB, iTunes)
 * 
 * Usage: php scripts/migrate_media_metadata.php [--type=movies|tv|docs|anime|books|games|podcasts] [--limit=100] [--dry-run]
 * 
 * Rate limits:
 * - TMDB: ~40 requests/second (we use 50ms delay)
 * - AniList: ~90 requests/minute (we use 700ms delay)
 * - Google Books: ~100 requests/second
 * - IGDB: ~4 requests/second (we use 300ms delay)
 * - iTunes: ~20 requests/second (we use 100ms delay)
 */

// Load environment
require_once __DIR__ . '/../app/Core/Bootstrap.php';

use App\Core\Database;

// Parse command line arguments
$options = getopt('', ['type:', 'limit:', 'dry-run', 'help']);

if (isset($options['help'])) {
    echo <<<HELP
Media Metadata Migration Script

Usage: php scripts/migrate_media_metadata.php [options]

Options:
  --type=TYPE      Media type to migrate (movies, tv, docs, anime, books, games, podcasts)
                   If not specified, migrates all types
  --limit=N        Maximum records to process (default: 100)
  --dry-run        Show what would be done without making changes
  --help           Show this help message

Examples:
  php scripts/migrate_media_metadata.php --type=movies --limit=50
  php scripts/migrate_media_metadata.php --dry-run

HELP;
    exit(0);
}

$type = $options['type'] ?? null;
$limit = (int)($options['limit'] ?? 100);
$dryRun = isset($options['dry-run']);

// API configurations (you need to set these in .env)
$config = [
    'tmdb_api_key' => getenv('TMDB_API_KEY') ?: '',
    'igdb_client_id' => getenv('IGDB_CLIENT_ID') ?: '',
    'igdb_access_token' => getenv('IGDB_ACCESS_TOKEN') ?: '',
];

// Validate API keys
function checkApiKeys($config, $type) {
    $warnings = [];
    if (in_array($type, ['movies', 'tv', 'docs', null]) && empty($config['tmdb_api_key'])) {
        $warnings[] = "TMDB_API_KEY not set - movies/tv/docs migration will be skipped";
    }
    if (in_array($type, ['games', null]) && (empty($config['igdb_client_id']) || empty($config['igdb_access_token']))) {
        $warnings[] = "IGDB credentials not set - games migration will be skipped";
    }
    return $warnings;
}

$warnings = checkApiKeys($config, $type);
foreach ($warnings as $warning) {
    echo "⚠️  WARNING: $warning\n";
}

class MediaMetadataMigrator {
    private $pdo;
    private $config;
    private $dryRun;
    private $stats = ['processed' => 0, 'updated' => 0, 'failed' => 0, 'skipped' => 0];
    
    public function __construct(array $config, bool $dryRun = false) {
        $this->pdo = Database::getConnection();
        $this->config = $config;
        $this->dryRun = $dryRun;
    }
    
    public function getStats(): array {
        return $this->stats;
    }
    
    /**
     * Migrate TMDB-based media (movies, tv shows, documentaries)
     */
    public function migrateTmdbMedia(string $table, string $mediaType, int $limit): void {
        if (empty($this->config['tmdb_api_key'])) {
            echo "⏭️  Skipping $table - TMDB API key not configured\n";
            return;
        }
        
        echo "\n📽️  Migrating $table...\n";
        
        $stmt = $this->pdo->prepare(
            "SELECT id, tmdb_id FROM $table WHERE title IS NULL AND tmdb_id IS NOT NULL LIMIT :limit"
        );
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "   Found " . count($records) . " records to migrate\n";
        
        foreach ($records as $record) {
            $this->stats['processed']++;
            
            try {
                $details = $this->fetchTmdbDetails($record['tmdb_id'], $mediaType);
                
                if ($details && !$this->dryRun) {
                    $this->updateRecord($table, $record['id'], $details);
                    $this->stats['updated']++;
                    echo "   ✅ Updated ID {$record['id']}: {$details['title']}\n";
                } elseif ($details && $this->dryRun) {
                    echo "   🔍 Would update ID {$record['id']}: {$details['title']}\n";
                } else {
                    $this->stats['failed']++;
                    echo "   ❌ Failed ID {$record['id']}: No data from TMDB\n";
                }
                
                usleep(50000); // 50ms delay for rate limiting
                
            } catch (Exception $e) {
                $this->stats['failed']++;
                echo "   ❌ Error ID {$record['id']}: " . $e->getMessage() . "\n";
            }
        }
    }
    
    /**
     * Fetch details from TMDB API
     */
    private function fetchTmdbDetails(int $tmdbId, string $type): ?array {
        $apiKey = $this->config['tmdb_api_key'];
        $url = "https://api.themoviedb.org/3/{$type}/{$tmdbId}?api_key={$apiKey}&language=zh-TW";
        
        $response = @file_get_contents($url);
        if (!$response) return null;
        
        $data = json_decode($response, true);
        if (!$data) return null;
        
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
            'tagline' => $data['tagline'] ?? null,
            'number_of_seasons' => $data['number_of_seasons'] ?? null,
            'number_of_episodes' => $data['number_of_episodes'] ?? null,
        ];
    }
    
    /**
     * Migrate AniList-based anime
     */
    public function migrateAnime(int $limit): void {
        echo "\n🎬 Migrating user_anime...\n";
        
        $stmt = $this->pdo->prepare(
            "SELECT id, anilist_id FROM user_anime WHERE title IS NULL AND anilist_id IS NOT NULL LIMIT :limit"
        );
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "   Found " . count($records) . " records to migrate\n";
        
        foreach ($records as $record) {
            $this->stats['processed']++;
            
            try {
                $details = $this->fetchAniListDetails($record['anilist_id']);
                
                if ($details && !$this->dryRun) {
                    $this->updateRecord('user_anime', $record['id'], $details);
                    $this->stats['updated']++;
                    echo "   ✅ Updated ID {$record['id']}: {$details['title']}\n";
                } elseif ($details && $this->dryRun) {
                    echo "   🔍 Would update ID {$record['id']}: {$details['title']}\n";
                } else {
                    $this->stats['failed']++;
                    echo "   ❌ Failed ID {$record['id']}: No data from AniList\n";
                }
                
                usleep(700000); // 700ms delay for rate limiting
                
            } catch (Exception $e) {
                $this->stats['failed']++;
                echo "   ❌ Error ID {$record['id']}: " . $e->getMessage() . "\n";
            }
        }
    }
    
    /**
     * Fetch details from AniList GraphQL API
     */
    private function fetchAniListDetails(int $anilistId): ?array {
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
        
        $response = $this->graphqlRequest('https://graphql.anilist.co', $query, ['id' => $anilistId]);
        
        if (!$response || !isset($response['data']['Media'])) return null;
        
        $media = $response['data']['Media'];
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
    
    /**
     * Make a GraphQL request
     */
    private function graphqlRequest(string $url, string $query, array $variables): ?array {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json'],
            CURLOPT_POSTFIELDS => json_encode(['query' => $query, 'variables' => $variables]),
        ]);
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        return $response ? json_decode($response, true) : null;
    }
    
    /**
     * Migrate Google Books
     */
    public function migrateBooks(int $limit): void {
        echo "\n📚 Migrating user_books...\n";
        
        $stmt = $this->pdo->prepare(
            "SELECT id, google_books_id FROM user_books WHERE title IS NULL AND google_books_id IS NOT NULL LIMIT :limit"
        );
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "   Found " . count($records) . " records to migrate\n";
        
        foreach ($records as $record) {
            $this->stats['processed']++;
            
            try {
                $details = $this->fetchGoogleBooksDetails($record['google_books_id']);
                
                if ($details && !$this->dryRun) {
                    $this->updateRecord('user_books', $record['id'], $details);
                    $this->stats['updated']++;
                    echo "   ✅ Updated ID {$record['id']}: {$details['title']}\n";
                } elseif ($details && $this->dryRun) {
                    echo "   🔍 Would update ID {$record['id']}: {$details['title']}\n";
                } else {
                    $this->stats['failed']++;
                    echo "   ❌ Failed ID {$record['id']}: No data from Google Books\n";
                }
                
                usleep(20000); // 20ms delay
                
            } catch (Exception $e) {
                $this->stats['failed']++;
                echo "   ❌ Error ID {$record['id']}: " . $e->getMessage() . "\n";
            }
        }
    }
    
    /**
     * Fetch details from Google Books API
     */
    private function fetchGoogleBooksDetails(string $bookId): ?array {
        $url = "https://www.googleapis.com/books/v1/volumes/{$bookId}";
        
        $response = @file_get_contents($url);
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
            'published_date' => isset($info['publishedDate']) ? $this->formatDate($info['publishedDate']) : null,
            'page_count' => $info['pageCount'] ?? null,
            'language' => $info['language'] ?? null,
        ];
    }
    
    /**
     * Migrate IGDB Games
     */
    public function migrateGames(int $limit): void {
        if (empty($this->config['igdb_client_id']) || empty($this->config['igdb_access_token'])) {
            echo "⏭️  Skipping user_games - IGDB credentials not configured\n";
            return;
        }
        
        echo "\n🎮 Migrating user_games...\n";
        
        $stmt = $this->pdo->prepare(
            "SELECT id, igdb_id FROM user_games WHERE (name IS NULL OR name = '') AND igdb_id IS NOT NULL LIMIT :limit"
        );
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "   Found " . count($records) . " records to migrate\n";
        
        foreach ($records as $record) {
            $this->stats['processed']++;
            
            try {
                $details = $this->fetchIgdbDetails($record['igdb_id']);
                
                if ($details && !$this->dryRun) {
                    $this->updateRecord('user_games', $record['id'], $details);
                    $this->stats['updated']++;
                    echo "   ✅ Updated ID {$record['id']}: {$details['name']}\n";
                } elseif ($details && $this->dryRun) {
                    echo "   🔍 Would update ID {$record['id']}: {$details['name']}\n";
                } else {
                    $this->stats['failed']++;
                    echo "   ❌ Failed ID {$record['id']}: No data from IGDB\n";
                }
                
                usleep(300000); // 300ms delay
                
            } catch (Exception $e) {
                $this->stats['failed']++;
                echo "   ❌ Error ID {$record['id']}: " . $e->getMessage() . "\n";
            }
        }
    }
    
    /**
     * Fetch details from IGDB API
     */
    private function fetchIgdbDetails(int $igdbId): ?array {
        $ch = curl_init('https://api.igdb.com/v4/games');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Client-ID: ' . $this->config['igdb_client_id'],
                'Authorization: Bearer ' . $this->config['igdb_access_token'],
            ],
            CURLOPT_POSTFIELDS => "fields name,cover.url,summary,genres.name,rating,platforms.name,involved_companies.company.name,involved_companies.developer,involved_companies.publisher,game_modes.name; where id = {$igdbId};",
        ]);
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        if (!$response) return null;
        
        $data = json_decode($response, true);
        if (empty($data[0])) return null;
        
        $game = $data[0];
        
        // Extract developers and publishers
        $developers = [];
        $publishers = [];
        if (!empty($game['involved_companies'])) {
            foreach ($game['involved_companies'] as $company) {
                if ($company['developer'] ?? false) {
                    $developers[] = $company['company']['name'];
                }
                if ($company['publisher'] ?? false) {
                    $publishers[] = $company['company']['name'];
                }
            }
        }
        
        return [
            'name' => $game['name'] ?? null,
            'cover_image_cdn' => isset($game['cover']['url']) 
                ? 'https:' . str_replace('t_thumb', 't_cover_big', $game['cover']['url'])
                : null,
            'overview' => $game['summary'] ?? null,
            'genres' => !empty($game['genres']) 
                ? json_encode(array_column($game['genres'], 'name')) 
                : null,
            'external_rating' => isset($game['rating']) ? round($game['rating'] / 10, 1) : null,
            'platforms' => !empty($game['platforms']) 
                ? json_encode(array_column($game['platforms'], 'name')) 
                : null,
            'developers' => !empty($developers) ? json_encode($developers) : null,
            'publishers' => !empty($publishers) ? json_encode($publishers) : null,
            'game_modes' => !empty($game['game_modes']) 
                ? json_encode(array_column($game['game_modes'], 'name')) 
                : null,
        ];
    }
    
    /**
     * Migrate iTunes Podcasts
     */
    public function migratePodcasts(int $limit): void {
        echo "\n🎙️ Migrating user_podcasts...\n";
        
        $stmt = $this->pdo->prepare(
            "SELECT id, itunes_id FROM user_podcasts WHERE title IS NULL AND itunes_id IS NOT NULL LIMIT :limit"
        );
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "   Found " . count($records) . " records to migrate\n";
        
        foreach ($records as $record) {
            $this->stats['processed']++;
            
            try {
                $details = $this->fetchItunesDetails($record['itunes_id']);
                
                if ($details && !$this->dryRun) {
                    $this->updateRecord('user_podcasts', $record['id'], $details);
                    $this->stats['updated']++;
                    echo "   ✅ Updated ID {$record['id']}: {$details['title']}\n";
                } elseif ($details && $this->dryRun) {
                    echo "   🔍 Would update ID {$record['id']}: {$details['title']}\n";
                } else {
                    $this->stats['failed']++;
                    echo "   ❌ Failed ID {$record['id']}: No data from iTunes\n";
                }
                
                usleep(100000); // 100ms delay
                
            } catch (Exception $e) {
                $this->stats['failed']++;
                echo "   ❌ Error ID {$record['id']}: " . $e->getMessage() . "\n";
            }
        }
    }
    
    /**
     * Fetch details from iTunes API
     */
    private function fetchItunesDetails(int $itunesId): ?array {
        $url = "https://itunes.apple.com/lookup?id={$itunesId}&entity=podcast";
        
        $response = @file_get_contents($url);
        if (!$response) return null;
        
        $data = json_decode($response, true);
        if (empty($data['results'][0])) return null;
        
        $podcast = $data['results'][0];
        
        return [
            'title' => $podcast['collectionName'] ?? $podcast['trackName'] ?? null,
            'cover_image_cdn' => $podcast['artworkUrl600'] ?? $podcast['artworkUrl100'] ?? null,
            'overview' => null, // iTunes doesn't provide description in lookup API
            'genres' => !empty($podcast['genres']) ? json_encode($podcast['genres']) : null,
            'artist_name' => $podcast['artistName'] ?? null,
            'feed_url' => $podcast['feedUrl'] ?? null,
            'episode_count' => $podcast['trackCount'] ?? null,
            'explicit' => ($podcast['collectionExplicitness'] ?? '') === 'explicit' ? 1 : 0,
        ];
    }
    
    /**
     * Update a database record
     */
    private function updateRecord(string $table, int $id, array $data): void {
        // Filter out null values and build update query
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
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
    }
    
    /**
     * Format date string to MySQL DATE format
     */
    private function formatDate(string $date): ?string {
        if (preg_match('/^\d{4}$/', $date)) {
            return "{$date}-01-01";
        }
        if (preg_match('/^\d{4}-\d{2}$/', $date)) {
            return "{$date}-01";
        }
        $timestamp = strtotime($date);
        return $timestamp ? date('Y-m-d', $timestamp) : null;
    }
}

// Main execution
echo "🚀 Media Metadata Migration Script\n";
echo "===================================\n";

if ($dryRun) {
    echo "🔍 DRY RUN MODE - No changes will be made\n";
}

$migrator = new MediaMetadataMigrator($config, $dryRun);

$types = $type ? [$type] : ['movies', 'tv', 'docs', 'anime', 'books', 'games', 'podcasts'];

foreach ($types as $mediaType) {
    switch ($mediaType) {
        case 'movies':
            $migrator->migrateTmdbMedia('user_movies', 'movie', $limit);
            break;
        case 'tv':
            $migrator->migrateTmdbMedia('user_tv_shows', 'tv', $limit);
            break;
        case 'docs':
            $migrator->migrateTmdbMedia('user_documentaries', 'movie', $limit); // TMDB treats docs as movies
            break;
        case 'anime':
            $migrator->migrateAnime($limit);
            break;
        case 'books':
            $migrator->migrateBooks($limit);
            break;
        case 'games':
            $migrator->migrateGames($limit);
            break;
        case 'podcasts':
            $migrator->migratePodcasts($limit);
            break;
    }
}

// Print summary
$stats = $migrator->getStats();
echo "\n===================================\n";
echo "📊 Migration Summary\n";
echo "   Processed: {$stats['processed']}\n";
echo "   Updated:   {$stats['updated']}\n";
echo "   Failed:    {$stats['failed']}\n";
echo "   Skipped:   {$stats['skipped']}\n";
echo "===================================\n";

if ($dryRun) {
    echo "\n💡 Run without --dry-run to apply changes\n";
}
