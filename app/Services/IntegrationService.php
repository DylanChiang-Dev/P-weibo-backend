<?php
namespace App\Services;

use App\Core\Crypto;
use App\Exceptions\ValidationException;
use App\Models\UserIntegration;

class IntegrationService {
    private array $allowedKeys = [
        'tmdb_api_key',
        'rawg_api_key',
        'google_books_api_key',
        'igdb_client_id',
        'igdb_client_secret',
    ];

    public function getStatus(int $userId): array {
        $creds = $this->getCredentials($userId);
        return [
            'tmdb' => ['configured' => !empty($creds['tmdb_api_key'] ?? '')],
            'rawg' => ['configured' => !empty($creds['rawg_api_key'] ?? '')],
            'google_books' => ['configured' => !empty($creds['google_books_api_key'] ?? '')],
            'igdb' => ['configured' => !empty($creds['igdb_client_id'] ?? '') && !empty($creds['igdb_client_secret'] ?? '')],
        ];
    }

    public function saveCredentials(int $userId, array $payload): void {
        $data = array_intersect_key($payload, array_flip($this->allowedKeys));

        // Normalize to strings and trim; never store nulls.
        foreach ($data as $k => $v) {
            if ($v === null) {
                unset($data[$k]);
                continue;
            }
            $data[$k] = trim((string)$v);
        }

        // Basic validation
        if (isset($data['igdb_client_id']) && $data['igdb_client_id'] === '') {
            throw new ValidationException('igdb_client_id cannot be empty');
        }
        if (isset($data['igdb_client_secret']) && $data['igdb_client_secret'] === '') {
            throw new ValidationException('igdb_client_secret cannot be empty');
        }

        $enc = Crypto::encryptJson($data);
        UserIntegration::upsertEncrypted($userId, $enc);
    }

    public function getCredentials(int $userId): array {
        $row = UserIntegration::getByUserId($userId);
        if (!$row) return [];
        $enc = (string)($row['credentials_enc'] ?? '');
        if ($enc === '') return [];
        return Crypto::decryptJson($enc) ?? [];
    }
}
?>

