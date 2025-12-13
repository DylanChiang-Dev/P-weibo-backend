<?php
namespace App\Exceptions;

/**
 * Exception for conflict errors (409 Conflict)
 */
class ConflictException extends AppException {
    protected int $statusCode = 409;

    public function __construct(string $message = "Conflict", ?array $details = null) {
        parent::__construct($message, 0, $details);
    }
}
?>

