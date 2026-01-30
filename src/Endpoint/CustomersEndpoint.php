<?php

declare(strict_types=1);

namespace Directo\Endpoint;

/**
 * Customers (Kliendid) endpoint.
 *
 * Retrieves customer records from Directo XMLCore API.
 *
 * Available filters:
 * - code: Customer code (exact match)
 * - loyaltycard: Loyalty card number
 * - regno: Registration number
 * - email: Email address
 * - phone: Phone number
 * - closed: Include closed customers (0 or 1)
 * - ts: Timestamp filter for incremental sync
 *
 * Response structure (array keys per record):
 * - kood: Customer code
 * - nimi: Customer name
 * - email: Email address
 * - telefon: Phone number
 * - registrikood: Registration number
 * - aadress: Address
 * - linn: City
 * - postiindex: Postal code
 * - riik: Country code
 * - kliendiryhm: Customer group
 * - hinnaklass: Price class
 * - ...and more depending on Directo configuration
 *
 * @see https://wiki.directo.ee/et/xmlcore_xml
 */
final class CustomersEndpoint extends AbstractEndpoint
{
    /**
     * {@inheritDoc}
     */
    public function what(): string
    {
        return 'customer';
    }

    /**
     * {@inheritDoc}
     */
    public function allowedFilters(): array
    {
        return [
            'code',        // Customer code filter
            'loyaltycard', // Loyalty card number
            'regno',       // Registration number
            'email',       // Email address
            'phone',       // Phone number
            'closed',      // Include closed (0/1)
            'ts',          // Timestamp for incremental sync
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function xmlElements(): array
    {
        return [
            'root' => 'customers',
            'record' => 'customer',
            'key' => 'code',
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function schemas(): array
    {
        return [
            'list' => 'ws_kliendid.xsd',
            'put' => 'xml_IN_kliendid.xsd',
        ];
    }
}
