<?php
require_once __DIR__ . '/../config/config.php';
$config = config();
$pdo = new PDO(
    'mysql:host='.$config['db']['host'].';dbname='.$config['db']['name'].';charset='.$config['db']['charset'],
    $config['db']['user'],
    $config['db']['pass']
);

echo "Modifying comments table...\n";

// 1. Allow user_id to be NULL
// Note: We need to drop the foreign key first if it exists, then modify the column, then re-add FK if needed.
// However, for simplicity in this environment, we'll try modifying the column directly. 
// If strict mode is on or FK constraints prevent it, we might need a more complex script.
// Let's check constraints first or just try to modify.
// Usually: ALTER TABLE comments MODIFY user_id BIGINT UNSIGNED NULL;

try {
    // Check if FK exists to drop it (optional, but safer)
    // For now, let's try to modify the column to be nullable.
    $pdo->exec('ALTER TABLE comments MODIFY user_id BIGINT UNSIGNED NULL');
    echo "  - user_id is now nullable\n";
} catch (PDOException $e) {
    echo "  - Error modifying user_id: " . $e->getMessage() . "\n";
}

// 2. Add author_name column
try {
    $pdo->exec('ALTER TABLE comments ADD COLUMN author_name VARCHAR(100) NULL AFTER user_id');
    echo "  - author_name column added\n";
} catch (PDOException $e) {
    // Ignore if already exists
    if (strpos($e->getMessage(), 'Duplicate column') === false) {
        echo "  - Error adding author_name: " . $e->getMessage() . "\n";
    } else {
        echo "  - author_name already exists\n";
    }
}

echo "Verifying schema...\n";
$stmt = $pdo->query("DESCRIBE comments");
$columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($columns as $col) {
    echo "  - {$col['Field']} ({$col['Type']}) Null: {$col['Null']}\n";
}

echo "Migration complete!\n";
