<?php
require_once __DIR__ . '/../config/config.php';
$config = config();
$pdo = new PDO(
    'mysql:host='.$config['db']['host'].';dbname='.$config['db']['name'].';charset='.$config['db']['charset'],
    $config['db']['user'],
    $config['db']['pass']
);

echo "Removing username column from users table...\n";
$pdo->exec('ALTER TABLE users DROP COLUMN username');

echo "Verifying schema...\n";
$stmt = $pdo->query("DESCRIBE users");
$columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($columns as $col) {
    echo "  - {$col['Field']} ({$col['Type']})\n";
}

echo "Migration complete!\n";
