<?php
namespace App\Models;

use App\Core\QueryBuilder;

class User {
    public const ROLE_ADMIN = 'admin';
    public const ROLE_USER = 'user';

    public static function findByEmail(string $email): ?array {
        return QueryBuilder::table('users')
            ->where('email', '=', $email)
            ->first();
    }

    public static function findById(int $id): ?array {
        return QueryBuilder::table('users')
            ->where('id', '=', $id)
            ->first();
    }

    public static function create(string $email, string $passwordHash, string $role = self::ROLE_USER): int {
        return QueryBuilder::table('users')->insert([
            'email' => $email,
            'password_hash' => $passwordHash,
            'role' => $role
        ]);
    }

    public static function updateProfile(int $id, array $data): void {
        QueryBuilder::table('users')
            ->where('id', '=', $id)
            ->update($data);
    }

    public static function isAdmin(array $user): bool {
        return ($user['role'] ?? self::ROLE_USER) === self::ROLE_ADMIN;
    }
}
?>