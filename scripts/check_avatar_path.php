<?php
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/app/Core/Database.php';
require_once dirname(__DIR__) . '/app/Core/QueryBuilder.php';

use App\Core\Database;
use App\Core\QueryBuilder;

$config = config();
Database::init($config['db']);

$user = QueryBuilder::table('users')->where('id', '=', 1)->first();
echo "Database Path: " . $user['avatar_path'] . "\n";

if (strpos($user['avatar_path'], '/uploads/avatars/') !== false) {
    echo "Path is CORRECT in database.\n";
} else {
    echo "Path is INCORRECT in database.\n";
}
