<?php
namespace App\Controllers;

use App\Core\Request;
use App\Core\ApiResponse;
use App\Exceptions\NotFoundException;
use App\Services\UserService;

class UserController {
    private UserService $service;

    public function __construct() {
        $this->service = new UserService();
    }

    public function show(Request $req, array $params): void {
        $email = $params['email'] ?? '';
        $profile = $this->service->getProfile($email);
        
        if (!$profile) {
            throw new NotFoundException('User not found');
        }

        ApiResponse::success($profile);
    }

    public function updateMe(Request $req, array $params): void {
        $user = $req->user;
        
        // For POST multipart/form-data, PHP automatically populates $_POST and $_FILES
        $displayName = $_POST['displayName'] ?? null;
        $avatarFile = $req->files['avatar'] ?? null;

        $result = $this->service->updateProfile($user['id'], $displayName, $avatarFile);
        ApiResponse::success($result);
    }   
}
?>
