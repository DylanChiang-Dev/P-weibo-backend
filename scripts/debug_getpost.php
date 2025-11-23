<?php
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/app/Core/Database.php';
require_once dirname(__DIR__) . '/app/Core/QueryBuilder.php';

use App\Core\Database;
use App\Core\QueryBuilder;

$config = config();
Database::init($config['db']);

$postId = 288;

echo "Testing Post::getById($postId)\n\n";

// Simulate Post::getById
$row = QueryBuilder::table('posts')
    ->select([
        'posts.*', 
        'posts.visibility',
        'users.email', 
        'users.display_name', 
        'users.avatar_path',
        'users.role'
    ])
    ->join('users', 'posts.user_id', '=', 'users.id')
    ->where('posts.id', '=', $postId)
    ->where('posts.is_deleted', '=', 0)
    ->first();

echo "Query result:\n";
var_dump($row);

if ($row) {
    echo "\nKey fields:\n";
    echo " - id: " . $row['id'] . "\n";
    echo " - user_id: " . $row['user_id'] . "\n";
    echo " - visibility: " . $row['visibility'] . "\n";
    echo " - content: " . substr($row['content'], 0, 30) . "...\n";
}
