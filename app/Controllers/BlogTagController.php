<?php
namespace App\Controllers;

use App\Core\Request;
use App\Core\ApiResponse;
use App\Core\Validator;
use App\Exceptions\ValidationException;
use App\Models\BlogTag;

class BlogTagController {
    /**
     * Get all tags
     */
    public function list(Request $req): void {
        $tags = BlogTag::getAll();
        ApiResponse::success($tags);
    }

    /**
     * Create a new tag (Admin only)
     */
    public function create(Request $req): void {
        $data = is_array($req->body) ? $req->body : [];
        
        $errs = Validator::required($data, ['name']);
        if (!empty($errs)) throw new ValidationException('Bad Request', $errs);

        $slug = $data['slug'] ?? BlogTag::generateSlug($data['name']);

        // Check if slug already exists
        if (BlogTag::existsBySlug($slug)) {
            throw new ValidationException('Tag already exists');
        }

        $id = BlogTag::create($data['name'], $slug);

        ApiResponse::success(['id' => $id], 201);
    }
}
?>
