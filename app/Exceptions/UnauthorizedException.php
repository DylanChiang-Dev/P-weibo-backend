<?php
namespace App\Exceptions;

/**
 * Exception for unauthorized access (401 Unauthorized)
 */
class UnauthorizedException extends AppException {
    protected int $statusCode = 401;

    public function __construct(string $message = "Unauthorized") {
        parent::__construct($message);
    }
}
?>
