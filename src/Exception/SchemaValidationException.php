<?php

declare(strict_types=1);

namespace Directo\Exception;

/**
 * Exception for XSD schema validation failures.
 *
 * Thrown when response XML doesn't conform to the endpoint's XSD schema.
 * Includes detailed libxml validation errors.
 *
 * Note: Schema validation is optional (disabled by default) due to CPU cost.
 * Enable it for debugging or in non-critical paths.
 */
class SchemaValidationException extends DirectoException
{
    /**
     * @param  string  $message  Error message
     * @param  array<int, \LibXMLError>  $validationErrors  libxml validation errors
     * @param  string  $schemaPath  Path to the XSD schema used
     * @param  array<string, mixed>  $context  Debugging context
     */
    public function __construct(
        string $message,
        protected readonly array $validationErrors = [],
        protected readonly string $schemaPath = '',
        array $context = [],
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $context, 0, $previous);
    }

    /**
     * Get libxml validation errors.
     *
     * @return array<int, \LibXMLError>
     */
    public function getValidationErrors(): array
    {
        return $this->validationErrors;
    }

    /**
     * Get formatted validation error messages.
     *
     * @return array<int, string>
     */
    public function getFormattedErrors(): array
    {
        return array_map(
            fn (\LibXMLError $error): string => sprintf(
                '[%s] Line %d: %s',
                match ($error->level) {
                    LIBXML_ERR_WARNING => 'WARNING',
                    LIBXML_ERR_ERROR => 'ERROR',
                    LIBXML_ERR_FATAL => 'FATAL',
                    default => 'UNKNOWN',
                },
                $error->line,
                trim($error->message),
            ),
            $this->validationErrors,
        );
    }

    /**
     * Get the schema path used for validation.
     */
    public function getSchemaPath(): string
    {
        return $this->schemaPath;
    }
}
