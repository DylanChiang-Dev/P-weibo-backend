#!/usr/bin/env php
<?php
/**
 * Database Migration: Add Media Metadata Fields
 * 
 * This script adds all metadata fields to media library tables.
 * Run this once on the production database.
 * 
 * Usage: php scripts/run_media_metadata_migration.php
 */

require_once __DIR__ . '/../app/Core/Bootstrap.php';

use App\Core\Database;

$pdo = Database::getConnection();

echo "🚀 Starting Media Metadata Migration...\n";
echo "=====================================\n\n";

$migrations = [
    // ============================================
    // P0: Core Fields
    // ============================================
    'P0: user_movies - Add core fields' => [
        "ALTER TABLE user_movies ADD COLUMN IF NOT EXISTS title VARCHAR(500) NULL AFTER tmdb_id",
        "ALTER TABLE user_movies ADD COLUMN IF NOT EXISTS original_title VARCHAR(500) NULL AFTER title",
        "ALTER TABLE user_movies ADD COLUMN IF NOT EXISTS cover_image_cdn TEXT NULL AFTER original_title",
        "ALTER TABLE user_movies ADD COLUMN IF NOT EXISTS cover_image_local TEXT NULL AFTER cover_image_cdn",
    ],
    
    'P0: user_tv_shows - Add core fields' => [
        "ALTER TABLE user_tv_shows ADD COLUMN IF NOT EXISTS title VARCHAR(500) NULL AFTER tmdb_id",
        "ALTER TABLE user_tv_shows ADD COLUMN IF NOT EXISTS original_title VARCHAR(500) NULL AFTER title",
        "ALTER TABLE user_tv_shows ADD COLUMN IF NOT EXISTS cover_image_cdn TEXT NULL AFTER original_title",
        "ALTER TABLE user_tv_shows ADD COLUMN IF NOT EXISTS cover_image_local TEXT NULL AFTER cover_image_cdn",
    ],
    
    'P0: user_documentaries - Add core fields' => [
        "ALTER TABLE user_documentaries ADD COLUMN IF NOT EXISTS title VARCHAR(500) NULL AFTER tmdb_id",
        "ALTER TABLE user_documentaries ADD COLUMN IF NOT EXISTS original_title VARCHAR(500) NULL AFTER title",
        "ALTER TABLE user_documentaries ADD COLUMN IF NOT EXISTS cover_image_cdn TEXT NULL AFTER original_title",
        "ALTER TABLE user_documentaries ADD COLUMN IF NOT EXISTS cover_image_local TEXT NULL AFTER cover_image_cdn",
    ],
    
    'P0: user_books - Add core fields' => [
        "ALTER TABLE user_books ADD COLUMN IF NOT EXISTS title VARCHAR(500) NULL AFTER isbn",
        "ALTER TABLE user_books ADD COLUMN IF NOT EXISTS original_title VARCHAR(500) NULL AFTER title",
        "ALTER TABLE user_books ADD COLUMN IF NOT EXISTS cover_image_cdn TEXT NULL AFTER original_title",
        "ALTER TABLE user_books ADD COLUMN IF NOT EXISTS cover_image_local TEXT NULL AFTER cover_image_cdn",
    ],
    
    'P0: user_anime - Add missing fields' => [
        "ALTER TABLE user_anime ADD COLUMN IF NOT EXISTS original_title VARCHAR(500) NULL AFTER title",
        "ALTER TABLE user_anime ADD COLUMN IF NOT EXISTS cover_image_local TEXT NULL AFTER cover_image_cdn",
    ],
    
    'P0: user_games - Add cover_image_local' => [
        "ALTER TABLE user_games ADD COLUMN IF NOT EXISTS cover_image_local TEXT NULL AFTER cover_image_cdn",
    ],
    
    'P0: user_podcasts - Add cover_image_local' => [
        "ALTER TABLE user_podcasts ADD COLUMN IF NOT EXISTS cover_image_local TEXT NULL AFTER cover_image_cdn",
    ],
    
    // ============================================
    // P1: Extended Fields
    // ============================================
    'P1: user_movies - Add extended fields' => [
        "ALTER TABLE user_movies ADD COLUMN IF NOT EXISTS overview TEXT NULL AFTER cover_image_local",
        "ALTER TABLE user_movies ADD COLUMN IF NOT EXISTS genres JSON NULL AFTER overview",
        "ALTER TABLE user_movies ADD COLUMN IF NOT EXISTS external_rating DECIMAL(3,1) NULL AFTER genres",
        "ALTER TABLE user_movies ADD COLUMN IF NOT EXISTS backdrop_image_cdn TEXT NULL AFTER external_rating",
        "ALTER TABLE user_movies ADD COLUMN IF NOT EXISTS backdrop_image_local TEXT NULL AFTER backdrop_image_cdn",
    ],
    
    'P1: user_tv_shows - Add extended fields' => [
        "ALTER TABLE user_tv_shows ADD COLUMN IF NOT EXISTS overview TEXT NULL AFTER cover_image_local",
        "ALTER TABLE user_tv_shows ADD COLUMN IF NOT EXISTS genres JSON NULL AFTER overview",
        "ALTER TABLE user_tv_shows ADD COLUMN IF NOT EXISTS external_rating DECIMAL(3,1) NULL AFTER genres",
        "ALTER TABLE user_tv_shows ADD COLUMN IF NOT EXISTS backdrop_image_cdn TEXT NULL AFTER external_rating",
        "ALTER TABLE user_tv_shows ADD COLUMN IF NOT EXISTS backdrop_image_local TEXT NULL AFTER backdrop_image_cdn",
    ],
    
    'P1: user_documentaries - Add extended fields' => [
        "ALTER TABLE user_documentaries ADD COLUMN IF NOT EXISTS overview TEXT NULL AFTER cover_image_local",
        "ALTER TABLE user_documentaries ADD COLUMN IF NOT EXISTS genres JSON NULL AFTER overview",
        "ALTER TABLE user_documentaries ADD COLUMN IF NOT EXISTS external_rating DECIMAL(3,1) NULL AFTER genres",
        "ALTER TABLE user_documentaries ADD COLUMN IF NOT EXISTS backdrop_image_cdn TEXT NULL AFTER external_rating",
        "ALTER TABLE user_documentaries ADD COLUMN IF NOT EXISTS backdrop_image_local TEXT NULL AFTER backdrop_image_cdn",
    ],
    
    'P1: user_anime - Add extended fields' => [
        "ALTER TABLE user_anime ADD COLUMN IF NOT EXISTS overview TEXT NULL AFTER cover_image_local",
        "ALTER TABLE user_anime ADD COLUMN IF NOT EXISTS genres JSON NULL AFTER overview",
        "ALTER TABLE user_anime ADD COLUMN IF NOT EXISTS external_rating DECIMAL(3,1) NULL AFTER genres",
        "ALTER TABLE user_anime ADD COLUMN IF NOT EXISTS backdrop_image_cdn TEXT NULL AFTER external_rating",
        "ALTER TABLE user_anime ADD COLUMN IF NOT EXISTS backdrop_image_local TEXT NULL AFTER backdrop_image_cdn",
    ],
    
    'P1: user_books - Add extended fields' => [
        "ALTER TABLE user_books ADD COLUMN IF NOT EXISTS overview TEXT NULL AFTER cover_image_local",
        "ALTER TABLE user_books ADD COLUMN IF NOT EXISTS genres JSON NULL AFTER overview",
        "ALTER TABLE user_books ADD COLUMN IF NOT EXISTS external_rating DECIMAL(3,1) NULL AFTER genres",
    ],
    
    'P1: user_games - Add extended fields' => [
        "ALTER TABLE user_games ADD COLUMN IF NOT EXISTS overview TEXT NULL AFTER cover_image_local",
        "ALTER TABLE user_games ADD COLUMN IF NOT EXISTS genres JSON NULL AFTER overview",
        "ALTER TABLE user_games ADD COLUMN IF NOT EXISTS external_rating DECIMAL(3,1) NULL AFTER genres",
        "ALTER TABLE user_games ADD COLUMN IF NOT EXISTS backdrop_image_cdn TEXT NULL AFTER external_rating",
        "ALTER TABLE user_games ADD COLUMN IF NOT EXISTS backdrop_image_local TEXT NULL AFTER backdrop_image_cdn",
    ],
    
    'P1: user_podcasts - Add extended fields' => [
        "ALTER TABLE user_podcasts ADD COLUMN IF NOT EXISTS overview TEXT NULL AFTER cover_image_local",
        "ALTER TABLE user_podcasts ADD COLUMN IF NOT EXISTS genres JSON NULL AFTER overview",
        "ALTER TABLE user_podcasts ADD COLUMN IF NOT EXISTS external_rating DECIMAL(3,1) NULL AFTER genres",
    ],
    
    // ============================================
    // P2: Media-Specific Fields
    // ============================================
    'P2: user_movies - Add movie-specific fields' => [
        "ALTER TABLE user_movies ADD COLUMN IF NOT EXISTS runtime INT NULL AFTER backdrop_image_local",
        "ALTER TABLE user_movies ADD COLUMN IF NOT EXISTS tagline VARCHAR(500) NULL AFTER runtime",
        "ALTER TABLE user_movies ADD COLUMN IF NOT EXISTS director VARCHAR(255) NULL AFTER tagline",
        "ALTER TABLE user_movies ADD COLUMN IF NOT EXISTS cast JSON NULL AFTER director",
    ],
    
    'P2: user_tv_shows - Add tv-specific fields' => [
        "ALTER TABLE user_tv_shows ADD COLUMN IF NOT EXISTS number_of_seasons INT NULL AFTER backdrop_image_local",
        "ALTER TABLE user_tv_shows ADD COLUMN IF NOT EXISTS number_of_episodes INT NULL AFTER number_of_seasons",
        "ALTER TABLE user_tv_shows ADD COLUMN IF NOT EXISTS episode_runtime INT NULL AFTER number_of_episodes",
        "ALTER TABLE user_tv_shows ADD COLUMN IF NOT EXISTS networks JSON NULL AFTER episode_runtime",
    ],
    
    'P2: user_documentaries - Add doc-specific fields' => [
        "ALTER TABLE user_documentaries ADD COLUMN IF NOT EXISTS number_of_seasons INT NULL AFTER backdrop_image_local",
        "ALTER TABLE user_documentaries ADD COLUMN IF NOT EXISTS number_of_episodes INT NULL AFTER number_of_seasons",
        "ALTER TABLE user_documentaries ADD COLUMN IF NOT EXISTS episode_runtime INT NULL AFTER number_of_episodes",
        "ALTER TABLE user_documentaries ADD COLUMN IF NOT EXISTS networks JSON NULL AFTER episode_runtime",
    ],
    
    'P2: user_anime - Add anime-specific fields' => [
        "ALTER TABLE user_anime ADD COLUMN IF NOT EXISTS format VARCHAR(50) NULL AFTER backdrop_image_local",
        "ALTER TABLE user_anime ADD COLUMN IF NOT EXISTS season_info VARCHAR(50) NULL AFTER format",
        "ALTER TABLE user_anime ADD COLUMN IF NOT EXISTS studio VARCHAR(255) NULL AFTER season_info",
        "ALTER TABLE user_anime ADD COLUMN IF NOT EXISTS source VARCHAR(50) NULL AFTER studio",
    ],
    
    'P2: user_books - Add book-specific fields' => [
        "ALTER TABLE user_books ADD COLUMN IF NOT EXISTS authors JSON NULL AFTER external_rating",
        "ALTER TABLE user_books ADD COLUMN IF NOT EXISTS publisher VARCHAR(255) NULL AFTER authors",
        "ALTER TABLE user_books ADD COLUMN IF NOT EXISTS published_date DATE NULL AFTER publisher",
        "ALTER TABLE user_books ADD COLUMN IF NOT EXISTS page_count INT NULL AFTER published_date",
        "ALTER TABLE user_books ADD COLUMN IF NOT EXISTS isbn_10 VARCHAR(13) NULL AFTER page_count",
        "ALTER TABLE user_books ADD COLUMN IF NOT EXISTS isbn_13 VARCHAR(17) NULL AFTER isbn_10",
        "ALTER TABLE user_books ADD COLUMN IF NOT EXISTS language VARCHAR(10) NULL AFTER isbn_13",
    ],
    
    'P2: user_games - Add game-specific fields' => [
        "ALTER TABLE user_games ADD COLUMN IF NOT EXISTS platforms JSON NULL AFTER backdrop_image_local",
        "ALTER TABLE user_games ADD COLUMN IF NOT EXISTS developers JSON NULL AFTER platforms",
        "ALTER TABLE user_games ADD COLUMN IF NOT EXISTS publishers JSON NULL AFTER developers",
        "ALTER TABLE user_games ADD COLUMN IF NOT EXISTS game_modes JSON NULL AFTER publishers",
    ],
    
    'P2: user_podcasts - Add podcast-specific fields' => [
        "ALTER TABLE user_podcasts ADD COLUMN IF NOT EXISTS artist_name VARCHAR(255) NULL AFTER external_rating",
        "ALTER TABLE user_podcasts ADD COLUMN IF NOT EXISTS feed_url TEXT NULL AFTER artist_name",
        "ALTER TABLE user_podcasts ADD COLUMN IF NOT EXISTS episode_count INT NULL AFTER feed_url",
        "ALTER TABLE user_podcasts ADD COLUMN IF NOT EXISTS explicit BOOLEAN DEFAULT FALSE AFTER episode_count",
    ],
    
    // ============================================
    // Indexes
    // ============================================
    'Add title indexes' => [
        "CREATE INDEX IF NOT EXISTS idx_movies_title ON user_movies(title(255))",
        "CREATE INDEX IF NOT EXISTS idx_tv_title ON user_tv_shows(title(255))",
        "CREATE INDEX IF NOT EXISTS idx_docs_title ON user_documentaries(title(255))",
        "CREATE INDEX IF NOT EXISTS idx_books_title ON user_books(title(255))",
    ],
];

$totalSuccess = 0;
$totalFailed = 0;

foreach ($migrations as $name => $queries) {
    echo "📦 $name\n";
    
    foreach ($queries as $sql) {
        try {
            $pdo->exec($sql);
            echo "   ✅ OK\n";
            $totalSuccess++;
        } catch (PDOException $e) {
            // Check if it's a "column already exists" error (code 1060)
            if (strpos($e->getMessage(), '1060') !== false || strpos($e->getMessage(), 'Duplicate column') !== false) {
                echo "   ⏭️  Already exists, skipping\n";
                $totalSuccess++;
            } else {
                echo "   ❌ Error: " . $e->getMessage() . "\n";
                $totalFailed++;
            }
        }
    }
    echo "\n";
}

echo "=====================================\n";
echo "📊 Migration Complete!\n";
echo "   ✅ Success: $totalSuccess\n";
echo "   ❌ Failed: $totalFailed\n";
echo "=====================================\n";

if ($totalFailed > 0) {
    exit(1);
}
