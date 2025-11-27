<?php
namespace App\Controllers;

use App\Core\Request;
use App\Core\ApiResponse;
use App\Core\Validator;
use App\Exceptions\ValidationException;
use App\Models\BlogCategory;

class BlogCategoryController {
    /**
     * Get all categories
     */
    public function list(Request $req): void {
        $categories = BlogCategory::getAll();
        ApiResponse::success($categories);
    }

    /**
     * Create a new category (Admin only)
     */
    public function create(Request $req): void {
        $data = is_array($req->body) ? $req->body : [];
        
        $errs = Validator::required($data, ['name', 'slug']);
        if (!empty($errs)) throw new ValidationException('Bad Request', $errs);

        // Check if slug already exists
        if (BlogCategory::existsBySlug($data['slug'])) {
            throw new ValidationException('Slug already exists');
        }

        $id = BlogCategory::create(
            $data['name'],
            $data['slug'],
            $data['description'] ?? null
        );

        ApiResponse::success(['id' => $id], 201);
    }
}
?>
