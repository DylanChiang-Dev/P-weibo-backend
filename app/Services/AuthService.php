<?php
namespace App\Services;

use App\Models\User;
use App\Core\Logger;

class AuthService {
    public function register(string $email, string $password): array {
        $exists = User::findByEmail($email);
        if ($exists) {
            throw new \RuntimeException('Email already registered', 409);
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);
        try {
            $id = User::create($email, $hash);
        } catch (\Throwable $e) {
            Logger::error('register_failed', ['message' => $e->getMessage()]);
            throw new \RuntimeException('Internal Server Error', 500);
        }

        return ['id' => $id, 'email' => $email];
    }

    public function login(string $email, string $password, string $ua, string $ip): array {
        $user = User::findByEmail($email);
        if (!$user || !password_verify($password, $user['password_hash'])) {
            Logger::warn('login_failed', ['email' => $email]);
            throw new \RuntimeException('Invalid credentials', 401);
        }

        return TokenService::issueTokens((int)$user['id'], $ua, $ip);
    }

    public function refresh(string $refreshToken): array {
        return TokenService::refresh($refreshToken);
    }

    public function logout(string $refreshToken): void {
        TokenService::revoke($refreshToken);
    }
}
