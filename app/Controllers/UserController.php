<?php
namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
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
            Response::json(['success' => false, 'error' => 'User not found'], 404);
        }

        Response::json(['success' => true, 'data' => $profile]);
    }

    public function updateMe(Request $req, array $params): void {
        $user = $req->user;
        
        // For POST multipart/form-data, PHP automatically populates $_POST and $_FILES
        $displayName = $_POST['displayName'] ?? null;
        $avatarFile = $req->files['avatar'] ?? null;

        try {
            $result = $this->service->updateProfile($user['id'], $displayName, $avatarFile);
            Response::json(['success' => true, 'data' => $result]);
        } catch (\RuntimeException $e) {
            $code = $e->getCode() ?: 500;
            Response::json(['success' => false, 'error' => $e->getMessage()], $code);
        } catch (\Throwable $e) {
            Response::json(['success' => false, 'error' => 'Internal Server Error'], 500);
        }
    }
}
?>
