<?php
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/config/config.php';
$config = config();

// Autoload
spl_autoload_register(function (string $class) use ($root) {
    $prefix = 'App\\';
    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }
    $rel = substr($class, strlen($prefix));
    $path = $root . '/app/' . str_replace('\\', '/', $rel) . '.php';
    if (file_exists($path)) require_once $path;
});

use App\Core\Database;
use App\Core\QueryBuilder;
use App\Models\User;

// Init Database
Database::init($config['db']);

echo "Running QueryBuilder Tests...\n";

// Test 1: Query Builder SQL Generation (Mocking not easy without dependency injection, so we test integration)
// We will clean up test data
$testUser = 'test_qb_' . time();
$testEmail = $testUser . '@example.com';

echo "Test 1: Create User... ";
try {
    $id = User::create($testUser, $testEmail, 'hash123');
    if ($id > 0) {
        echo "PASS (ID: $id)\n";
    } else {
        echo "FAIL\n";
        exit(1);
    }
} catch (\Throwable $e) {
    echo "FAIL (" . $e->getMessage() . ")\n";
    exit(1);
}

echo "Test 2: Find User by ID... ";
$user = User::findById($id);
if ($user && $user['username'] === $testUser) {
    echo "PASS\n";
} else {
    echo "FAIL\n";
    print_r($user);
    exit(1);
}

echo "Test 3: Find User by Username... ";
$user = User::findByUsername($testUser);
if ($user && $user['email'] === $testEmail) {
    echo "PASS\n";
} else {
    echo "FAIL\n";
    print_r($user);
    exit(1);
}

echo "Test 4: Query Builder Raw Select... ";
$qbUser = QueryBuilder::table('users')->where('id', '=', $id)->first();
if ($qbUser && $qbUser['username'] === $testUser) {
    echo "PASS\n";
} else {
    echo "FAIL\n";
    exit(1);
}

echo "Test 5: Query Builder Count... ";
$count = QueryBuilder::table('users')->count();
if ($count > 0) {
    echo "PASS (Count: $count)\n";
} else {
    echo "FAIL\n";
    exit(1);
}

// Cleanup
Database::execute('DELETE FROM users WHERE id = ?', [$id]);
echo "Cleanup... DONE\n";

echo "All Tests Passed!\n";
