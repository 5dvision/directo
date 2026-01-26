<?php

declare(strict_types=1);

namespace Directo\Parser;

use DOMDocument;
use DOMElement;
use Stringable;

/**
 * Builds XML request bodies from PHP arrays.
 *
 * Used for create/update (PUT) operations to convert
 * PHP arrays into XML format matching Directo input schemas.
 *
 * Design notes:
 * - Generic array-to-XML conversion
 * - No hardcoded endpoint knowledge - caller provides element names
 * - Supports nested arrays (e.g., for line items)
 * - Handles attributes via special '@attributes' key
 * - Use SchemaRegistry for XSD validation to catch mistakes
 *
 * Input XML structure example for items:
 * ```xml
 * <artiklid>
 *   <artikkel kood="ITEM001">
 *     <nimetus>Item Name</nimetus>
 *     <klass>CLASS1</klass>
 *     ...
 *   </artikkel>
 * </artiklid>
 * ```
 */
final class XmlRequestBuilder
{
    /**
     * Special key for XML attributes.
     */
    private const ATTRIBUTES_KEY = '@attributes';

    /**
     * Build XML string from array data.
     *
     * @param  string  $rootElement  The root element name (e.g., 'artiklid')
     * @param  string  $recordElement  The record element name (e.g., 'artikkel')
     * @param  array<string, mixed>  $data  The record data
     * @param  string|null  $keyAttribute  Optional attribute name for key (e.g., 'kood')
     * @return string The XML string
     */
    public function build(
        string $rootElement,
        string $recordElement,
        array $data,
        ?string $keyAttribute = null,
    ): string {
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;

        // Create root element
        $root = $dom->createElement($rootElement);
        $dom->appendChild($root);

        // Create record element
        $record = $dom->createElement($recordElement);

        // Set the key attribute if specified (e.g., kood="ITEM001")
        if ($keyAttribute !== null && isset($data[$keyAttribute])) {
            $record->setAttribute($keyAttribute, (string) $data[$keyAttribute]);
            unset($data[$keyAttribute]); // Remove from data to avoid duplicate
        }

        // Add data elements
        $this->addElements($dom, $record, $data);
        $root->appendChild($record);

        $xml = $dom->saveXML();

        return $xml !== false ? $xml : '';
    }

    /**
     * Build XML for multiple records.
     *
     * @param  string  $rootElement  The root element name
     * @param  string  $recordElement  The record element name
     * @param  array<int, array<string, mixed>>  $records  Array of record data
     * @param  string|null  $keyAttribute  Optional attribute name for key
     * @return string The XML string
     */
    public function buildBatch(
        string $rootElement,
        string $recordElement,
        array $records,
        ?string $keyAttribute = null,
    ): string {
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;

        // Create root element
        $root = $dom->createElement($rootElement);
        $dom->appendChild($root);

        foreach ($records as $data) {
            $record = $dom->createElement($recordElement);

            // Set the key attribute if specified
            if ($keyAttribute !== null && isset($data[$keyAttribute])) {
                $record->setAttribute($keyAttribute, (string) $data[$keyAttribute]);
                unset($data[$keyAttribute]);
            }

            $this->addElements($dom, $record, $data);
            $root->appendChild($record);
        }

        $xml = $dom->saveXML();

        return $xml !== false ? $xml : '';
    }

    /**
     * Add array elements to a DOM parent.
     *
     * @param  DOMDocument  $dom  The document
     * @param  DOMElement  $parent  The parent element
     * @param  array<string, mixed>  $data  The data to add
     */
    private function addElements(DOMDocument $dom, DOMElement $parent, array $data): void
    {
        foreach ($data as $key => $value) {
            // Skip attributes key, handled separately
            if ($key === self::ATTRIBUTES_KEY) {
                if (is_array($value)) {
                    foreach ($value as $attrName => $attrValue) {
                        $parent->setAttribute((string) $attrName, (string) $attrValue);
                    }
                }

                continue;
            }

            if (is_array($value)) {
                // Check if it's a list of items (numeric keys)
                if ($this->isNumericArray($value)) {
                    // Multiple child elements with same name
                    foreach ($value as $item) {
                        $child = $dom->createElement($key);
                        if (is_array($item)) {
                            $this->addElements($dom, $child, $item);
                        } else {
                            $child->textContent = $this->formatValue($item);
                        }
                        $parent->appendChild($child);
                    }
                } else {
                    // Nested associative array = child element with children
                    $child = $dom->createElement($key);
                    $this->addElements($dom, $child, $value);
                    $parent->appendChild($child);
                }
            } else {
                // Simple value
                $child = $dom->createElement($key);
                $child->textContent = $this->formatValue($value);
                $parent->appendChild($child);
            }
        }
    }

    /**
     * Check if array has numeric keys (is a list).
     */
    private function isNumericArray(array $array): bool
    {
        if (empty($array)) {
            return false;
        }

        return array_keys($array) === range(0, count($array) - 1);
    }

    /**
     * Format a value for XML content.
     */
    private function formatValue(mixed $value): string
    {
        if ($value instanceof Stringable) {
            return (string) $value;
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if ($value === null) {
            return '';
        }

        return (string) $value;
    }
}
