<?php
require_once __DIR__ . '/../config/config.php';
$config = config();
$pdo = new PDO(
    'mysql:host='.$config['db']['host'].';dbname='.$config['db']['name'].';charset='.$config['db']['charset'],
    $config['db']['user'],
    $config['db']['pass']
);

echo "Step 1: Delete related data for users other than ID 2...\n";
$pdo->exec('DELETE FROM comments WHERE user_id != 2');
$pdo->exec('DELETE FROM likes WHERE user_id != 2');
$pdo->exec('DELETE FROM post_images WHERE post_id IN (SELECT id FROM posts WHERE user_id != 2)');
$pdo->exec('DELETE FROM posts WHERE user_id != 2');
$pdo->exec('DELETE FROM refresh_tokens WHERE user_id != 2');

echo "Step 2: Delete users other than ID 2...\n";
$pdo->exec('DELETE FROM users WHERE id != 2');

echo "Step 3: Update admin user ID from 2 to 1...\n";
$pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
$pdo->exec('UPDATE users SET id = 1 WHERE id = 2');
$pdo->exec('UPDATE posts SET user_id = 1 WHERE user_id = 2');
$pdo->exec('UPDATE comments SET user_id = 1 WHERE user_id = 2');
$pdo->exec('UPDATE likes SET user_id = 1 WHERE user_id = 2');
$pdo->exec('UPDATE refresh_tokens SET user_id = 1 WHERE user_id = 2');
$pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

echo "Step 4: Reset AUTO_INCREMENT...\n";
$pdo->exec('ALTER TABLE users AUTO_INCREMENT = 2');

echo "Step 5: Verify final state...\n";
$stmt = $pdo->query('SELECT id, username, email FROM users');
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($users, JSON_PRETTY_PRINT) . "\n";

echo "Database cleanup complete!\n";
