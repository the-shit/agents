<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;

class ContainerDaemonException extends Exception
{
    public function __construct(
        string $message,
        public readonly int $statusCode = 0,
        public readonly ?array $response = null,
    ) {
        parent::__construct($message, $statusCode);
    }

    public static function connectionFailed(string $url): self
    {
        return new self("Failed to connect to container daemon at {$url}", 0);
    }

    public static function authenticationFailed(): self
    {
        return new self('Authentication failed - invalid or missing token', 401);
    }

    public static function notFound(string $containerId): self
    {
        return new self("Container not found: {$containerId}", 404);
    }

    public static function fromResponse(int $statusCode, array $response): self
    {
        $message = $response['error'] ?? $response['message'] ?? 'Unknown error';

        return new self($message, $statusCode, $response);
    }

    public function isConnectionError(): bool
    {
        return $this->statusCode === 0;
    }

    public function isAuthError(): bool
    {
        return $this->statusCode === 401;
    }

    public function isNotFound(): bool
    {
        return $this->statusCode === 404;
    }
}
