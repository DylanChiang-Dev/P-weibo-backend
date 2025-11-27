<?php
namespace App\Models;

use App\Core\QueryBuilder;

class BlogTag {
    /**
     * Get all tags
     */
    public static function getAll(): array {
        return QueryBuilder::table('blog_tags')
            ->orderBy('name', 'ASC')
            ->get();
    }

    /**
     * Get tag by ID
     */
    public static function getById(int $id): ?array {
        return QueryBuilder::table('blog_tags')
            ->where('id', '=', $id)
            ->first();
    }

    /**
     * Get tag by slug
     */
    public static function getBySlug(string $slug): ?array {
        return QueryBuilder::table('blog_tags')
            ->where('slug', '=', $slug)
            ->first();
    }

    /**
     * Create a new tag
     */
    public static function create(string $name, string $slug): int {
        return QueryBuilder::table('blog_tags')->insert([
            'name' => $name,
            'slug' => $slug
        ]);
    }

    /**
     * Find or create tag by name
     */
    public static function findOrCreate(string $name): int {
        $slug = self::generateSlug($name);
        
        $existing = self::getBySlug($slug);
        if ($existing) {
            return (int)$existing['id'];
        }

        return self::create($name, $slug);
    }

    /**
     * Generate slug from name
     */
    public static function generateSlug(string $name): string {
        $slug = strtolower($name);
        $slug = preg_replace('/[^a-z0-9\x{4e00}-\x{9fa5}]+/u', '-', $slug);
        $slug = trim($slug, '-');
        return $slug;
    }

    /**
     * Check if slug exists
     */
    public static function existsBySlug(string $slug): bool {
        $result = QueryBuilder::table('blog_tags')
            ->where('slug', '=', $slug)
            ->first();
        return $result !== null;
    }
}
?>
