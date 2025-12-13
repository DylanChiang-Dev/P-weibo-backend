<?php
namespace App\Services;

use App\Models\User;
use App\Core\QueryBuilder;
use App\Exceptions\NotFoundException;

class UserService {
    public function getProfile(string $email): ?array {
        $user = User::findByEmail($email);
        if (!$user) return null;

        // Get stats
        $postCount = QueryBuilder::table('posts')
            ->where('user_id', '=', $user['id'])
            ->where('is_deleted', '=', 0)
            ->count();

        return [
            'id' => $user['id'],
            'email' => $user['email'],
            'display_name' => $user['display_name'],
            'avatar_path' => $user['avatar_path'],
            'created_at' => $user['created_at'],
            'stats' => [
                'posts' => $postCount
            ]
        ];
    }

    public function updateProfile(int $userId, ?string $displayName, ?array $avatarFile): array {
        $user = User::findById($userId);
        if (!$user) {
            throw new NotFoundException('User not found');
        }

        $updateData = [];
        
        if ($displayName !== null) {
            $updateData['display_name'] = $displayName;
        }

        if ($avatarFile && $avatarFile['error'] === UPLOAD_ERR_OK) {
            $config = \config();
            $uploadPath = $config['upload']['path'] . '/avatars';
            
            if (!is_dir($uploadPath)) {
                mkdir($uploadPath, 0755, true);
            }

            $ext = pathinfo($avatarFile['name'], PATHINFO_EXTENSION);
            $filename = 'avatar_' . $userId . '_' . time() . '.' . $ext;
            $destination = $uploadPath . '/' . $filename;

            if (move_uploaded_file($avatarFile['tmp_name'], $destination)) {
                $updateData['avatar_path'] = '/uploads/avatars/' . $filename;
            }
        }

        if (!empty($updateData)) {
            User::updateProfile($userId, $updateData);
        }

        $updatedUser = User::findById($userId);
        return [
            'id' => (int)$updatedUser['id'],
            'email' => $updatedUser['email'],
            'displayName' => $updatedUser['display_name'],
            'avatar' => $updatedUser['avatar_path']
        ];
    }
}
?>
