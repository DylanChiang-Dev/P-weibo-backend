<?php
namespace App\Exceptions;

/**
 * Exception for resource not found errors (404 Not Found)
 */
class NotFoundException extends AppException {
    protected int $statusCode = 404;

    public function __construct(string $message = "Resource not found") {
        parent::__construct($message);
    }
}
?>
