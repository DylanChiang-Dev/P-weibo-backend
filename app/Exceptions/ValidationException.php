<?php
namespace App\Exceptions;

/**
 * Exception for validation errors (400 Bad Request)
 */
class ValidationException extends AppException {
    protected int $statusCode = 400;

    public function __construct(string $message = "Validation failed", ?array $details = null) {
        parent::__construct($message, 0, $details);
    }
}
?>
