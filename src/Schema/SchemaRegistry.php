<?php

declare(strict_types=1);

namespace Directo\Schema;

use Directo\Exception\SchemaValidationException;
use DOMDocument;

/**
 * Schema validator for XSD validation.
 *
 * Validates XML against XSD schema files stored locally.
 * Endpoints define their own schemas via the schemas() method.
 *
 * Design notes:
 * - Simple validation: just validate XML against schema file
 * - Schemas stored in resources/xsd/ directory
 * - Endpoints define which schemas to use (no central registry)
 *
 * Trade-offs:
 * - Schema validation is CPU-intensive (DOM parsing + XSD validation)
 * - Disabled by default; enable for debugging or in batch jobs
 * - Schema drift: remote schemas may change; use composer schemas:update
 *   periodically to refresh local copies
 *
 * Caching strategy:
 * - Schemas are cached on disk (resources/xsd/)
 * - No runtime caching needed (DOMDocument loads from file)
 * - Update via CLI command, not automatically (predictable builds)
 */
final class SchemaRegistry
{
    /**
     * Create a new schema registry.
     *
     * @param  string  $schemaBasePath  Local directory containing XSD files
     * @param  string  $schemaBaseUrl  Base URL for downloading schemas
     */
    public function __construct(
        private readonly string $schemaBasePath,
        private readonly string $schemaBaseUrl,
    ) {
    }

    /**
     * Get the base path for schema files (local directory).
     *
     * @return string Local directory path
     */
    public function getSchemaBasePath(): string
    {
        return $this->schemaBasePath;
    }

    /**
     * Get the base URL for downloading schemas.
     *
     * @return string Base URL for schema downloads
     */
    public function getSchemaBaseUrl(): string
    {
        return $this->schemaBaseUrl;
    }

    /**
     * Get full path to a schema file.
     *
     * @param  string  $schemaFile  Schema filename (e.g., 'ws_artiklid.xsd')
     * @return string Full local path to the schema file
     */
    public function getSchemaPath(string $schemaFile): string
    {
        return $this->schemaBasePath.'/'.$schemaFile;
    }

    /**
     * Check if a schema file exists locally.
     *
     * @param  string  $schemaFile  Schema filename
     * @return bool True if file exists
     */
    public function schemaFileExists(string $schemaFile): bool
    {
        return file_exists($this->getSchemaPath($schemaFile));
    }

    /**
     * Get the download URL for a schema file.
     *
     * @param  string  $schemaFile  Schema filename
     * @return string Full URL to download the schema
     */
    public function getSchemaUrl(string $schemaFile): string
    {
        return $this->schemaBaseUrl.$schemaFile;
    }

    /**
     * Validate XML against a schema file.
     *
     * @param  string  $xml  The XML content to validate
     * @param  string  $schemaFile  The schema filename (e.g., 'ws_artiklid.xsd')
     * @param  array<string, mixed>  $context  Context for error reporting
     *
     * @throws SchemaValidationException If validation fails
     * @throws \InvalidArgumentException If schema file not found
     */
    public function validateFile(
        string $xml,
        string $schemaFile,
        array $context = [],
    ): void {
        $schemaPath = $this->getSchemaPath($schemaFile);

        if (! file_exists($schemaPath)) {
            throw new \InvalidArgumentException(
                sprintf(
                    'Schema file not found: %s. Run "composer schemas:update" to download schemas.',
                    $schemaPath,
                ),
            );
        }

        // Capture libxml errors
        $previousUseErrors = libxml_use_internal_errors(true);
        libxml_clear_errors();

        try {
            $dom = new DOMDocument();
            $dom->loadXML($xml, LIBXML_NOBLANKS);

            if (! $dom->schemaValidate($schemaPath)) {
                $errors = libxml_get_errors();
                libxml_clear_errors();

                throw new SchemaValidationException(
                    sprintf('XML does not conform to schema: %s', $schemaFile),
                    $errors,
                    $schemaPath,
                    array_merge($context, ['schemaFile' => $schemaFile]),
                );
            }
        } finally {
            libxml_use_internal_errors($previousUseErrors);
        }
    }
}
