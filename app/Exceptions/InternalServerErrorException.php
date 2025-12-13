<?php
namespace App\Exceptions;

/**
 * Exception for internal server errors (500 Internal Server Error)
 * Keep message generic to avoid leaking internals.
 */
class InternalServerErrorException extends AppException {
    protected int $statusCode = 500;

    public function __construct(string $message = "Internal Server Error", ?array $details = null) {
        parent::__construct($message, 0, $details);
    }
}
?>

