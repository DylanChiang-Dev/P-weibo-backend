<?php
namespace App\Models;

use App\Core\QueryBuilder;

class BlogCategory {
    /**
     * Get all categories
     */
    public static function getAll(): array {
        return QueryBuilder::table('blog_categories')
            ->orderBy('name', 'ASC')
            ->get();
    }

    /**
     * Get category by ID
     */
    public static function getById(int $id): ?array {
        return QueryBuilder::table('blog_categories')
            ->where('id', '=', $id)
            ->first();
    }

    /**
     * Get category by slug
     */
    public static function getBySlug(string $slug): ?array {
        return QueryBuilder::table('blog_categories')
            ->where('slug', '=', $slug)
            ->first();
    }

    /**
     * Create a new category
     */
    public static function create(string $name, string $slug, ?string $description = null): int {
        return QueryBuilder::table('blog_categories')->insert([
            'name' => $name,
            'slug' => $slug,
            'description' => $description
        ]);
    }

    /**
     * Check if slug exists
     */
    public static function existsBySlug(string $slug): bool {
        $result = QueryBuilder::table('blog_categories')
            ->where('slug', '=', $slug)
            ->first();
        return $result !== null;
    }
}
?>
