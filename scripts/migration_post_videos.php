<?php
/**
 * Database Migration: Create post_videos table
 * 
 * This migration creates a new table to store video metadata for posts.
 * 
 * Usage: php scripts/migration_post_videos.php
 */

require_once __DIR__ . '/../config/config.php';

$config = config();

try {
    $pdo = new PDO(
        'mysql:host='.$config['db']['host'].';dbname='.$config['db']['name'].';charset='.$config['db']['charset'],
        $config['db']['user'],
        $config['db']['pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    echo "ERROR: Database connection failed: " . $e->getMessage() . "\n";
    exit(1);
}

echo "Creating post_videos table...\n";

$sql = "
CREATE TABLE IF NOT EXISTS post_videos (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    post_id BIGINT UNSIGNED NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    thumbnail_path VARCHAR(255) DEFAULT NULL,
    duration INT UNSIGNED DEFAULT NULL,
    file_size BIGINT UNSIGNED DEFAULT NULL,
    mime_type VARCHAR(50) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
    INDEX idx_post_id (post_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
";

try {
    $pdo->exec($sql);
    echo "✓ post_videos table created successfully!\n";
} catch (PDOException $e) {
    echo "ERROR: Failed to create table: " . $e->getMessage() . "\n";
    exit(1);
}

// Verify table structure
echo "\nVerifying table structure...\n";
$stmt = $pdo->query("DESCRIBE post_videos");
$columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($columns as $col) {
    echo "  - {$col['Field']} ({$col['Type']}) {$col['Null']} {$col['Key']}\n";
}

echo "\nMigration complete!\n";
