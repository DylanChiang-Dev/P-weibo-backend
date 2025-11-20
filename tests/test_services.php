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
use App\Core\Auth;
use App\Services\AuthService;
use App\Services\PostService;
use App\Services\TokenService;

// Init
Database::init($config['db']);
Auth::init($config['jwt'], $config['app_url']);
TokenService::init($config['jwt']);

echo "Running Service Integration Tests...\n";

$authService = new AuthService();
$postService = new PostService($config['upload']);

$testUser = 'svc_test_' . time();
$testEmail = $testUser . '@example.com';
$testPass = 'password123';

// 1. Auth Register
echo "Test 1: Auth Register... ";
try {
    $user = $authService->register($testUser, $testEmail, $testPass);
    if ($user['username'] === $testUser) {
        echo "PASS (ID: {$user['id']})\n";
    } else {
        echo "FAIL\n";
        exit(1);
    }
} catch (\Throwable $e) {
    echo "FAIL (" . $e->getMessage() . ")\n";
    exit(1);
}

$userId = (int)$user['id'];

// 2. Auth Login
echo "Test 2: Auth Login... ";
try {
    $tokens = $authService->login($testUser, $testPass, 'test-agent', '127.0.0.1');
    if (isset($tokens['access_token'])) {
        echo "PASS\n";
    } else {
        echo "FAIL\n";
        exit(1);
    }
} catch (\Throwable $e) {
    echo "FAIL (" . $e->getMessage() . ")\n";
    exit(1);
}

// 3. Post Create
echo "Test 3: Post Create... ";
try {
    $postId = $postService->createPost($userId, 'Hello World from Service Test');
    if ($postId > 0) {
        echo "PASS (ID: $postId)\n";
    } else {
        echo "FAIL\n";
        exit(1);
    }
} catch (\Throwable $e) {
    echo "FAIL (" . $e->getMessage() . ")\n";
    exit(1);
}

// 4. Post List
echo "Test 4: Post List... ";
$list = $postService->getPosts(10, null);
if (count($list['items']) > 0 && $list['items'][0]['id'] === $postId) {
    echo "PASS\n";
} else {
    echo "FAIL\n";
    print_r($list);
    exit(1);
}

// 5. Like
echo "Test 5: Like Post... ";
$cnt = $postService->likePost($userId, $postId);
if ($cnt === 1) {
    echo "PASS\n";
} else {
    echo "FAIL (Count: $cnt)\n";
    exit(1);
}

// 6. Comment
echo "Test 6: Comment Post... ";
$postService->commentPost($userId, $postId, 'Nice post!');
$post = $postService->getPost($postId);
if ($post['comment_count'] === 1) {
    echo "PASS\n";
} else {
    echo "FAIL\n";
    exit(1);
}

// 7. User Profile
echo "Test 7: User Profile... ";
$userService = new \App\Services\UserService();
$profile = $userService->getProfile($testUser);
if ($profile && $profile['username'] === $testUser && isset($profile['stats'])) {
    echo "PASS\n";
} else {
    echo "FAIL\n";
    print_r($profile);
    exit(1);
}

// 8. Delete Post
echo "Test 8: Delete Post... ";
try {
    $postService->deletePost($userId, $postId);
    $deletedPost = $postService->getPost($postId);
    if ($deletedPost === null) {
        echo "PASS\n";
    } else {
        echo "FAIL (Post still exists)\n";
        exit(1);
    }
} catch (\Throwable $e) {
    echo "FAIL (" . $e->getMessage() . ")\n";
    exit(1);
}

// Cleanup
Database::execute('DELETE FROM comments WHERE post_id = ?', [$postId]);
Database::execute('DELETE FROM likes WHERE post_id = ?', [$postId]);
Database::execute('DELETE FROM posts WHERE id = ?', [$postId]);
Database::execute('DELETE FROM users WHERE id = ?', [$userId]);
echo "Cleanup... DONE\n";

echo "All Service Tests Passed!\n";
