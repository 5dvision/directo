<?php

declare(strict_types=1);

namespace Directo\Contract;

/**
 * Interface for Directo API endpoints.
 */
interface Endpoint
{
    /**
     * Returns the resource name ('what' parameter).
     *
     * @return string Recource name (e.g., 'customer', 'item')
     */
    public function what(): string;

    /**
     * Returns list of allowed filter keys for this endpoint.
     *
     * @return array<int, string> List of allowed filter key names
     */
    public function allowedFilters(): array;

    /**
     * Returns XML element configuration for PUT operations.
     *
     * @return array{root: string, record: string, key: string|null}
     */
    public function xmlElements(): array;

    /**
     * Returns XSD schema files for each operation.
     *
     * @return array{list?: string|null, put?: string|null}
     */
    public function schemas(): array;

    /**
     * Fetches and lists records from this endpoint.
     *
     * @param  array<string, scalar|\Stringable>  $filters
     * @return array<int, array<string, mixed>>
     */
    public function list(array $filters = []): array;

    /**
     * Creates or updates a record.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function put(array $data): array;

    /**
     * Creates or updates multiple records in a single request.
     *
     * @param  array<int, array<string, mixed>>  $records
     * @return array<string, mixed>
     */
    public function putBatch(array $records): array;
}
