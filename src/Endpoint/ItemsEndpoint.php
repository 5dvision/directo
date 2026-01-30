<?php

declare(strict_types=1);

namespace Directo\Endpoint;

/**
 * Items (Artiklid) endpoint.
 *
 * Retrieves item/product records from Directo XMLCore API.
 *
 * Available filters:
 * - class: Item class/category
 * - code: Item code (exact match)
 * - type: Item type
 * - barcode: Barcode/EAN
 * - supplier: Supplier code
 * - supplieritem: Supplier's item code
 * - closed: Include closed items (0 or 1)
 * - ts: Timestamp filter for incremental sync
 *
 * Response structure (array keys per record):
 * - kood: Item code
 * - nimetus: Item name
 * - nimetus2: Alternative name
 * - klass: Item class
 * - yksus: Unit of measure
 * - ribakood: Barcode
 * - hind: Price
 * - kaal: Weight
 * - maht: Volume
 * - tarnija: Supplier code
 * - tarnija_artikkel: Supplier's item code
 * - ...and more depending on Directo configuration
 *
 * @see https://wiki.directo.ee/et/xmlcore_xml
 */
final class ItemsEndpoint extends AbstractEndpoint
{
    /**
     * {@inheritDoc}
     */
    public function what(): string
    {
        return 'item';
    }

    /**
     * {@inheritDoc}
     *
     * @return list<string>
     */
    public function allowedFilters(): array
    {
        return [
            'class',        // Item class/category
            'code',         // Item code
            'type',         // Item type
            'barcode',      // Barcode/EAN
            'supplier',     // Supplier code
            'supplieritem', // Supplier's item code
            'closed',       // Include closed (0/1)
            'ts',           // Timestamp for incremental sync
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
            'root' => 'items',
            'record' => 'item',
            'key' => 'code',
        ];
    }

    /**
     * {@inheritDoc}
     *
     * @return array{list: string, put: string}
     */
    public function schemas(): array
    {
        return [
            'list' => 'ws_artiklid.xsd',
            'put' => 'xml_IN_artiklid.xsd',
        ];
    }
}
