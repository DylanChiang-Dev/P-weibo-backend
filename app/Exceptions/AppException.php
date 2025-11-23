<?php
namespace App\Exceptions;

use Exception;

/**
 * Base exception for all application exceptions
 * All custom exceptions should extend this class
 */
abstract class AppException extends Exception {
    protected int $statusCode = 500;
    protected ?array $errorDetails = null;

    public function __construct(
        string $message = "",
        int $code = 0,
        ?array $details = null,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
        $this->errorDetails = $details;
    }

    public function getStatusCode(): int {
        return $this->statusCode;
    }

    public function getErrorDetails(): ?array {
        return $this->errorDetails;
    }
}
?>
