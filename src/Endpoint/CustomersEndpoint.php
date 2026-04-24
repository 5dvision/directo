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
 * Response structure (parsed array keys per record):
 * - @code: Customer code
 * - @name: Customer name
 * - @email: Email address
 * - @phone: Phone number
 * - @regno: Registration number
 * - @address1: Address line 1
 * - @address2: Address line 2 / city depending on Directo setup
 * - @country: Country code
 * - @closed: Closed flag
 * - datafields: Nested custom fields container
 * - ...and more depending on Directo configuration

 * PUT payloads must follow the Directo IN schema, which uses English
 * attribute names on <customer> plus optional nested containers such as
 * <datafields>.
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
