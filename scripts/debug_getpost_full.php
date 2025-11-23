<?php
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/app/Core/Database.php';
require_once dirname(__DIR__) . '/app/Core/QueryBuilder.php';
require_once dirname(__DIR__) . '/app/Core/Logger.php';
require_once dirname(__DIR__) . '/app/Models/Post.php';
require_once dirname(__DIR__) . '/app/Models/PostImage.php';
require_once dirname(__DIR__) . '/app/Models/PostVideo.php';
require_once dirname(__DIR__) . '/app/Models/Like.php';
require_once dirname(__DIR__) . '/app/Models/Comment.php';
require_once dirname(__DIR__) . '/app/Services/PostService.php';
require_once dirname(__DIR__) . '/app/Services/ImageService.php';
require_once dirname(__DIR__) . '/app/Services/MediaService.php';

use App\Core\Database;
use App\Models\Post;
use App\Services\PostService;

$config = config();
Database::init($config['db']);

$postId = 288;
$currentUserId = 1;

echo "=== Testing PostService::getPost ===\n\n";
echo "Post ID: $postId\n";
echo "Current User ID: $currentUserId\n\n";

// Step 1: Test Post::getById
echo "Step 1: Testing Post::getById\n";
$row = Post::getById($postId);
if (!$row) {
    echo "ERROR: Post::getById returned null\n";
    exit(1);
}
echo "SUCCESS: Post retrieved\n";
echo " - id: " . $row['id'] . "\n";
echo " - user_id: " . $row['user_id'] . "\n";
echo " - visibility: " . $row['visibility'] . "\n\n";

// Step 2: Test privacy check
echo "Step 2: Testing privacy check\n";
if ($row['visibility'] === 'private') {
    echo "Post is PRIVATE\n";
    if (!$currentUserId || (int)$row['user_id'] !== (int)$currentUserId) {
        echo "ERROR: Privacy check would deny access\n";
        echo " - currentUserId: $currentUserId\n";
        echo " - post u ser_id: " . $row['user_id'] . "\n";
        echo " - Match: " . ((int)$row['user_id'] === (int)$currentUserId ? 'YES' : 'NO') . "\n";
        exit(1);
    } else {
        echo "SUCCESS: Privacy check would allow access\n";
    }
} else {
    echo "Post is PUBLIC\n";
}

// Step 3: Test full PostService::getPost
echo "\nStep 3: Testing PostService::getPost\n";
$postService = new PostService($config['upload']);
$result = $postService->getPost($postId, $currentUserId);

if (!$result) {
    echo "ERROR: PostService::getPost returned null\n";
    exit(1);
}

echo "SUCCESS: PostService::getPost returned data\n";
echo " - id: " . $result['id'] . "\n";
echo " - visibility: " . $result['visibility'] . "\n";
echo " - content: " . substr($result['content'], 0, 30) . "...\n";

echo "\n=== ALL TESTS PASSED ===\n";
