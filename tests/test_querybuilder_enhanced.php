<?php
/**
 * Test Enhanced QueryBuilder Features
 * Tests new methods: whereIn, whereNotIn, orWhere, offset, transaction
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
use App\Core\Logger;

// Init Logger and Database
Logger::init($config['log']['path']);
Database::init($config['db']);

echo "Running Enhanced QueryBuilder Tests...\n\n";

// Create test data
$testIds = [];
for ($i = 0; $i < 3; $i++) {
    $username = 'qb_test_' . time() . '_' . $i;
    $testIds[] = QueryBuilder::table('users')->insert([
        'username' => $username,
        'email' => $username . '@example.com',
        'password_hash' => 'hash123'
    ]);
}

// Test 1: whereIn
echo "Test 1: whereIn... ";
try {
    $users = QueryBuilder::table('users')
        ->whereIn('id', $testIds)
        ->get();
    
    if (count($users) === 3) {
        echo "PASS\n";
    } else {
        echo "FAIL (Expected 3, got " . count($users) . ")\n";
        exit(1);
    }
} catch (\Throwable $e) {
    echo "FAIL (" . $e->getMessage() . ")\n";
    exit(1);
}

// Test 2: whereNotIn
echo "Test 2: whereNotIn... ";
try {
    $count = QueryBuilder::table('users')
        ->whereNotIn('id', [$testIds[0]])
        ->whereIn('id', $testIds)
        ->count();
    
    if ($count === 2) {
        echo "PASS\n";
    } else {
        echo "FAIL (Expected 2, got $count)\n";
        exit(1);
    }
} catch (\Throwable $e) {
    echo "FAIL (" . $e->getMessage() . ")\n";
    exit(1);
}

// Test 3: orWhere
echo "Test 3: orWhere... ";
try {
    $users = QueryBuilder::table('users')
        ->where('id', '=', $testIds[0])
        ->orWhere('id', '=', $testIds[1])
        ->get();
    
    if (count($users) === 2) {
        echo "PASS\n";
    } else {
        echo "FAIL (Expected 2, got " . count($users) . ")\n";
        exit(1);
    }
} catch (\Throwable $e) {
    echo "FAIL (" . $e->getMessage() . ")\n";
    exit(1);
}

// Test 4: offset
echo "Test 4: offset... ";
try {
    $users = QueryBuilder::table('users')
        ->whereIn('id', $testIds)
        ->orderBy('id', 'ASC')
        ->limit(2)
        ->offset(1)
        ->get();
    
    if (count($users) === 2 && $users[0]['id'] === $testIds[1]) {
        echo "PASS\n";
    } else {
        echo "FAIL\n";
        print_r($users);
        exit(1);
    }
} catch (\Throwable $e) {
    echo "FAIL (" . $e->getMessage() . ")\n";
    exit(1);
}

// Test 5: transaction (success)
echo "Test 5: transaction (commit)... ";
try {
    $result = QueryBuilder::transaction(function() {
        $timestamp = microtime(true);
        $id = QueryBuilder::table('users')->insert([
            'username' => 'tx_test_' . $timestamp,
            'email' => 'tx_test_' . $timestamp . '@example.com',
            'password_hash' => 'hash123'
        ]);
        return $id;
    });
    
    $user = QueryBuilder::table('users')->where('id', '=', $result)->first();
    if ($user && $user['id'] === $result) {
        echo "PASS\n";
        // Cleanup transaction test user
        Database::execute('DELETE FROM users WHERE id = ?', [$result]);
    } else {
        echo "FAIL\n";
        exit(1);
    }
} catch (\Throwable $e) {
    echo "FAIL (" . $e->getMessage() . ")\n";
    exit(1);
}

// Test 6: transaction (rollback)
echo "Test 6: transaction (rollback)... ";
try {
    $beforeCount = QueryBuilder::table('users')->count();
    
    try {
        QueryBuilder::transaction(function() {
            QueryBuilder::table('users')->insert([
                'username' => 'rollback_test',
                'email' => 'rollback@example.com',
                'password_hash' => 'hash123'
            ]);
            // Force an error to trigger rollback
            throw new \Exception('Force rollback');
        });
    } catch (\Exception $e) {
        // Expected
    }
    
    $afterCount = QueryBuilder::table('users')->count();
    if ($beforeCount === $afterCount) {
        echo "PASS\n";
    } else {
        echo "FAIL (Transaction not rolled back)\n";
        exit(1);
    }
} catch (\Throwable $e) {
    echo "FAIL (" . $e->getMessage() . ")\n";
    exit(1);
}

// Test 7: Empty whereIn (should add false condition)
echo "Test 7: Empty whereIn... ";
try {
    $users = QueryBuilder::table('users')
        ->whereIn('id', [])
        ->get();
    
    if (count($users) === 0) {
        echo "PASS\n";
    } else {
        echo "FAIL\n";
        exit(1);
    }
} catch (\Throwable $e) {
    echo "FAIL (" . $e->getMessage() . ")\n";
    exit(1);
}

// Cleanup
foreach ($testIds as $id) {
    Database::execute('DELETE FROM users WHERE id = ?', [$id]);
}
echo "Cleanup... DONE\n";

echo "\nAll Enhanced QueryBuilder Tests Passed!\n";
?>
