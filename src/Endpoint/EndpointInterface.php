<?php

declare(strict_types=1);

namespace Directo\Endpoint;

/**
 * Interface for all Directo XMLCore endpoints.
 *
 * Each endpoint must define:
 * - what(): The 'what' parameter value for the API
 * - allowedFilters(): List of valid filter keys
 * - xmlElements(): XML element names for PUT operations
 * - schemas(): XSD schema files per operation
 *
 * Design notes:
 * - Everything about an endpoint in one class
 * - Schemas defined per operation (list, put) for clarity
 * - Adding new endpoint = implement interface, no registry updates
 */
interface EndpointInterface
{
    /**
     * Get the 'what' parameter value for this endpoint.
     *
     * This value is used in API requests and schema lookups.
     *
     * @example 'customer', 'item'
     */
    public function what(): string;

    /**
     * Get list of allowed filter keys for this endpoint.
     *
     * Used for validation: unknown filters cause an exception.
     * This is a developer-error check (fail fast).
     *
     * @return array<int, string> List of allowed filter key names
     */
    public function allowedFilters(): array;

    /**
     * Get XML element configuration for PUT operations.
     *
     * @return array{root: string, record: string, key: string|null}
     *                                                               - root: Root element name (e.g., 'artiklid')
     *                                                               - record: Record element name (e.g., 'artikkel')
     *                                                               - key: Key attribute name (e.g., 'kood') or null if no key attribute
     */
    public function xmlElements(): array;

    /**
     * Get XSD schema files for each operation.
     *
     * Returns schema filenames keyed by operation name.
     * Return null for an operation to skip validation.
     *
     * @return array{list?: string|null, put?: string|null}
     *                                                      - list: Schema for list() responses (e.g., 'ws_artiklid.xsd')
     *                                                      - put: Schema for put() requests (e.g., 'xml_IN_artiklid.xsd')
     *
     * @example ['list' => 'ws_artiklid.xsd', 'put' => 'xml_IN_artiklid.xsd']
     */
    public function schemas(): array;

    /**
     * Fetch and list records from this endpoint.
     *
     * @param  array<string, scalar|\Stringable>  $filters  Optional filters
     * @return array<int, array<string, mixed>> Array of records
     *
     * @throws \Directo\Exception\InvalidFilterException If unknown filter provided
     * @throws \Directo\Exception\TransportException On network errors
     * @throws \Directo\Exception\HttpException On HTTP errors
     * @throws \Directo\Exception\XmlParseException On XML parse errors
     * @throws \Directo\Exception\SchemaValidationException If validation enabled and fails
     */
    public function list(array $filters = []): array;

    /**
     * Create or update a record.
     *
     * Uses PUT request with XML body. Creates if record doesn't exist,
     * updates if it does (upsert behavior).
     *
     * @param  array<string, mixed>  $data  Record data (must include key field, e.g., 'kood')
     * @return array<string, mixed> Response data
     *
     * @throws \Directo\Exception\TransportException On network errors
     * @throws \Directo\Exception\HttpException On HTTP errors
     * @throws \Directo\Exception\ApiErrorException On API-level errors
     * @throws \Directo\Exception\SchemaValidationException If validation enabled and fails
     */
    public function put(array $data): array;

    /**
     * Create or update multiple records in a single request.
     *
     * @param  array<int, array<string, mixed>>  $records  Array of record data
     * @return array<string, mixed> Response data
     *
     * @throws \Directo\Exception\TransportException On network errors
     * @throws \Directo\Exception\HttpException On HTTP errors
     * @throws \Directo\Exception\ApiErrorException On API-level errors
     * @throws \Directo\Exception\SchemaValidationException If validation enabled and fails
     */
    public function putBatch(array $records): array;
}
