<?php
namespace App\Exceptions;

/**
 * Exception for forbidden access (403 Forbidden)
 */
class ForbiddenException extends AppException {
    protected int $statusCode = 403;

    public function __construct(string $message = "Forbidden") {
        parent::__construct($message);
    }
}
?>
