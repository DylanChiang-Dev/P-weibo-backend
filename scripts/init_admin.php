<?php
/**
 * Admin Account Initialization Script
 * 
 * This script creates or updates the admin account based on environment variables.
 * It's safe to run multiple times (idempotent).
 * 
 * Usage: php scripts/init_admin.php
 */

require_once __DIR__ . '/../config/config.php';

$config = config();

// Get admin credentials from environment
$adminEmail = $config['admin']['email'] ?? null;
$adminPassword = $config['admin']['password'] ?? null;
$adminDisplayName = $config['admin']['display_name'] ?? 'Admin';

// Validate configuration
if (!$adminEmail || !$adminPassword) {
    echo "ERROR: Admin credentials not configured in .env\n";
    echo "Please set ADMIN_EMAIL and ADMIN_PASSWORD in your .env file.\n";
    exit(1);
}

// Connect to database
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

echo "Initializing admin account...\n";

// Check if admin already exists (by email or ID=1)
$stmt = $pdo->prepare('SELECT id, email FROM users WHERE id = 1 OR email = ?');
$stmt->execute([$adminEmail]);
$existingAdmin = $stmt->fetch(PDO::FETCH_ASSOC);

if ($existingAdmin) {
    echo "Admin account already exists (ID: {$existingAdmin['id']}, Email: {$existingAdmin['email']})\n";
    
    // Ask if user wants to update
    echo "Do you want to update the admin password and display name? (yes/no): ";
    $handle = fopen("php://stdin", "r");
    $line = trim(fgets($handle));
    fclose($handle);
    
    if (strtolower($line) !== 'yes' && strtolower($line) !== 'y') {
        echo "Skipping update.\n";
        exit(0);
    }
    
    // Update existing admin
    $passwordHash = password_hash($adminPassword, PASSWORD_BCRYPT);
    $stmt = $pdo->prepare('UPDATE users SET email = ?, password_hash = ?, display_name = ? WHERE id = ?');
    $stmt->execute([$adminEmail, $passwordHash, $adminDisplayName, $existingAdmin['id']]);
    
    echo "✓ Admin account updated successfully!\n";
    echo "  Email: $adminEmail\n";
    echo "  Display Name: $adminDisplayName\n";
} else {
    // Create new admin account
    $passwordHash = password_hash($adminPassword, PASSWORD_BCRYPT);
    
    // If no users exist, create with ID=1
    $stmt = $pdo->query('SELECT COUNT(*) FROM users');
    $userCount = $stmt->fetchColumn();
    
    if ($userCount == 0) {
        // Force ID=1 for first admin
        $stmt = $pdo->prepare('INSERT INTO users (id, email, password_hash, display_name) VALUES (1, ?, ?, ?)');
        $stmt->execute([$adminEmail, $passwordHash, $adminDisplayName]);
    } else {
        // Let auto-increment handle ID
        $stmt = $pdo->prepare('INSERT INTO users (email, password_hash, display_name) VALUES (?, ?, ?)');
        $stmt->execute([$adminEmail, $passwordHash, $adminDisplayName]);
    }
    
    $adminId = $pdo->lastInsertId() ?: 1;
    
    echo "✓ Admin account created successfully!\n";
    echo "  ID: $adminId\n";
    echo "  Email: $adminEmail\n";
    echo "  Display Name: $adminDisplayName\n";
}

echo "\nYou can now login with these credentials.\n";
