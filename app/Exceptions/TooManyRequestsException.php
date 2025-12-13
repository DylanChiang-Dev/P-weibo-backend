<?php
namespace App\Exceptions;

/**
 * Exception for rate limit errors (429 Too Many Requests)
 */
class TooManyRequestsException extends AppException {
    protected int $statusCode = 429;

    public function __construct(string $message = "Too many requests", ?array $details = null) {
        parent::__construct($message, 0, $details);
    }
}
?>

