<?php

declare(strict_types=1);

namespace Directo\Endpoint;

/**
 * Receipts (Laekumised) endpoint.
 *
 * Retrieves receipt/payment records from Directo XMLCore API.
 *
 * **Note**: This is a read-only endpoint. Only list() operations are supported.
 *
 * Available filters:
 * - number: Receipt number (exact match)
 * - date1: Start date filter (YYYY-MM-DD)
 * - date2: End date filter (YYYY-MM-DD)
 * - ts: Timestamp filter for incremental sync
 *
 * Response structure (array keys per record):
 * - number: Receipt number
 * - confirmed: Confirmed status (0/1)
 * - ts: Last modified timestamp
 * - rows: Array of receipt rows with:
 *   - invoice: Invoice number
 *   - order: Order number
 *   - aeg: Time/date
 *   - customer: Customer code
 *   - customername: Customer name
 *   - received: Received amount
 *   - regno: Registration number
 *   - invoicesum: Invoice sum
 * - ...and more depending on Directo configuration
 *
 * @see https://wiki.directo.ee/et/xml_direct#laekumised_receipts
 */
final class ReceiptsEndpoint extends AbstractEndpoint
{
    /**
     * {@inheritDoc}
     */
    public function what(): string
    {
        return 'receipt';
    }

    /**
     * {@inheritDoc}
     *
     * @return list<string>
     */
    public function allowedFilters(): array
    {
        return [
            'number', // Receipt number filter
            'date1',  // Start date (YYYY-MM-DD)
            'date2',  // End date (YYYY-MM-DD)
            'ts',     // Timestamp for incremental sync
        ];
    }

    /**
     * {@inheritDoc}
     *
     * @return array{root: string, record: string, key: string}
     */
    public function xmlElements(): array
    {
        return [
            'root' => 'transport',
            'record' => 'receipt',
            'key' => 'number',
        ];
    }

    /**
     * {@inheritDoc}
     *
     * @return array{list: string, put: ?string}
     */
    public function schemas(): array
    {
        return [
            'list' => 'ws_laekumised.xsd',
            'put' => null,  // Read-only endpoint
        ];
    }
}
