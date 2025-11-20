<?php
namespace App\Models;

use App\Core\QueryBuilder;

class User {
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

    public static function create(string $email, string $passwordHash): int {
        return QueryBuilder::table('users')->insert([
            'email' => $email,
            'password_hash' => $passwordHash
        ]);
    }

    public static function updateProfile(int $id, array $data): void {
        QueryBuilder::table('users')
            ->where('id', '=', $id)
            ->update($data);
    }
}
?>