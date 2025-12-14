<?php
namespace App\Controllers;

use App\Core\ApiResponse;
use App\Core\Request;
use App\Exceptions\ValidationException;
use App\Services\IntegrationService;

class IntegrationController {
    private IntegrationService $service;

    public function __construct() {
        $this->service = new IntegrationService();
    }

    public function status(Request $req): void {
        $userId = (int)($req->user['id'] ?? 0);
        ApiResponse::success($this->service->getStatus($userId));
    }

    public function save(Request $req): void {
        $userId = (int)($req->user['id'] ?? 0);
        $payload = is_array($req->body) ? $req->body : [];
        if (!is_array($payload)) {
            throw new ValidationException('Bad Request');
        }
        $this->service->saveCredentials($userId, $payload);
        ApiResponse::success();
    }
}
?>

