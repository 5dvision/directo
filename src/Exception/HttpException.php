<?php

declare(strict_types=1);

namespace Directo\Exception;

/**
 * Exception for HTTP-level errors (non-2xx responses).
 *
 * Thrown when the server returns an error status code.
 * The HTTP status code is available via getCode().
 */
class HttpException extends DirectoException
{
    /**
     * Create a new HTTP exception.
     *
     * @param  string  $message  Error message
     * @param  int  $statusCode  HTTP status code
     * @param  string  $responseBody  Raw response body
     * @param  array<string, mixed>  $context  Debugging context
     * @param  \Throwable|null  $previous  Previous exception
     */
    public function __construct(
        string $message,
        protected readonly int $statusCode,
        protected readonly string $responseBody = '',
        array $context = [],
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $context, $statusCode, $previous);
    }

    /**
     * Get the HTTP status code.
     *
     * @return int HTTP status code
     */
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * Get the raw response body (may be empty).
     *
     * @return string Response body
     */
    public function getResponseBody(): string
    {
        return $this->responseBody;
    }
}
