<?php
/**
 * Database Migration: Add is_pinned column to posts table
 * 
 * This migration adds a boolean column to allow pinning posts to the top of the feed.
 * 
 * Usage: php scripts/migration_pin_posts.php
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

echo "Adding is_pinned column to posts table...\n";

// Check if column already exists
$stmt = $pdo->query("SHOW COLUMNS FROM posts LIKE 'is_pinned'");
if ($stmt->rowCount() > 0) {
    echo "Column is_pinned already exists, skipping...\n";
    exit(0);
}

// Add is_pinned column
$sql = "ALTER TABLE posts ADD COLUMN is_pinned TINYINT(1) DEFAULT 0 NOT NULL AFTER is_deleted";

try {
    $pdo->exec($sql);
    echo "✓ Column is_pinned added successfully!\n";
} catch (PDOException $e) {
    echo "ERROR: Failed to add column: " . $e->getMessage() . "\n";
    exit(1);
}

// Add index for better query performance
echo "Adding index on is_pinned...\n";
$indexSql = "ALTER TABLE posts ADD INDEX idx_is_pinned (is_pinned)";

try {
    $pdo->exec($indexSql);
    echo "✓ Index idx_is_pinned added successfully!\n";
} catch (PDOException $e) {
    echo "ERROR: Failed to add index: " . $e->getMessage() . "\n";
    exit(1);
}

// Verify table structure
echo "\nVerifying posts table structure...\n";
$stmt = $pdo->query("DESCRIBE posts");
$columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($columns as $col) {
    if ($col['Field'] === 'is_pinned' || $col['Field'] === 'is_deleted') {
        echo "  - {$col['Field']} ({$col['Type']}) {$col['Null']} {$col['Default']}\n";
    }
}

echo "\nMigration complete!\n";
