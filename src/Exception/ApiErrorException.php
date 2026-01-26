<?php

declare(strict_types=1);

namespace Directo\Exception;

/**
 * Exception thrown when Directo API returns an error response.
 *
 * Directo XMLCore API returns errors in various XML formats:
 * - <error>Error message</error>
 * - <results><error>...</error></results>
 * - <results error="1" message="..."/>
 * - <results><errors><error>...</error></errors></results>
 *
 * This exception is thrown when such error responses are detected,
 * even though HTTP status code may be 200 OK.
 */
final class ApiErrorException extends DirectoException
{
    /**
     * @param  string  $message  Primary error message
     * @param  array<int, string>  $errors  All error messages extracted
     * @param  string  $rawXml  The raw XML error response
     * @param  array<string, mixed>  $context  Debugging context
     */
    public function __construct(
        string $message,
        private readonly array $errors,
        private readonly string $rawXml,
        array $context = [],
    ) {
        parent::__construct($message, $context);
    }

    /**
     * Get all error messages extracted from the response.
     *
     * @return array<int, string>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Get the raw XML error response.
     */
    public function getRawXml(): string
    {
        return $this->rawXml;
    }

    /**
     * Check if there are multiple errors.
     */
    public function hasMultipleErrors(): bool
    {
        return count($this->errors) > 1;
    }

    /**
     * Get errors as a formatted string list.
     *
     * @return array<int, string> Formatted error strings
     */
    public function getFormattedErrors(): array
    {
        return array_map(
            fn (int $i, string $error): string => sprintf('[%d] %s', $i + 1, $error),
            array_keys($this->errors),
            $this->errors,
        );
    }
}
