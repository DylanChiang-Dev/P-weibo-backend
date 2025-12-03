<?php
namespace App\Models;

use App\Core\QueryBuilder;

class UserBook {
    /**
     * Create a new book record
     */
    public static function create(array $data): int {
        return QueryBuilder::table('user_books')->insert($data);
    }
    
    /**
     * Get book by ID
     */
    public static function getById(int $id): ?array {
        return QueryBuilder::table('user_books')
            ->where('id', '=', $id)
            ->first();
    }
    
    /**
     * List books for a user
     */
    public static function list(int $userId, ?string $status = null, int $limit = 20, int $offset = 0): array {
        $query = QueryBuilder::table('user_books')
            ->where('user_id', '=', $userId);
        
        if ($status) {
            $query->where('status', '=', $status);
        }
        
        return $query->orderBy('completed_date', 'DESC')
            ->orderBy('created_at', 'DESC')
            ->limit($limit)
            ->offset($offset)
            ->get();
    }
    
    /**
     * Count total books for a user
     */
    public static function count(int $userId, ?string $status = null): int {
        $query = QueryBuilder::table('user_books')
            ->where('user_id', '=', $userId);
        
        if ($status) {
            $query->where('status', '=', $status);
        }
        
        return $query->count();
    }
    
    /**
     * Update book record
     */
    public static function update(int $id, array $data): void {
        QueryBuilder::table('user_books')
            ->where('id', '=', $id)
            ->update($data);
    }
    
    /**
     * Delete book record
     */
    public static function delete(int $id): void {
        QueryBuilder::table('user_books')
            ->where('id', '=', $id)
            ->delete();
    }
    
    /**
     * Check if user already has this book (by Google Books ID or ISBN)
     */
    public static function exists(int $userId, ?string $googleBooksId, ?string $isbn): bool {
        $query = QueryBuilder::table('user_books')
            ->where('user_id', '=', $userId);
        
        if ($googleBooksId) {
            $query->where('google_books_id', '=', $googleBooksId);
        } elseif ($isbn) {
            $query->where('isbn', '=', $isbn);
        } else {
            return false;
        }
        
        $result = $query->first();
        return $result !== null;
    }
}
?>
