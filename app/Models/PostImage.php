<?php
namespace App\Models;

use App\Core\QueryBuilder;

class PostImage {
    public static function add(int $postId, string $filePath, int $width, int $height): int {
        return QueryBuilder::table('post_images')->insert([
            'post_id' => $postId,
            'file_path' => $filePath,
            'width' => $width,
            'height' => $height
        ]);
    }

    public static function listByPost(int $postId): array {
        return QueryBuilder::table('post_images')
            ->where('post_id', '=', $postId)
            ->get();
    }

    public static function getById(int $id): ?array {
        return QueryBuilder::table('post_images')
            ->where('id', '=', $id)
            ->first();
    }

    public static function delete(int $id): void {
        QueryBuilder::table('post_images')
            ->where('id', '=', $id)
            ->delete();
    }
}
?>