<?php
namespace App\Models;

use App\Core\QueryBuilder;

class PostVideo {
    /**
     * Add a video to a post
     */
    public static function add(
        int $postId, 
        string $filePath, 
        ?string $thumbnailPath = null,
        ?int $duration = null,
        ?int $fileSize = null,
        ?string $mimeType = null
    ): int {
        return QueryBuilder::table('post_videos')->insert([
            'post_id' => $postId,
            'file_path' => $filePath,
            'thumbnail_path' => $thumbnailPath,
            'duration' => $duration,
            'file_size' => $fileSize,
            'mime_type' => $mimeType,
        ]);
    }

    /**
     * Get all videos for a post
     */
    public static function listByPost(int $postId): array {
        return QueryBuilder::table('post_videos')
            ->where('post_id', '=', $postId)
            ->get();
    }

    /**
     * Delete all videos for a post (called when post is deleted)
     */
    public static function deleteByPost(int $postId): void {
        QueryBuilder::table('post_videos')
            ->where('post_id', '=', $postId)
            ->delete();
    }

    public static function getById(int $id): ?array {
        return QueryBuilder::table('post_videos')
            ->where('id', '=', $id)
            ->first();
    }

    public static function delete(int $id): void {
        QueryBuilder::table('post_videos')
            ->where('id', '=', $id)
            ->delete();
    }
}
?>
