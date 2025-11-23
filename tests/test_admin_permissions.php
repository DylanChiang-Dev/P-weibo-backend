<?php
/**
 * Test Admin Permissions
 * Verify that only admins can perform protected actions
 */

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
use App\Core\Request;
use App\Middleware\AdminMiddleware;
use App\Models\User;
use App\Exceptions\ForbiddenException;

// Init Database
Database::init($config['db']);

echo "Running Admin Permission Tests...\n\n";

// Setup: Create Admin and User
$adminEmail = 'test_admin_' . time() . '@example.com';
$userEmail = 'test_user_' . time() . '@example.com';

try {
    // Manually insert since User::create doesn't support username yet in this test context
    // Or update User::create to support username. Let's use QueryBuilder directly for test setup flexibility
    $adminId = QueryBuilder::table('users')->insert([
        'email' => $adminEmail,
        'username' => 'admin_' . time(),
        'password_hash' => 'hash',
        'role' => User::ROLE_ADMIN
    ]);
    
    $userId = QueryBuilder::table('users')->insert([
        'email' => $userEmail,
        'username' => 'user_' . time(),
        'password_hash' => 'hash',
        'role' => User::ROLE_USER
    ]);
} catch (\Throwable $e) {
    echo "Setup Failed: " . $e->getMessage() . "\n";
    exit(1);
}

// Test 1: Admin Access
echo "Test 1: Admin access to protected route... ";
try {
    $middleware = new AdminMiddleware();
    $request = new Request('POST', '/api/posts', [], [], [], [], []);
    $request->user = ['id' => $adminId, 'role' => User::ROLE_ADMIN];
    
    $result = $middleware->handle($request, function($req) {
        return 'success';
    });
    
    if ($result === 'success') {
        echo "PASS\n";
    } else {
        echo "FAIL\n";
        exit(1);
    }
} catch (\Throwable $e) {
    echo "FAIL (" . $e->getMessage() . ")\n";
    exit(1);
}

// Test 2: User Access (Should Fail)
echo "Test 2: Regular user access to protected route... ";
try {
    $middleware = new AdminMiddleware();
    $request = new Request('POST', '/api/posts', [], [], [], [], []);
    $request->user = ['id' => $userId, 'role' => User::ROLE_USER];
    
    try {
        $middleware->handle($request, function($req) {
            return 'should not reach here';
        });
        echo "FAIL (Should have thrown ForbiddenException)\n";
        exit(1);
    } catch (ForbiddenException $e) {
        echo "PASS\n";
    }
} catch (\Throwable $e) {
    echo "FAIL (" . $e->getMessage() . ")\n";
    exit(1);
}

// Cleanup
Database::execute('DELETE FROM users WHERE id IN (?, ?)', [$adminId, $userId]);
echo "Cleanup... DONE\n";

echo "\nAll Admin Permission Tests Passed!\n";
?>
