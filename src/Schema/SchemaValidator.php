<?php

declare(strict_types=1);

namespace Directo\Schema;

use Directo\Exception\SchemaValidationException;
use DOMDocument;
use InvalidArgumentException;

/**
 * Validates XML against XSD schema files.
 */
class SchemaValidator
{
    /**
     * Validates XML against a schema file.
     *
     * @param  string  $xml  The XML content to validate
     * @param  string  $schemaPath  Full path to the schema file
     * @param  array<string, mixed>  $context  Context for error reporting
     *
     * @throws SchemaValidationException
     * @throws InvalidArgumentException
     */
    public function validate(
        string $xml,
        string $schemaPath,
        array $context = [],
    ): void {
        if (! file_exists($schemaPath)) {
            throw new InvalidArgumentException(
                sprintf('Schema file not found: %s', $schemaPath),
            );
        }

        // Capture libxml errors
        $previousUseErrors = libxml_use_internal_errors(true);
        libxml_clear_errors();

        try {
            $dom = new DOMDocument();
            // LIBXML_NOBLANKS is often useful for schema validation to ignore insignificant whitespace
            $dom->loadXML($xml, LIBXML_NOBLANKS);

            if (! $dom->schemaValidate($schemaPath)) {
                $errors = libxml_get_errors();
                libxml_clear_errors();

                throw new SchemaValidationException(
                    sprintf('XML does not conform to schema: %s', basename($schemaPath)),
                    $errors,
                    $schemaPath,
                    array_merge($context, ['schemaPath' => $schemaPath]),
                );
            }
        } finally {
            libxml_use_internal_errors($previousUseErrors);
        }
    }
}
