<?php
// 快速测试更新和游戏API的问题

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../app/Core/Database.php';
require_once __DIR__ . '/../app/Models/UserMovie.php';
require_once __DIR__ . '/../app/Models/UserGame.php';

$config = config();
\App\Core\Database::init($config['db']);

echo "测试1: 更新电影\n";
try {
    $updateData = ['my_rating' => 10.0];
    \App\Models\UserMovie::update(1, $updateData);
    echo "✅ 更新成功\n";
} catch (\Throwable $e) {
    echo "❌ 错误: " . $e->getMessage() . "\n";
    echo "文件: " . $e->getFile() . ":" . $e->getLine() . "\n";
}

echo "\n测试2: 添加游戏\n";
try {
    $gameData = [
        'user_id' => 1,
        'rawg_id' => 9999,
        'my_rating' => 10.0,
        'platform' => 'Test'
    ];
    $id = \App\Models\UserGame::create($gameData);
    echo "✅ 添加成功, ID: $id\n";
} catch (\Throwable $e) {
    echo "❌ 错误: " . $e->getMessage() . "\n";
    echo "文件: " . $e->getFile() . ":" . $e->getLine() . "\n";
}
?>
