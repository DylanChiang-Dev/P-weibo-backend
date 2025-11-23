<?php
/**
 * Create Admin User Script
 * Usage: php scripts/create_admin.php <email> <password> [username]
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
use App\Models\User;

// Init Database
Database::init($config['db']);

if ($argc < 3) {
    echo "Usage: php scripts/create_admin.php <email> <password> [username]\n";
    exit(1);
}

$email = $argv[1];
$password = $argv[2];
$username = $argv[3] ?? explode('@', $email)[0];

// Check if user exists
$existing = User::findByEmail($email);
if ($existing) {
    echo "User with email $email already exists.\n";
    
    // Ask to promote
    echo "Do you want to promote this user to admin? (y/n): ";
    $handle = fopen ("php://stdin","r");
    $line = fgets($handle);
    if(trim($line) != 'y'){
        echo "Aborted.\n";
        exit;
    }
    
    QueryBuilder::table('users')
        ->where('id', '=', $existing['id'])
        ->update(['role' => User::ROLE_ADMIN]);
        
    echo "User promoted to admin successfully!\n";
    exit;
}

// Create new admin
try {
    $hash = password_hash($password, PASSWORD_DEFAULT);
    
    QueryBuilder::table('users')->insert([
        'username' => $username,
        'email' => $email,
        'password_hash' => $hash,
        'role' => User::ROLE_ADMIN
    ]);
    
    echo "Admin user created successfully!\n";
    echo "Email: $email\n";
    echo "Username: $username\n";
} catch (\Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
