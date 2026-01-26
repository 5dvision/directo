<?php

declare(strict_types=1);

namespace Directo\Exception;

/**
 * Exception for XML parsing errors.
 *
 * Thrown when the response body is not valid XML.
 * Includes libxml errors for debugging.
 */
class XmlParseException extends DirectoException
{
    /**
     * Create a new XML parse exception.
     *
     * @param  string  $message  Error message
     * @param  array<int, \LibXMLError>  $xmlErrors  libxml error objects
     * @param  string  $rawXml  The raw XML that failed to parse
     * @param  array<string, mixed>  $context  Debugging context
     * @param  \Throwable|null  $previous  Previous exception
     */
    public function __construct(
        string $message,
        protected readonly array $xmlErrors = [],
        protected readonly string $rawXml = '',
        array $context = [],
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $context, 0, $previous);
    }

    /**
     * Get libxml errors.
     *
     * @return array<int, \LibXMLError>
     */
    public function getXmlErrors(): array
    {
        return $this->xmlErrors;
    }

    /**
     * Get formatted error messages.
     *
     * @return array<int, string>
     */
    public function getFormattedErrors(): array
    {
        return array_map(
            fn (\LibXMLError $error): string => sprintf(
                '[%s] Line %d, Column %d: %s',
                match ($error->level) {
                    LIBXML_ERR_WARNING => 'WARNING',
                    LIBXML_ERR_ERROR => 'ERROR',
                    LIBXML_ERR_FATAL => 'FATAL',
                    default => 'UNKNOWN',
                },
                $error->line,
                $error->column,
                trim($error->message),
            ),
            $this->xmlErrors,
        );
    }

    /**
     * Get the raw XML that failed to parse (truncated for safety).
     *
     * @param  int  $maxLength  Maximum length to return
     * @return string Truncated XML content
     */
    public function getRawXml(int $maxLength = 1000): string
    {
        if (strlen($this->rawXml) <= $maxLength) {
            return $this->rawXml;
        }

        return substr($this->rawXml, 0, $maxLength).'... (truncated)';
    }
}
