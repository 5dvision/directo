<?php

declare(strict_types=1);

namespace Directo\Contract;

/**
 * Interface for Directo API endpoints.
 */
interface Endpoint
{
    /**
     * Get the resource name ('what' parameter).
     *
     * @return string Recource name (e.g., 'customer', 'item')
     */
    public function what(): string;

    /**
     * Get list of allowed filter keys for this endpoint.
     *
     * @return array<int, string> List of allowed filter key names
     */
    public function allowedFilters(): array;

    /**
     * Get XML element configuration for PUT operations.
     *
     * @return array{root: string, record: string, key: string|null}
     */
    public function xmlElements(): array;

    /**
     * Get XSD schema files for each operation.
     *
     * @return array{list?: string|null, put?: string|null}
     */
    public function schemas(): array;

    /**
     * Fetch and list records from this endpoint.
     *
     * @param  array<string, scalar|\Stringable>  $filters
     * @return array<int, array<string, mixed>>
     */
    public function list(array $filters = []): array;

    /**
     * Create or update a record.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function put(array $data): array;

    /**
     * Create or update multiple records in a single request.
     *
     * @param  array<int, array<string, mixed>>  $records
     * @return array<string, mixed>
     */
    public function putBatch(array $records): array;
}
