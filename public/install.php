<?php
require_once __DIR__ . '/_tool_guard.php';
/**
 * P-Weibo Backend Installation Script
 * This script handles the installation process including:
 * - Database connection testing
 * - Creating .env file
 * - Running database migrations
 * - Creating admin user
 */

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['action']) || $_POST['action'] !== 'install') {
    // Redirect to install.html if accessed directly
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        header('Location: install.html');
        exit;
    }
    echo json_encode(['success' => false, 'error' => 'Invalid request']);
    exit;
}

try {
    $root = dirname(__DIR__);
    
    // Check if already installed
    if (file_exists($root . '/.installed')) {
        echo json_encode(['success' => false, 'error' => '系統已安裝。如需重新安裝，請刪除根目錄下的 .installed 文件']);
        exit;
    }
    
    // Validate input
    $dbHost = $_POST['db_host'] ?? '';
    $dbName = $_POST['db_name'] ?? '';
    $dbUser = $_POST['db_user'] ?? '';
    $dbPass = $_POST['db_pass'] ?? '';
    $adminEmail = $_POST['admin_email'] ?? '';
    $adminPass = $_POST['admin_pass'] ?? '';
    $adminName = $_POST['admin_name'] ?? '';
    
    if (empty($dbHost) || empty($dbName) || empty($dbUser) || empty($adminEmail) || empty($adminPass) || empty($adminName)) {
        throw new Exception('請填寫所有必填字段');
    }
    
    if (strlen($adminPass) < 8) {
        throw new Exception('管理員密碼至少需要 8 個字符');
    }
    
    // Step 1: Test database connection
    try {
        $dsn = "mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4";
        $pdo = new PDO($dsn, $dbUser, $dbPass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);
    } catch (PDOException $e) {
        throw new Exception('數據庫連接失敗: ' . $e->getMessage());
    }
    
    // Step 2: Generate JWT secrets
    $jwtAccessSecret = bin2hex(random_bytes(32));
    $jwtRefreshSecret = bin2hex(random_bytes(32));
    
    // Step 3: Create .env file
    $envContent = <<<ENV
APP_ENV=production
APP_URL=https://{$_SERVER['HTTP_HOST']}
FRONTEND_ORIGIN=https://{$_SERVER['HTTP_HOST']}

# Database Configuration
DB_HOST={$dbHost}
DB_PORT=3306
DB_NAME={$dbName}
DB_USER={$dbUser}
DB_PASS={$dbPass}
DB_CHARSET=utf8mb4

# JWT Configuration (Auto-generated)
JWT_ACCESS_SECRET={$jwtAccessSecret}
JWT_REFRESH_SECRET={$jwtRefreshSecret}
JWT_ACCESS_TTL=900
JWT_REFRESH_TTL=1209600

# Upload Configuration
UPLOAD_PATH={$root}/public/uploads
MAX_IMAGE_MB=10
MAX_VIDEO_MB=100

# Log Configuration
LOG_PATH={$root}/logs

# Admin Account Configuration
ADMIN_EMAIL={$adminEmail}
ADMIN_PASSWORD={$adminPass}
ADMIN_DISPLAY_NAME={$adminName}
ENV;
    
    if (file_put_contents($root . '/.env', $envContent) === false) {
        throw new Exception('無法創建 .env 文件，請檢查文件權限');
    }
    
    // Step 4: Create necessary directories
    $directories = [
        $root . '/public/uploads',
        $root . '/public/uploads/avatars',
        $root . '/logs'
    ];
    
    foreach ($directories as $dir) {
        if (!file_exists($dir)) {
            if (!mkdir($dir, 0755, true)) {
                throw new Exception("無法創建目錄: {$dir}");
            }
        }
    }
    
    // Step 5: Run database migrations
    $migrations = [
        $root . '/migrations/schema.sql'
    ];
    
    foreach ($migrations as $migrationFile) {
        if (!file_exists($migrationFile)) {
            throw new Exception("找不到遷移文件: " . basename($migrationFile));
        }
        
        $sql = file_get_contents($migrationFile);
        if ($sql === false) {
            throw new Exception("無法讀取遷移文件: " . basename($migrationFile));
        }
        
        // Execute SQL
        try {
            $pdo->exec($sql);
        } catch (PDOException $e) {
            // Ignore duplicate column errors (in case of re-installation)
            if (strpos($e->getMessage(), 'Duplicate column') === false && 
                strpos($e->getMessage(), 'already exists') === false) {
                throw new Exception("執行遷移失敗 (" . basename($migrationFile) . "): " . $e->getMessage());
            }
        }
    }
    
    // Step 6: Create admin user
    $hashedPassword = password_hash($adminPass, PASSWORD_BCRYPT);
    
    // Check if admin already exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$adminEmail]);
    $existingUser = $stmt->fetch();
    
    if ($existingUser) {
        // Update existing user to admin
        $stmt = $pdo->prepare("UPDATE users SET password_hash = ?, display_name = ?, role = 'admin' WHERE email = ?");
        $stmt->execute([$hashedPassword, $adminName, $adminEmail]);
    } else {
        // Create new admin user
        $username = explode('@', $adminEmail)[0];
        $stmt = $pdo->prepare("INSERT INTO users (email, password_hash, username, display_name, role) VALUES (?, ?, ?, ?, 'admin')");
        $stmt->execute([$adminEmail, $hashedPassword, $username, $adminName]);
    }
    
    // Step 7: Mark installation as complete
    file_put_contents($root . '/.installed', date('Y-m-d H:i:s'));
    
    echo json_encode([
        'success' => true,
        'message' => '安裝成功！',
        'admin_email' => $adminEmail
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
